<?php
// Atlanta Daniel
// May 2026
// index.php - Main Dashboard

session_start();

//resume from a remember-me cookie if the session has expired
require_once(__DIR__ . '/../app/Model/db_session.php');
SessionDB::resumeFromCookie();

//check if user is already logged in
// if not, direct to login page
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once(__DIR__ . '/../app/Model/db_watchlist.php');

$userID = (int) $_SESSION['user_id'];
$user_email = htmlspecialchars($_SESSION['user_email'] ?? '');
$user_zip = htmlspecialchars($_SESSION['user_zip'] ?? '');

//load user's watchlist from DB
$watchlist = WatchlistDB::getWatchlist($userID);

//flash message
$flash_type = '';
$flash_msg = '';
if (!empty($_SESSION['flash_message'])) {
    $flash_type = htmlspecialchars($_SESSION['flash_type'] ?? 'success');
    $flash_msg = htmlspecialchars($_SESSION['flash_message']);
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚨MovieSign!</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="./styles/main.css">
    
</head>

<body>

<header>
    <div class="wordmark">🚨Movie<span>Sign</span>!</div>
    <div class="user-bar">
        <span class="user-email"><?= $user_email ?></span>
        <span class="zip-display" id="zip-display" title="Your theater search location">
            📍 <span id="zip-value"><?= $user_zip ?></span>
        </span>

        <button type="button" id="geo-btn-header" class="btn-geo-sm" title="Update location from GPS">⊙</button>

        <a href="account.php" class="btn-account">⚙ Account</a>

        <form method="POST" action="../app/Controller/auth.php" style="margin:0;">
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="btn-logout">Sign out</button>
        </form>
    </div>
</header>

<main>

    <?php if ($flashMsg): ?>
        <div class="flash <?= $flashType ?>"><?= $flashMsg ?></div>
    <?php endif; ?>

    <!-- Movie Search -->
    <div class="section-label">Add to Watchlist</div>
    <div class="search-wrap">
        <span class="search-icon">🔍</span>
        <input type="text" id="search-input" placeholder="Search for a movie…" autocomplete="off">

        <div id="search-results">
            <div class="search-status" id="search-status">Start typing to search…</div>
        </div>
    </div>

    <!-- Watchlist -->
    <div class="section-label">
        My Watchlist
        <span class="count"><?= count($watchlist) ?> film<?= count($watchlist) !== 1 ? 's' : '' ?></span>
    </div>

    <?php if (empty($watchlist)): ?>
        <div class="empty-state">
            <div class="icon">🎬</div>
            <h2>Nothing here yet</h2>
            <p>Search for a movie above and add it to your watchlist.</p>
        </div>

    <?php else: ?>
        <div class="watchlist-grid">
            <?php foreach ($watchlist as $item): ?>
                <div class="movie-card">
                    <?php if ($item['poster_url']): ?>
                        <img src="<?= htmlspecialchars($item['poster_url']) ?>" alt="<?= htmlspecialchars($item['title']) ?> poster"
                             loading="lazy">
                    <?php else: ?>
                        <div class="poster-placeholder">🎞️</div>
                    <?php endif; ?>

                    <div class="movie-card-body">
                        <div class="movie-card-title"><?= htmlspecialchars($item['title']) ?></div>

                        <!-- Remove button -->
                        <form method="POST" action="../app/Controller/watchlist.php">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="watchlist_id" value="<?= (int)$item['watchlist_ID'] ?>">
                            <button type="submit" class="btn-remove">✕ Remove</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</main>

<!-- Hidden "add" forms get injected here by JS and auto-submitted -->
<div id="form-sink" style="display:none;"></div>

<script>
//TMDB Search

//film IDs already on the watchlist
const onWatchlist = new Set([
    <?php foreach ($watchlist as $item): ?>
        "<?= htmlspecialchars($item['film_ID']) ?>",
    <?php endforeach; ?>
]);

const input = document.getElementById('search-input');
const results = document.getElementById('search-results');
const status = document.getElementById('search-status');

let debounceTimer = null;

input.addEventListener('input', () => {
    const q = input.value.trim();

    if (q.length < 2) {
        results.classList.remove('visible');
        return;
    }

    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchMovies(q), 350);
});

//close dropdown when clicking outside
document.addEventListener('click', (e) => {
    if (!e.target.closest('.search-wrap')) {
        results.classList.remove('visible');
    }
});

async function fetchMovies(query) {
    status.textContent = 'Searching…';
    results.innerHTML = '';
    results.appendChild(status);
    results.classList.add('visible');

    try {
        //calls PHP proxy so the API key never touches the browser
        const res = await fetch(`../app/Controller/tmdb_search.php?q=${encodeURIComponent(query)}`);
        const data = await res.json();

        if (data.error) {
            status.textContent = '⚠️ ' + data.error;
            return;
        }

        if (!data.results || data.results.length === 0) {
            status.textContent = 'No results found.';
            return;
        }

        results.innerHTML = '';
        data.results.forEach(movie => buildResultItem(movie));

    } catch (err) {
        status.textContent = '⚠️ Search failed: ' + err.message;
    }
}

