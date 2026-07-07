<?php
// Atlanta Daniel
// July 2026
// streaming_check.php - "In case of Emergency, Pull the Lever!"
// Pulls every title on the user's watchlist and checks TMDB watch/providers
// (US region) to show where each one is streaming, renting, or available to buy.

session_start();

require_once(__DIR__ . '/../app/Model/db_session.php');
SessionDB::resumeFromCookie();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once(__DIR__ . '/../app/Model/db_watchlist.php');

$userID = (int) $_SESSION['user_id'];
$user_email = htmlspecialchars($_SESSION['user_email'] ?? '');

$watchlist = WatchlistDB::getWatchlist($userID);

$tmdbConfig = require __DIR__ . '/../app/Model/tmdb_config.php';
define('TMDB_BEARER', $tmdbConfig['bearer_token']);
define('LOGO_BASE', 'https://image.tmdb.org/t/p/w45');

function esc(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

//fetch US watch/providers for a single TMDB film ID
//returns ['ok' => bool, 'providers' => ['flatrate'=>[], 'rent'=>[], 'buy'=>[]], 'error' => string|null]
function fetchProviders(string $filmID): array {
    $url = "https://api.themoviedb.org/3/movie/{$filmID}/watch/providers";

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
        return ['ok' => false, 'providers' => null, 'error' => 'Could not reach TMDB.'];
    }
    if ($httpCode !== 200) {
        return ['ok' => false, 'providers' => null, 'error' => "TMDB returned HTTP $httpCode."];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['ok' => false, 'providers' => null, 'error' => 'Invalid response from TMDB.'];
    }

    $usProviders = $data['results']['US'] ?? null;
    $providers = ['flatrate' => [], 'rent' => [], 'buy' => []];

    if ($usProviders) {
        foreach (['flatrate', 'rent', 'buy'] as $key) {
            foreach (($usProviders[$key] ?? []) as $p) {
                $providers[$key][] = [
                    'name' => $p['provider_name'] ?? '',
                    'logo_url' => !empty($p['logo_path']) ? LOGO_BASE . $p['logo_path'] : null,
                ];
            }
        }
    }

    return ['ok' => true, 'providers' => $providers, 'error' => null];
}

//run the lookup for every watchlist title now, server-side
$results = [];
foreach ($watchlist as $item) {
    $results[] = [
        'item' => $item,
        'lookup' => fetchProviders($item['film_ID']),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Streaming Check — MovieSign!</title>

    <link rel="icon" type="image/x-icon" href="icons/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./styles/main.css?v=<?= filemtime(__DIR__ . '/styles/main.css') ?>">
</head>
<body>

<header>
    <a href="index.php" class="wordmark" title="home">🚨Movie<span>Sign</span>!</a>
    <div class="user-bar user-bar-desktop">
        <span class="user-email"><?= $user_email ?></span>
        <a href="index.php" class="btn-account">← Back</a>
    </div>
    <!-- Mobile-only back button -->
    <a href="index.php" class="btn-account btn-back-mobile">← Back</a>
</header>

<main>
    <h1 style="font-family:var(--font-display); font-size:1.8rem; letter-spacing:0.05em; color:var(--sub); margin-bottom:0.35rem;">
        📺 Streaming Check
    </h1>
    <p style="color:var(--sub); font-size:0.9rem; margin-bottom:1.5rem;">
        Nothing showing nearby? Here's where your watchlist titles stand right now for US streaming, rental, and purchase.
    </p>

    <?php if (empty($results)): ?>
        <div class="watch-check-empty-page">
            <div class="icon">🎞️</div>
            <h2>Your watchlist is empty</h2>
            <p>Add some films from the dashboard first, then check back here.</p>
        </div>
    <?php else: ?>
        <?php foreach ($results as $r):
            $item = $r['item'];
            $lookup = $r['lookup'];
        ?>
            <div class="watch-check-card">
                <?php if (!empty($item['poster_url'])): ?>
                    <img class="watch-check-poster" src="<?= esc($item['poster_url']) ?>" alt="<?= esc($item['title']) ?> poster" loading="lazy">
                <?php else: ?>
                    <div class="watch-check-poster-placeholder">🎞️</div>
                <?php endif; ?>

                <div style="flex:1; min-width:0;">
                    <div class="watch-check-title"><?= esc($item['title']) ?></div>

                    <?php if (!$lookup['ok']): ?>
                        <div class="alert-error"><?= esc($lookup['error']) ?></div>
                    <?php else:
                        $providers = $lookup['providers'];
                        $hasAny = !empty($providers['flatrate']) || !empty($providers['rent']) || !empty($providers['buy']);
                    ?>
                        <?php if (!$hasAny): ?>
                            <div class="alert-info">Not currently available to stream, rent, or buy in the US.</div>
                        <?php else: ?>
                            <?php foreach (['flatrate' => 'Streaming', 'rent' => 'Rent', 'buy' => 'Buy'] as $key => $label):
                                if (empty($providers[$key])) continue;
                            ?>
                                <div class="provider-group">
                                    <h4><?= esc($label) ?></h4>
                                    <div class="provider-row">
                                        <?php foreach ($providers[$key] as $p): ?>
                                            <?php if ($p['logo_url']): ?>
                                                <img class="provider-logo" title="<?= esc($p['name']) ?>"
                                                     src="<?= esc($p['logo_url']) ?>" alt="<?= esc($p['name']) ?>">
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <p style="color:var(--muted); font-size:0.75rem; margin-top:1rem;">
            Streaming data provided by TMDB and may not reflect every regional service or recent changes.
        </p>
    <?php endif; ?>
</main>

</body>
</html>
