<?php
// Atlanta Daniel
// May 2026
// movieglu_showtimes.php - Server-side proxy for MovieGlu showtime data.
//
// Converts the user's saved zip code to lat/lng server-side via Zippopotam.us
// no browser GPS required
//
// The MOVIEGLU_ENV constant is defined in public/index.php and passed here as the GET param 'env'.
//
// Returns JSON:
//   { "showtimes": [ { "film_id", "title", "times": [ { "cinema", "showtime" } ] } ] }
//   { "error": "..." } on failure

declare(strict_types=1);

session_start();

//check if user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

//resolve requested showtime date
//defaults to today in UTC if missing or invalid
$rawDate = trim($_GET['date'] ?? '');
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) {
    $showtimeDate = $rawDate;
} else {
    $showtimeDate = gmdate('Y-m-d');
}

//resolve user's local timezone (sent from the browser via Intl.DateTimeFormat)
//defaults to UTC if missing or invalid.
$requestedTz = trim($_GET['tz'] ?? 'UTC');
try {
    $userTz = new DateTimeZone($requestedTz);
} catch (Exception $e) {
    $userTz = new DateTimeZone('UTC');
}

//MovieGlu credentials
$movieGluCreds = require __DIR__ . '/../Model/movieglu_config.php';

const MOVIEGLU_BASE = 'https://api-gate2.movieglu.com/';
const MOVIEGLU_API_VERSION = 'v201';

//Resolve environment passed from index.php
//default to sandbox for safety
$env = ($_GET['env'] ?? 'sandbox') === 'us' ? 'us' : 'sandbox';
$creds = $movieGluCreds[$env];

//Resolve geolocation
// Sandbox always uses its fixed test coordinates
// US mode: convert the user's saved zip code to lat/lng via Zippopotam.us
if ($env === 'sandbox') {
    $geoHeader = $creds['geo'];
} else {
    $zip = trim($_SESSION['user_zip'] ?? '');

    if (!preg_match('/^\d{5}/', $zip)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No valid zip code on your account. Please update it in Account Settings.']);
        exit;
    }

    // Zippopotam.us — free, no API key, returns lat/lng for US zip codes
    $zipUrl = 'https://api.zippopotam.us/us/' . urlencode(substr($zip, 0, 5));
    $zipCh = curl_init();
    curl_setopt_array($zipCh, [
        CURLOPT_URL => $zipUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $zipBody = curl_exec($zipCh);
    $zipCode = curl_getinfo($zipCh, CURLINFO_HTTP_CODE);
    $zipError = curl_error($zipCh);
    curl_close($zipCh);

    if ($zipBody === false || $zipError || $zipCode !== 200) {
        http_response_code(502);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Could not geocode your zip code. Try again shortly.']);
        exit;
    }

    $zipData = json_decode($zipBody, true);
    $lat = (float) ($zipData['places'][0]['latitude']  ?? 0);
    $lng = (float) ($zipData['places'][0]['longitude'] ?? 0);

    if ($lat === 0.0 && $lng === 0.0) {
        http_response_code(502);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Could not determine coordinates for your zip code.']);
        exit;
    }

    $geoHeader = round($lat, 4) . ';' . round($lng, 4);
}

// ISO 8601 datetime required by MovieGlu
$nowLocal = new DateTime('now', $userTz);
$deviceDatetime = $nowLocal->format("Y-m-d\TH:i:s.000\Z");

//Helper cURL request to MovieGlu
function movieglu_get(string $endpoint, array $params, array $creds, string $geo, string $datetime): array {
    $url = MOVIEGLU_BASE . $endpoint . '?' . http_build_query($params);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'client: ' . $creds['client'],
            'x-api-key: ' . $creds['x-api-key'],
            'authorization: ' . $creds['authorization'],
            'territory: ' . $creds['territory'],
            'api-version: ' . MOVIEGLU_API_VERSION,
            'geolocation: ' . $geo,
            'device-datetime: ' . $datetime,
        ],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($body === false || $curlError) {
        return ['ok' => false, 'error' => 'cURL error: ' . $curlError];
    }
    if ($httpCode === 401 || $httpCode === 403) {
        return ['ok' => false, 'error' => "MovieGlu auth failed (HTTP $httpCode). Check credentials."];
    }
    if ($httpCode !== 200) {
        return ['ok' => false, 'error' => "MovieGlu returned HTTP $httpCode"];
    }

    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['ok' => false, 'error' => 'Invalid JSON from MovieGlu.'];
    }

    return ['ok' => true, 'data' => $data];
}


