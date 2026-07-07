<?php
// Atlanta Daniel
// June 2026 -- switched from MovieGlu to Gracenote for showtime data
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
require_once(__DIR__ . '/../app/Model/db_user.php');

$userID = (int) $_SESSION['user_id'];
$user_email = htmlspecialchars($_SESSION['user_email'] ?? '');
$user_zip = htmlspecialchars($_SESSION['user_zip'] ?? '');
$zip_display = $user_zip;

//load default radius
if (!isset($_SESSION['user_default_radius'])) {
    $dbUserRow = UserDB::getUser($userID);
    $_SESSION['user_default_radius'] = (int)($dbUserRow['default_radius'] ?? 10);
}
$user_default_radius = (int)$_SESSION['user_default_radius'];

//load user's watchlist from DB
$watchlist = WatchlistDB::getWatchlist($userID);

//flash message
$flash_type = '';
$flash_msg = '';
if (!empty($_SESSION['flash_message'])) {
    $flash_type = htmlspecialchars($_SESSION['flash_type'] ?? 'success');
    $flash_msg = $_SESSION['flash_message'];
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MovieSign!</title>

    <link rel="icon" type="image/x-icon" href="icons/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="icons/favicon-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="icons/favicon-180.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="./styles/main.css?v=<?= filemtime(__DIR__ . '/styles/main.css') ?>"> <!-- added the 'filemtime' section to force the browser to load the latest CSS changes -->

    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#e63946">
    
    <meta property="og:title" content="MovieSign!" />
    <meta property="og:description" content="Create a watchlist and find out when your movies are showing nearby." />
    <meta property="og:image" content="https://moviesign.atlantadaniel.com/public/icons/og-image.png" />
    <meta property="og:url" content="https://moviesign.atlantadaniel.com/" />
    <meta property="og:type" content="website" />
    
</head>

<body>

<header>
    <a href="index.php" class="wordmark" title="home">🚨Movie<span>Sign</span>!</a>

    <!-- Desktop user-bar (hidden on mobile) -->
    <div class="user-bar user-bar-desktop">
        <span class="user-email"><?= $user_email ?></span>
        <span class="zip-display" id="zip-display" title="Your theater search location">
            <span id="zip-value"><?= $zip_display ?></span>
        </span>
        <button type="button" id="geo-btn-header" class="btn-geo-sm" title="Update location from GPS">⊙</button>
        <a href="account.php" class="btn-account">⚙ Account</a>
        <form method="POST" action="../app/Controller/auth.php" style="margin:0;">
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="btn-logout">Sign out</button>
        </form>
    </div>

    <!-- Zip code display (mobile only — tap to refresh location) -->
    <button type="button" class="header-zip-pill" id="header-zip-pill" title="Tap to update location">
        <span id="zip-value-mobile-pill"><?= $zip_display ?></span>
    </button>

    <!-- Hamburger button (mobile only) -->
    <button class="hamburger" id="hamburger-btn" aria-label="Open menu" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>
</header>

<!-- Mobile nav drawer -->
<nav class="mobile-nav" id="mobile-nav" aria-hidden="true">
    <div class="mobile-nav-inner">
        <div class="mobile-nav-user">
            <span class="user-email"><?= $user_email ?></span>
            <span class="zip-display" id="zip-display-mobile" title="Your theater search location">
                <span id="zip-value-mobile"><?= $zip_display ?></span>
            </span>
            <button type="button" id="geo-btn-mobile" class="btn-geo-sm" title="Update location from GPS">⊙</button>
        </div>
        <a href="account.php" class="mobile-nav-link">⚙ Account</a>
        <form method="POST" action="../app/Controller/auth.php" style="margin:0;">
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="mobile-nav-link mobile-nav-signout">Sign out</button>
        </form>
    </div>
</nav>
<div class="mobile-nav-overlay" id="mobile-nav-overlay"></div>

<main>

    <?php if ($flash_msg): ?>
        <div class="flash <?= $flash_type ?>"><?= htmlspecialchars($flash_msg, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <!-- Showtimes -->
    <div id="showtimes-panel">
        <div class="showtimes-idle">
            <p>Hit <strong>Check Showtimes</strong> to see which of your watchlist films are playing near you today.</p>
            <br />
            <div class="showtime-date-row">
                <label for="showtime-date" class="showtime-date-label">Date</label>
                <input type="date" id="showtime-date" class="showtime-date-input" autocomplete="off">
            </div>
            <div class="showtime-radius-row" id="radius-row-idle">
            </div>
            <br />
            <button type="button" id="showtimes-refresh-btn" class="btn-showtimes-refresh" title="Check showtimes near you">
                Check Showtimes
            </button>
        </div>
    </div>

    <br /><hr /><br />

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
                <div class="movie-card film-card-trigger" data-film-id="<?= htmlspecialchars($item['film_ID']) ?>" data-film-title="<?= htmlspecialchars($item['title']) ?>" tabindex="0" role="button" aria-haspopup="dialog">
                    <?php if ($item['poster_url']): ?>
                        <img src="<?= htmlspecialchars($item['poster_url']) ?>" alt="<?= htmlspecialchars($item['title']) ?> poster"
                             loading="lazy">
                    <?php else: ?>
                        <div class="poster-placeholder">🎞️</div>
                    <?php endif; ?>

                    <div class="movie-card-body">
                        <div class="movie-card-title"><?= htmlspecialchars($item['title']) ?></div>

                        <!-- Remove button -->
                        <form method="POST" action="../app/Controller/watchlist.php" class="remove-form" onclick="event.stopPropagation()">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="watchlist_id" value="<?= (int)$item['watchlist_ID'] ?>">
                            <button type="submit" class="btn-remove">✕ Remove</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <br /><br />

</main>

<!-- Film detail overlay -->
<div class="film-overlay-backdrop" id="film-overlay-backdrop"></div>
<div class="film-overlay" id="film-overlay" role="dialog" aria-modal="true" aria-hidden="true" aria-label="Film details">
    <button type="button" class="film-overlay-close" id="film-overlay-close" aria-label="Close">✕</button>
    <div class="film-overlay-body" id="film-overlay-body">
        <!-- populated by JS -->
    </div>
</div>

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
        geoBtn.classList.remove('geo-active');

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
                            const mobileZip = document.getElementById('zip-value-mobile');
                            if (mobileZip) mobileZip.textContent = zip;
                            const pillZip = document.getElementById('zip-value-mobile-pill');
                            if (pillZip) pillZip.textContent = zip;
                            //turn both geo buttons green to show GPS is active
                            geoBtn.classList.add('geo-active');
                            geoBtn.textContent = '⊙';
                            const mobileGeo = document.getElementById('geo-btn-mobile');
                            if (mobileGeo) mobileGeo.classList.add('geo-active');

                            document.getElementById('zip-display')?.classList.add('zip-active');
                            document.getElementById('zip-display-mobile')?.classList.add('zip-active');
                            document.getElementById('header-zip-pill')?.classList.add('zip-active');
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

<script>
//Showtimes - pulls showtimes for the selected date and cross-references them against titles on user's watchlist

(function () {
    const refreshBtn = document.getElementById('showtimes-refresh-btn');
    const panel = document.getElementById('showtimes-panel');
    const datePicker = document.getElementById('showtime-date');

    //radius filter state — loaded from user's saved default
    let selectedRadius = <?= (int)$user_default_radius ?>;

    //radius button group
    function radiusHTML() {
        const opts = [
            { miles: 10, label: '10 mi' },
            { miles: 25, label: '25 mi' },
            { miles: 50, label: '50 mi' },
            { miles: 100, label: '100 mi' },
        ];
        const btns = opts.map(o =>
            `<button type="button" class="radius-btn${selectedRadius === o.miles ? ' radius-btn-active' : ''}" data-miles="${o.miles}">${o.label}</button>`
        ).join('');
        return `<div class="showtime-radius-row">
            <span class="showtime-radius-label">Distance</span>
            <div class="radius-btn-group">${btns}</div>
            <!-- <p class="showtime-radius-note">Search theaters within ${selectedRadius} miles of your zip code.</p> -->
        </div>`;
    }

    // Wire up radius buttons inside any container
    function bindRadiusBtns(container) {
        container.querySelectorAll('.radius-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                selectedRadius = parseInt(btn.dataset.miles, 10);
                // Re-highlight across whatever container they live in
                btn.closest('.radius-btn-group').querySelectorAll('.radius-btn').forEach(b => b.classList.remove('radius-btn-active'));
                btn.classList.add('radius-btn-active');
            });
        });
    }

    // Wire the initial (idle) radius buttons on page load
    bindRadiusBtns(panel);

    // Inject radius buttons into the idle state
    const idleRadiusRow = document.getElementById('radius-row-idle');
    if (idleRadiusRow) {
        idleRadiusRow.outerHTML = radiusHTML();
        bindRadiusBtns(panel);
    }

    //default to today in the user's local timezone (not UTC)
    const todayLocal = new Date();
    const yyyy = todayLocal.getFullYear();
    const mm = String(todayLocal.getMonth() + 1).padStart(2, '0');
    const dd = String(todayLocal.getDate()).padStart(2, '0');
    if (datePicker) datePicker.value = `${yyyy}-${mm}-${dd}`;

    let selectedDate = `${yyyy}-${mm}-${dd}`;

    //collect watchlist titles
    const watchlistTitles = [
        <?php foreach ($watchlist as $item): ?>
            <?= json_encode($item['title']) ?>,
        <?php endforeach; ?>
    ];

    if (!refreshBtn) return;

    refreshBtn.addEventListener('click', async () => {
        if (watchlistTitles.length === 0) {
            panel.innerHTML = `<div class="showtimes-empty">
                <p>Add some films to your watchlist first, then check for showtimes.</p>
            </div>`;
            return;
        }

        window.scrollTo({ top: 0, behavior: 'smooth' });
        setLoading(true);
        setTimeout(() => window.scrollTo({ top: 0, behavior: 'smooth' }), 100);

        try {
            const tz = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
            selectedDate = datePicker ? datePicker.value : `${yyyy}-${mm}-${dd}`;
            const params = new URLSearchParams({
                titles: JSON.stringify(watchlistTitles),
                tz,
                date: selectedDate,
                radius: selectedRadius,
            });

            const res = await fetch(`../app/Controller/gracenote_showtimes.php?${params}`);
            const data = await res.json();

            if (data.error) {
                showError(data.error);
                return;
            }

            renderShowtimes(data.showtimes ?? [], data.all_showing ?? []);
        } catch (err) {
            showError('Could not load showtimes: ' + err.message);
        } finally {
            setLoading(false);
        }
    });

    //convert "HH:MM" 24-hour string to "H:MM AM/PM"
    function to12h(time24) {
        if (!time24 || time24.includes(' ')) return time24; //already formatted
        const [hStr, mStr] = time24.split(':');
        if (!hStr || !mStr) return time24;
        let h = parseInt(hStr, 10);
        const m = mStr;
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return `${h}:${m} ${ampm}`;
    }

    // Render helpers
    function setLoading(on) {
        refreshBtn.disabled = on;
        refreshBtn.textContent = on ? '⌛ Checking…' : 'Check Showtimes';
        if (on) {
            panel.innerHTML = `<div class="showtimes-loading">
                <div class="showtimes-spinner"></div>
                <p>Scanning theaters near you…</p>
            </div>`;
        }
    }

    function showError(msg) {
        panel.innerHTML = `<div class="showtimes-error">
            <span class="showtimes-error-icon">⚠️</span>
            <p>${escHtml(msg)}</p>
            <p class="showtimes-note">Check that your location is set and try again.</p>
            <div style="margin-top:1.5rem; padding-top:1.25rem; border-top:1px solid rgba(255,255,255,0.1); text-align:center;">
                <div class="showtime-date-row">
                    <label for="showtime-date-error" class="showtime-date-label">Date</label>
                    <input type="date" id="showtime-date-error" class="showtime-date-input" value="${escHtml(selectedDate)}" autocomplete="off">
                </div>
                ${radiusHTML()}
                <br />
                <button type="button" class="btn-showtimes-refresh btn-showtimes-retry" id="error-retry-btn">Check Showtimes</button>
            </div>
        </div>`;
        bindRadiusBtns(panel);
        document.getElementById('error-retry-btn').addEventListener('click', () => {
            const errorPicker = document.getElementById('showtime-date-error');
            if (errorPicker && datePicker) datePicker.value = errorPicker.value;
            refreshBtn.click();
        });
    }

    function renderShowtimes(showtimes, allShowing) {
    if (showtimes.length === 0) {
        
        let msg = `<div class="showtimes-empty">
            <p><strong>None of your watchlist films are showing near you on this date.</strong></p>`;

        if (allShowing.length > 0) {
            msg += `<p class="showtimes-note"><strong>Titles showing nearby:</strong><br>
                <em>${allShowing.map(escHtml).join(', ')}</em></p><br>`;
        }

        msg += `<a href="streaming_check.php" class="lever-link" onclick="">In case of Emergency, Pull the Lever!</a>`;

        msg += `<div style="margin-top:1.5rem; padding-top:1.25rem; border-top:1px solid rgba(255,255,255,0.1); text-align:center;">
            <div class="showtimes-empty">
            <p><strong>Or try a different date.</strong></p>
            <div class="showtime-date-row">
                <label for="showtime-date-empty" class="showtime-date-label">Date</label>
                <input type="date" id="showtime-date-empty" class="showtime-date-input" value="${escHtml(selectedDate)}" autocomplete="off">
            </div>
            ${radiusHTML()}
            <br />
            <button type="button" class="btn-showtimes-refresh btn-showtimes-retry" id="empty-retry-btn">Check Showtimes</button>
        </div>
        </div>`;

        panel.innerHTML = msg;
        bindRadiusBtns(panel);

        document.getElementById('empty-retry-btn').addEventListener('click', () => {
            const emptyPicker = document.getElementById('showtime-date-empty');
            if (emptyPicker && datePicker) datePicker.value = emptyPicker.value;
            refreshBtn.click();
        });

        return;
    }

        //if there are showtime matches — MOVIE SIGN!
        let html = `<div class="moviesign-alert">
            <span class="moviesign-siren">🚨</span>
            <strong>WE'VE GOT MOVIE SIGN!</strong>
            <span class="moviesign-siren">🚨</span>
        </div>
        <p class="showtime-radius-note" style="text-align:center; margin:0.4rem 0 0.75rem;">Showing theaters within ${selectedRadius} miles of your zip code.</p>
        <div class="showtimes-grid">`;

        for (const film of showtimes) {
            const posterHTML = film.poster
                ? `<img class="st-poster" src="${escHtml(film.poster)}" alt="${escHtml(film.title)} poster" loading="lazy">`
                : `<div class="st-poster-placeholder">🎞️</div>`;

            //group times by cinema
            const byCinema = {};
            const cinemaDistance = {};
            for (const t of film.times) {
                if (!byCinema[t.cinema]) {
                    byCinema[t.cinema] = [];
                    cinemaDistance[t.cinema] = t.distance ?? null;
                }
                byCinema[t.cinema].push(t.showtime);
            }

            let cinemasHTML = '';
            for (const [cinema, times] of Object.entries(byCinema)) {
                const timePills = times.map(t =>
                    `<span class="st-time">${escHtml(to12h(t))}</span>`
                ).join('');

                const distStr = (cinemaDistance[cinema] !== null && cinemaDistance[cinema] !== undefined)
                    ? ` <span class="st-distance">${cinemaDistance[cinema]} mi</span>` : '';

                cinemasHTML += `<div class="st-cinema">
                    <div class="st-cinema-name">🎬 ${escHtml(cinema)}${distStr}</div>
                    <div class="st-times">${timePills}</div>
                </div>`;
            }

            html += `<div class="st-card">
                ${posterHTML}
                <div class="st-body">
                    <div class="st-title">${escHtml(film.title)}</div>
                    ${cinemasHTML}
                </div>
            </div>`;
        }

        html += `</div>
        <div style="margin-top:1.5rem; padding-top:1.25rem; border-top:1px solid rgba(255,255,255,0.1); text-align:center;">
            <div class="showtime-date-row">
                <label for="showtime-date-retry" class="showtime-date-label">Date</label>
                <input type="date" id="showtime-date-retry" class="showtime-date-input" autocomplete="off">
            </div>
            ${radiusHTML()}
            <br />
            <button type="button" class="btn-showtimes-refresh btn-showtimes-retry">Check Showtimes</button>
        </div>`;
        panel.innerHTML = html;
        bindRadiusBtns(panel);

        //try a different date
        const retryPicker = document.getElementById('showtime-date-retry');
        if (retryPicker) retryPicker.value = selectedDate;
        document.querySelector('.btn-showtimes-retry').addEventListener('click', () => {
            if (retryPicker && datePicker) datePicker.value = retryPicker.value;
            refreshBtn.click();
        });
    }

})();
</script>

