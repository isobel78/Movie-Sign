<?php
// Atlanta Daniel
// May 2026
// tmdb_search.php - server-side proxy for TMDB movie search, used for watchlist items

session_start();

//check that user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

//TMDB access token
define('TMDB_BEARER', 'eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiI5MzJhYjVkYzQ1M2RhNTdiZTFlNjcwZjgyOGFkMTE3MyIsIm5iZiI6MTc3MDUwODk2Mi4xMTUsInN1YiI6IjY5ODdkMmEyZGYzNmMxMzhjNzNhOWJjNyIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.fW5sTF9QxnVlRGNtYUoPcnEjdVr-kjJNOh1rm_hTJrs');

//search query params
$query = trim($_GET['q'] ?? '');
if (strlen($query) < 2) {
    header('Content-Type: application/json');
    echo json_encode(['results' => []]);
    exit;
}

//TMDB request url
$url = 'https://api.themoviedb.org/3/search/movie?'
     . http_build_query([
           'query' => $query,
           'include_adult' => 'false',
           'language' => 'en-US',
           'page' => 1,
       ]);

//call TMDB using cURL
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

header('Content-Type: application/json');

//any errors?
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

//shape the response for what the front-end needs
$POSTER_BASE = 'https://image.tmdb.org/t/p/w300';

$movies = [];
foreach (($data['results'] ?? []) as $movie) {
    $posterURL = $movie['poster_path']
        ? $POSTER_BASE . $movie['poster_path']
        : null;

    $movies[] = [
        'film_id'  => (string) $movie['id'],
        'title' => $movie['title'] ?? 'Unknown',
        'year' => isset($movie['release_date']) ? substr($movie['release_date'], 0, 4) : '',
        'overview' => $movie['overview']    ?? '',
        'poster_url' => $posterURL,
        'rating' => $movie['vote_average'] ?? 0,
    ];
}

echo json_encode(['results' => array_slice($movies, 0, 8)]);