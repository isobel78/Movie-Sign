<?php
// Atlanta Daniel
// June 2026
// gracenote_showtimes.php - Server-side proxy for Gracenote showtime data.
//
// Calls data.tmsapi.com/v1.1/movies/showings with the user's zip code.
// Cross-references results against the watchlist titles passed from the client.
//
// Returns JSON:
//   { "showtimes": [ { "film_id", "title", "times": [ { "cinema", "showtime" } ] } ] }
//   { "error": "..." } on failure

declare(strict_types=1);

session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Resolve requested showtime date
$rawDate = trim($_GET['date'] ?? '');
$showtimeDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)
    ? $rawDate
    : gmdate('Y-m-d');

// Resolve user timezone
$requestedTz = trim($_GET['tz'] ?? 'UTC');
try {
    $userTz = new DateTimeZone($requestedTz);
} catch (Exception $e) {
    $userTz = new DateTimeZone('UTC');
}

// Gracenote config
$cfg = require __DIR__ . '/../Model/gracenote_config.php';

// Zip code from session
$zip = trim($_SESSION['user_zip'] ?? '');
if (!preg_match('/^\d{5}/', $zip)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No valid zip code on your account. Please update it in Account Settings.']);
    exit;
}
$zip = substr($zip, 0, 5);

// Radius — Gracenote accepts miles as 'radius' param; accepted values: 10, 25, 50, 100
$rawRadius = (int) ($_GET['radius'] ?? 10);
$radiusMiles = in_array($rawRadius, [10, 25, 50, 100], true) ? $rawRadius : 10;

// Watchlist titles from client
$watchlistTitles = [];
$rawTitles = $_GET['titles'] ?? '';
if ($rawTitles) {
    $decoded = json_decode($rawTitles, true);
    if (is_array($decoded)) {
        foreach ($decoded as $t) {
            $watchlistTitles[] = mb_strtolower(trim((string) $t));
        }
    }
}

// Fetch movies showing near the zip on the requested date
$url = $cfg['base_url'] . 'movies/showings?' . http_build_query([
    'api_key' => $cfg['api_key'],
    'zip' => $zip,
    'startDate' => $showtimeDate,
    'numDays' => 1,
    'radius' => $radiusMiles,
]);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$body = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($body === false || $curlError) {
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'cURL error: ' . $curlError]);
    exit;
}
if ($httpCode === 401 || $httpCode === 403) {
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode(['error' => "Gracenote auth failed (HTTP $httpCode). Check API key."]);
    exit;
}
if ($httpCode !== 200) {
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode(['error' => "Gracenote returned HTTP $httpCode"]);
    exit;
}

$data = json_decode($body, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid JSON from Gracenote.']);
    exit;
}

// Gracenote response is a flat array of showing objects:
// [ { "tmsId", "title", "showtimes": [ { "dateTime", "theatre": { "id", "name" } } ] } ]
$allShowing = $data ?? [];

if (empty($allShowing)) {
    header('Content-Type: application/json');
    echo json_encode(['showtimes' => []]);
    exit;
}

// Cross-reference against watchlist
$matched = [];
$allTitles = [];
foreach ($allShowing as $film) {
    $title = $film['title'] ?? '';
    $allTitles[] = $title;
    if (empty($watchlistTitles) || in_array(mb_strtolower(trim($title)), $watchlistTitles, true)) {
        $matched[] = $film;
    }
}

if (empty($matched)) {
    header('Content-Type: application/json');
    echo json_encode(['showtimes' => [], 'all_showing' => $allTitles]);
    exit;
}

// Build output — group showtimes by film, then by cinema
$showtimes = [];
$now = new DateTime('now', $userTz);

foreach ($matched as $film) {
    $tmsId = $film['tmsId'] ?? '';
    $title = $film['title'] ?? 'Unknown';
    $times = [];

    foreach ($film['showtimes'] ?? [] as $showing) {
        $cinemaName = $showing['theatre']['name'] ?? 'Unknown Cinema';
        $rawTime = $showing['dateTime'] ?? '';  // ISO 8601: "2026-06-28T14:00"
        $distanceMiles = isset($showing['theatre']['distance'])
            ? round((float) $showing['theatre']['distance'], 1)
            : null;
        $displayTime = $rawTime;

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $rawTime)) {
            try {
                // Gracenote times are local theater time, no timezone offset
                $dt = new DateTime($rawTime, $userTz);

                // Skip past showtimes
                if ($dt < $now) {
                    continue;
                }

                $displayTime = $dt->format('g:i A');
            } catch (Exception $e) {
                $displayTime = $rawTime;
            }
        }

        $times[] = [
            'cinema'   => $cinemaName,
            'showtime' => $displayTime,
            'distance' => $distanceMiles,
        ];
    }

    if (!empty($times)) {
        $showtimes[] = [
            'film_id' => $tmsId,
            'title'   => $title,
            'poster'  => null, // poster coming from TMDB
            'times'   => $times,
        ];
    }
}

header('Content-Type: application/json');
echo json_encode(['showtimes' => $showtimes, 'all_showing' => $allTitles]);