<script>
// Hamburger menu
(function () {
    const btn = document.getElementById('hamburger-btn');
    const nav = document.getElementById('mobile-nav');
    const overlay = document.getElementById('mobile-nav-overlay');

    function openMenu() {
        nav.classList.add('open');
        overlay.classList.add('open');
        btn.classList.add('open');
        btn.setAttribute('aria-expanded', 'true');
        nav.setAttribute('aria-hidden', 'false');
    }
    function closeMenu() {
        nav.classList.remove('open');
        overlay.classList.remove('open');
        btn.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
        nav.setAttribute('aria-hidden', 'true');
    }

    btn.addEventListener('click', () => {
        btn.classList.contains('open') ? closeMenu() : openMenu();
    });
    overlay.addEventListener('click', closeMenu);

    // Mobile geo button mirrors the desktop geo button click
    const mobileGeoBtn = document.getElementById('geo-btn-mobile');
    const desktopGeoBtn = document.getElementById('geo-btn-header');
    if (mobileGeoBtn && desktopGeoBtn) {
        mobileGeoBtn.addEventListener('click', () => {
            closeMenu();
            desktopGeoBtn.click();
        });
    }

    // Mobile header zip pill — tap to refresh location (no menu needed)
    const headerZipPill = document.getElementById('header-zip-pill');
    if (headerZipPill && desktopGeoBtn) {
        headerZipPill.addEventListener('click', () => {
            desktopGeoBtn.click();
        });
    }
})();
</script>