//Step 1
//Fetch films showing near the user on the requested date
$filmsResult = movieglu_get(
    'filmsNowShowing/',
    ['n' => 10, 'date' => $showtimeDate],
    $creds,
    $geoHeader,
    $deviceDatetime
);

if (!$filmsResult['ok']) {
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode(['error' => $filmsResult['error']]);
    exit;
}

$nowShowingFilms = $filmsResult['data']['films'] ?? [];


if (empty($nowShowingFilms)) {
    header('Content-Type: application/json');
    echo json_encode(['showtimes' => []]);
    exit;
}

//Step 2
//cross-reference with the user's watchlist (passed from the client)

//have to use a case-insensitive title match since there is no shared film ID
//between TMDB (the watchlist) and MovieGlu (the showtimes)
$rawTitles = $_GET['titles'] ?? '';
$watchlistTitles = [];
if ($rawTitles) {
    $decoded = json_decode($rawTitles, true);
    if (is_array($decoded)) {
        foreach ($decoded as $t) {
            $watchlistTitles[] = mb_strtolower(trim((string) $t));
        }
    }
}

$matchedFilms = [];
foreach ($nowShowingFilms as $film) {
    $mgTitle = mb_strtolower(trim($film['film_name'] ?? ''));
    if (empty($watchlistTitles) || in_array($mgTitle, $watchlistTitles, true)) {
        $matchedFilms[] = $film;
    }
}

if (empty($matchedFilms)) {
    header('Content-Type: application/json');
    echo json_encode(['showtimes' => [], 'all_showing' => array_map(
        fn($f) => $f['film_name'] ?? '', $nowShowingFilms
    )]);
    exit;
}

//radius filter - accepted values: 10, 25, 50, 100. Default: 10.
$rawRadius = (int) ($_GET['radius'] ?? 10);
$radiusMiles = in_array($rawRadius, [10, 25, 50, 100], true) ? $rawRadius : 10;


//Step 3
//for each matched film, fetch cinemas + showtimes
$showtimes = [];

foreach ($matchedFilms as $film) {
    $filmId = $film['film_id'] ?? null;
    $filmName = $film['film_name'] ?? 'Unknown';
    if (!$filmId) continue;

    $cinemasResult = movieglu_get(
        'filmShowTimes/',
        [
            'film_id' => $filmId,
            'date' => $showtimeDate,
            'n' => 25,
        ],
        $creds,
        $geoHeader,
        $deviceDatetime
    );

    if (!$cinemasResult['ok']) {
        // Skip this film silently; don't abort the whole response
        continue;
    }

    $cinemas = $cinemasResult['data']['cinemas'] ?? [];
    $times = [];

    foreach ($cinemas as $cinema) {
        //filter by radius
        //probably won't work in sandbox mode — skip the filter in that case
        $distance = isset($cinema['distance']) ? (float) $cinema['distance'] : null;
        if ($env !== 'sandbox' && $distance !== null && $distance > $radiusMiles) {
            continue;
        }

        $cinemaName = $cinema['cinema_name'] ?? 'Unknown Cinema';

        foreach ($cinema['showings']['Standard']['times'] ?? [] as $t) {
            $rawTime = $t['start_time'] ?? '';

            //MovieGlu returns times as "HH:MM" local theater time (no timezone info)
            // If MovieGlu ever returns ISO 8601 timestamps, swap in the conversion below.
            $displayTime = $rawTime;
            if (preg_match('/^\d{2}:\d{2}$/', $rawTime)) {
                // Parse and reformat as h:mm AM/PM in the user's timezone
                try {
                    $dt = new DateTime('today ' . $rawTime, $userTz);
                    $displayTime = $dt->format('g:i A');
                } catch (Exception $e) {
                    $displayTime = $rawTime; // fallback: show raw
                }
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}T/', $rawTime)) {
                // Full ISO 8601 — convert from UTC to local timezone
                try {
                    $dt = new DateTime($rawTime, new DateTimeZone('UTC'));
                    $dt->setTimezone($userTz);
                    $displayTime = $dt->format('g:i A');
                } catch (Exception $e) {
                    $displayTime = $rawTime;
                }
            }

            $times[] = [
                'cinema' => $cinemaName,
                'showtime' => $displayTime,
                'distance' => $distance,
            ];
        }
    }

    if (!empty($times)) {
        $showtimes[] = [
            'film_id' => (string) $filmId,
            'title' => $filmName,
            'poster' => $film['images']['poster']['1']['medium']['film_image'] ?? null,
            'times' => $times,
        ];
    }
}

header('Content-Type: application/json');
echo json_encode(['showtimes' => $showtimes]);