function buildResultItem(movie) {
    const alreadyAdded = onWatchlist.has(movie.film_id);

    const item = document.createElement('div');
    item.className = 'result-item';

    const posterHTML = movie.poster_url
        ? `<img class="result-poster" src="${escHtml(movie.poster_url)}" alt="${escHtml(movie.title)}" loading="lazy">`
        : `<div class="result-poster-placeholder">🎞️</div>`;

    const metaParts = [];
    if (movie.year) metaParts.push(movie.year);
    if (movie.rating) metaParts.push('★ ' + Number(movie.rating).toFixed(1));

    item.innerHTML = `
        ${posterHTML}
        <div class="result-info">
            <div class="result-title">${escHtml(movie.title)}</div>
            <div class="result-meta">${metaParts.join(' · ')}</div>
        </div>
        <button class="btn-add"
                data-film-id="${escHtml(movie.film_id)}"
                data-title="${escHtml(movie.title)}"
                data-poster="${escHtml(movie.poster_url || '')}"
                ${alreadyAdded ? 'disabled' : ''}>
            ${alreadyAdded ? '✓ Added' : '+ Add'}
        </button>
    `;

    //wire the Add button — builds a hidden form and submits it
    const btn = item.querySelector('.btn-add');
    if (!alreadyAdded) {
        btn.addEventListener('click', () => addToWatchlist(btn));
    }

    results.appendChild(item);
}

function addToWatchlist(btn) {
    const filmID = btn.dataset.filmId;
    const title = btn.dataset.title;
    const posterURL = btn.dataset.poster;

    //update the UI
    btn.disabled = true;
    btn.textContent = '✓ Added';
    onWatchlist.add(filmID);

    //build and submit a hidden POST form to the watchlist controller
    const sink = document.getElementById('form-sink');
    sink.innerHTML = `
        <form method="POST" action="../app/Controller/watchlist.php" id="add-form">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="film_id" value="${escHtml(filmID)}">
            <input type="hidden" name="title" value="${escHtml(title)}">
            <input type="hidden" name="poster_url" value="${escHtml(posterURL)}">
        </form>
    `;
    document.getElementById('add-form').submit();
}

//basic HTML-escape for injecting API data into the DOM safely
function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}
</script>

<script>
// Header geolocation button — updates zip without a page reload.
// Reverse-geocodes via BigDataCloud
(function () {
    const geoBtn = document.getElementById('geo-btn-header');
    const zipValue = document.getElementById('zip-value');
    if (!geoBtn) return;

    geoBtn.addEventListener('click', () => {
        if (!navigator.geolocation) {
            alert('Geolocation is not supported by your browser.');
            return;
        }

        geoBtn.disabled = true;
        geoBtn.textContent = '⌛';

        navigator.geolocation.getCurrentPosition(
            async (pos) => {
                const { latitude, longitude } = pos.coords;
                try {
                    const res  = await fetch(
                        `https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${latitude}&longitude=${longitude}&localityLanguage=en`
                    );
                    const data = await res.json();
                    const zip  = (data.postcode ?? '').replace(/\s/g, '').slice(0, 10);

                    if (zip && /^\d{5}(-\d{4})?$/.test(zip)) {
                        // Persist via AJAX to the auth controller.
                        const fd = new FormData();
                        fd.append('action', 'update_zip');
                        fd.append('zip', zip);

                        const save = await fetch('../app/Controller/auth.php', {
                            method: 'POST',
                            body: fd
                        });
                        const result = await save.json();

                        if (result.success) {
                            zipValue.textContent = zip;
                            geoBtn.textContent = '✅';
                            setTimeout(() => { geoBtn.textContent = '⊙'; }, 2000);
                        } else {
                            geoBtn.textContent = '⚠️';
                            setTimeout(() => { geoBtn.textContent = '⊙'; }, 2000);
                        }
                    } else {
                        alert('Could not determine a US zip code. Please update it in Account Settings.');
                        geoBtn.textContent = '⊙';
                    }
                } catch (_) {
                    alert('Reverse geocoding failed. Please update your zip in Account Settings.');
                    geoBtn.textContent = '⊙';
                }
                geoBtn.disabled = false;
            },
            (err) => {
                const msgs = {
                    1: 'Location access denied.',
                    2: 'Position unavailable.',
                    3: 'Location request timed out.',
                };
                alert((msgs[err.code] ?? 'Geolocation error.') + ' Update your zip in Account Settings.');
                geoBtn.disabled = false;
                geoBtn.textContent = '⊙';
            },
            { timeout: 10000, maximumAge: 300000 }
        );
    });
})();
</script>

</body>
</html>