<script>
// Film detail overlay
(function () {
    const backdrop = document.getElementById('film-overlay-backdrop');
    const overlay = document.getElementById('film-overlay');
    const body = document.getElementById('film-overlay-body');
    const closeBtn = document.getElementById('film-overlay-close');

    const cache = {}; // film_id -> parsed detail JSON, avoid refetching on repeat opens

    function escHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    function formatReleaseDate(iso) {
        if (!iso) return '';
        const [y, m, d] = iso.split('-').map(Number);
        const date = new Date(y, m - 1, d); // local-time constructor, not new Date(iso)
        return date.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
    }

    function isFutureDate(iso) {
        if (!iso) return false;
        const [y, m, d] = iso.split('-').map(Number);
        const date = new Date(y, m - 1, d);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        return date.getTime() > today.getTime();
    }

    function openOverlay() {
        backdrop.classList.add('open');
        overlay.classList.add('open');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('film-overlay-lock');
    }

    function closeOverlay() {
        backdrop.classList.remove('open');
        overlay.classList.remove('open');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('film-overlay-lock');
    }

    function renderLoading(title) {
        body.innerHTML = `
            <div class="film-overlay-loading">
                <div class="showtimes-spinner"></div>
                <p>Loading ${escHtml(title)}…</p>
            </div>`;
    }

    function renderError(msg) {
        body.innerHTML = `
            <div class="film-overlay-error">
                <span class="showtimes-error-icon">⚠️</span>
                <p>${escHtml(msg)}</p>
            </div>`;
    }

    function renderDetail(d) {
        const runtimeHTML = d.runtime ? `<span class="chip">${d.runtime} min</span>` : '';
        const ratingHTML = d.rating ? `<span class="chip">★ ${Math.round(d.rating * 10) / 10}/10 (${d.vote_count})</span>` : '';
        const release_dateHTML = isFutureDate(d.release_date)
            ? `<span class="chip">Opens ${escHtml(formatReleaseDate(d.release_date))}</span>` : '';
        const directorHTML = d.director
            ? `<div class="film-overlay-director">Directed by <strong>${escHtml(d.director)}</strong></div>` : '';
        const posterHTML = d.poster_url
            ? `<img class="film-overlay-poster" src="${escHtml(d.poster_url)}" alt="${escHtml(d.title)} poster">`
            : `<div class="film-overlay-poster poster-placeholder">🎞️</div>`;

        let castHTML = '';
        if (d.cast && d.cast.length) {
            castHTML = `
                <div class="section-label" style="margin-top:1.25rem;">Top Billed Cast</div>
                <div class="cast-grid">
                    ${d.cast.map(p => `
                        <div class="cast-card">
                            ${p.photo_url
                                ? `<img class="cast-photo" src="${escHtml(p.photo_url)}" alt="${escHtml(p.name)}">`
                                : `<div class="cast-photo"></div>`}
                            <div class="cast-name">${escHtml(p.name)}</div>
                            <div class="cast-role">${escHtml(p.character)}</div>
                        </div>`).join('')}
                </div>`;
        }

        body.innerHTML = `
            <div class="film-overlay-hero">
                ${posterHTML}
                <div class="film-overlay-info">
                    <div class="film-overlay-title">${escHtml(d.title)}${d.year ? ` (${escHtml(d.year)})` : ''}</div>
                    ${d.tagline ? `<div class="film-overlay-tagline">"${escHtml(d.tagline)}"</div>` : ''}
                    <div class="chip-row">${release_dateHTML}${runtimeHTML}${ratingHTML}</div>
                    ${directorHTML}
                    <div class="film-overlay-overview">${escHtml(d.overview || '')}</div>
                </div>
            </div>
            ${castHTML}
        `;
    }

    async function loadFilm(filmId, title) {
        if (cache[filmId]) {
            renderDetail(cache[filmId]);
            return;
        }

        renderLoading(title);

        try {
            const res = await fetch(`../app/Controller/film_details.php?film_id=${encodeURIComponent(filmId)}`);
            const data = await res.json();
            if (!res.ok || data.error) {
                renderError(data.error || 'Could not load film details.');
                return;
            }
            cache[filmId] = data;
            renderDetail(data);
        } catch (e) {
            renderError('Something went wrong reaching the server.');
        }
    }

    // Opening pushes a history entry so the device/browser back button closes
    // the overlay instead of leaving index.php. Closing via UI (✕, backdrop,
    // Escape) calls history.back() when we own the top entry, so back/forward
    // stays consistent either way the overlay gets closed.
    function openFilmOverlay(filmId, title) {
        const state = { filmOverlay: true, filmId };
        if (overlay.classList.contains('open')) {
            history.replaceState(state, '', `#film-${filmId}`);
        } else {
            history.pushState(state, '', `#film-${filmId}`);
        }
        openOverlay();
        loadFilm(filmId, title);
    }

    function requestClose() {
        if (history.state && history.state.filmOverlay) {
            history.back(); // triggers popstate below, which closes the overlay
        } else {
            closeOverlay();
        }
    }

    window.addEventListener('popstate', (e) => {
        if (!e.state || !e.state.filmOverlay) {
            closeOverlay();
        }
    });

    document.querySelectorAll('.film-card-trigger').forEach(card => {
        card.addEventListener('click', () => {
            openFilmOverlay(card.dataset.filmId, card.dataset.filmTitle);
        });
        card.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openFilmOverlay(card.dataset.filmId, card.dataset.filmTitle);
            }
        });
    });

    closeBtn.addEventListener('click', requestClose);
    backdrop.addEventListener('click', requestClose);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && overlay.classList.contains('open')) requestClose();
    });
})();
</script>
</body>
</html>