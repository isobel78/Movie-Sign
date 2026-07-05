<?php
// Atlanta Daniel
// July 2026
// film_details.php - server-side proxy for TMDB movie details, used by the
// watchlist film-detail overlay. Mirrors tmdb_search.php's auth/curl pattern.

session_start();

//check that user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

//TMDB access token
$tmdbConfig = require __DIR__ . '/../Model/tmdb_config.php';
define('TMDB_BEARER', $tmdbConfig['bearer_token']);

//film ID param — TMDB IDs are numeric
$filmID = preg_replace('/\D/', '', trim($_GET['film_id'] ?? ''));

header('Content-Type: application/json');

if ($filmID === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing film_id']);
    exit;
}

$url = "https://api.themoviedb.org/3/movie/{$filmID}?"
     . http_build_query([
           'language'           => 'en-US',
           'append_to_response' => 'credits,watch/providers',
       ]);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . TMDB_BEARER,
        'Accept: application/json',
    ],
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false || $curlError) {
    http_response_code(502);
    echo json_encode(['error' => 'Could not reach TMDB: ' . $curlError]);
    exit;
}

if ($httpCode === 401) {
    http_response_code(502);
    echo json_encode(['error' => 'TMDB API key is invalid or expired.']);
    exit;
}

if ($httpCode === 404) {
    http_response_code(404);
    echo json_encode(['error' => 'Film not found.']);
    exit;
}

if ($httpCode !== 200) {
    http_response_code(502);
    echo json_encode(['error' => "TMDB returned HTTP $httpCode"]);
    exit;
}

$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(502);
    echo json_encode(['error' => 'Invalid response from TMDB.']);
    exit;
}

//shape the response for the overlay — only what the front-end needs
$POSTER_BASE  = 'https://image.tmdb.org/t/p/w342';
$PROFILE_BASE = 'https://image.tmdb.org/t/p/w185';
$LOGO_BASE    = 'https://image.tmdb.org/t/p/w45';

$director = null;
foreach (($data['credits']['crew'] ?? []) as $c) {
    if (($c['job'] ?? '') === 'Director') { $director = $c['name']; break; }
}

$cast = [];
foreach (array_slice($data['credits']['cast'] ?? [], 0, 8) as $person) {
    $cast[] = [
        'name'      => $person['name'] ?? '',
        'character' => $person['character'] ?? '',
        'photo_url' => !empty($person['profile_path']) ? $PROFILE_BASE . $person['profile_path'] : null,
    ];
}

$usProviders = $data['watch/providers']['results']['US'] ?? null;
$providers = ['flatrate' => [], 'rent' => [], 'buy' => []];
if ($usProviders) {
    foreach (['flatrate', 'rent', 'buy'] as $key) {
        foreach (($usProviders[$key] ?? []) as $p) {
            $providers[$key][] = [
                'name' => $p['provider_name'] ?? '',
                'logo_url' => !empty($p['logo_path']) ? $LOGO_BASE . $p['logo_path'] : null,
            ];
        }
    }
}

echo json_encode([
    'id'          => $data['id'] ?? null,
    'title'       => $data['title'] ?? 'Unknown',
    'tagline'     => $data['tagline'] ?? '',
    'overview'    => $data['overview'] ?? '',
    'year'        => isset($data['release_date']) ? substr($data['release_date'], 0, 4) : '',
    'runtime'     => $data['runtime'] ?? null,
    'rating'      => $data['vote_average'] ?? 0,
    'vote_count'  => $data['vote_count'] ?? 0,
    'release_date' => $data['release_date'] ?? '',
    'director'    => $director,
    'poster_url'  => !empty($data['poster_path']) ? $POSTER_BASE . $data['poster_path'] : null,
    'cast'        => $cast,
]);