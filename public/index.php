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

// MovieGlu environment switch 
// 'sandbox' uses free test data and shows "Sandbox" in the location display
// 'sandbox' allows for 10,000 req/month, fake data at lat -22.0 lng 14.0
// Change to 'us' when deploying with live US showtime data
// 'us' is limited to 75 eval requests

define('MOVIEGLU_ENV', 'sandbox');  //change to 'us' when ready for live data

$userID = (int) $_SESSION['user_id'];
$user_email = htmlspecialchars($_SESSION['user_email'] ?? '');
$user_zip = htmlspecialchars($_SESSION['user_zip'] ?? '');
$zip_display = (MOVIEGLU_ENV === 'sandbox') ? 'Sandbox' : $user_zip;

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

    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="favicon-32.png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="./styles/main.css?v=<?= filemtime(__DIR__ . '/styles/main.css') ?>"> <!-- added the 'filemtime' section to force the browser to load the latest CSS changes -->
    
</head>

<body>

<header>
    <a href="index.php" class="wordmark" title="home">🚨Movie<span>Sign</span>!</a>
    <div class="user-bar">
        <span class="user-email"><?= $user_email ?></span>
        <span class="zip-display<?= (MOVIEGLU_ENV === 'sandbox') ? ' zip-testing' : '' ?>" id="zip-display" title="<?= (MOVIEGLU_ENV === 'sandbox') ? 'Sandbox mode active' : 'Your theater search location' ?>">
            📍 <span id="zip-value"><?= $zip_display ?></span>
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

    <?php if ($flash_msg): ?>
        <div class="flash <?= $flash_type ?>"><?= htmlspecialchars($flash_msg, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <!-- Showtimes -->
    <div id="showtimes-panel">
        <div class="showtimes-idle">
            <p>Hit <strong>Check Showtimes</strong> to see which of your watchlist films are playing near you today.</p>
            <p class="showtimes-note">📍 Uses your saved zip code location.</p>
            <br />
            <div class="showtime-date-row">
                <label for="showtime-date" class="showtime-date-label">📅 Date</label>
                <input type="date" id="showtime-date" class="showtime-date-input">
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

    <br /><br />

    <!-- Movie Search -->
    <div class="section-label">Add to Watchlist</div>
    <div class="search-wrap">
        <span class="search-icon">🔍</span>
        <input type="text" id="search-input" placeholder="Search for a movie…" autocomplete="off">

        <div id="search-results">
            <div class="search-status" id="search-status">Start typing to search…</div>
        </div>
    </div>

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
    const isSandbox = <?= json_encode(MOVIEGLU_ENV === 'sandbox') ?>;

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
                            //in live mode, update the visible zip label
                            if (!isSandbox) zipValue.textContent = zip;
                            
                            //turn button green to show GPS is active
                            geoBtn.classList.add('geo-active');
                            geoBtn.textContent = '⊙';
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
//MovieGlu Showtimes
// Pulls showtimes for the selected date and cross-references them against titles on user's watchlist

(function () {
    const refreshBtn = document.getElementById('showtimes-refresh-btn');
    const panel = document.getElementById('showtimes-panel');
    const datePicker = document.getElementById('showtime-date');

    //radius filter state — default 25 miles
    let selectedRadius = 25;

    //radius button group
    function radiusHTML() {
        const opts = [
            { miles: 25, label: '25 mi' },
            { miles: 50, label: '50 mi' },
            { miles: 100, label: '100 mi' },
        ];
        const btns = opts.map(o =>
            `<button type="button" class="radius-btn${selectedRadius === o.miles ? ' radius-btn-active' : ''}" data-miles="${o.miles}">${o.label}</button>`
        ).join('');
        return `<div class="showtime-radius-row">
            <label class="showtime-radius-label">Distance</label>
            <div class="radius-btn-group">${btns}</div>
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

            const res = await fetch(`../app/Controller/movieglu_showtimes.php?${params}&env=<?= urlencode(MOVIEGLU_ENV) ?>`);
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
            ${radiusHTML()}
            <div class="showtime-date-row showtime-date-retry">
                <label for="showtime-date-error" class="showtime-date-label">📅 Try a different date</label>
                <input type="date" id="showtime-date-error" class="showtime-date-input" value="${escHtml(selectedDate)}">
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
            <div class="showtimes-empty-icon">🎭</div>
            <p>None of your watchlist films are showing near you on this date.</p>`;

        if (allShowing.length > 0) {
            msg += `<p class="showtimes-note">Currently showing nearby:<br>
                <em>${allShowing.map(escHtml).join(', ')}</em></p>`;
        }

        msg += `${radiusHTML()}
        <div class="showtime-date-row showtime-date-retry">
            <label for="showtime-date-empty" class="showtime-date-label">📅 Try a different date</label>
            <input type="date" id="showtime-date-empty" class="showtime-date-input" value="${escHtml(selectedDate)}">
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
        <div class="showtimes-grid">`;

        for (const film of showtimes) {
            const posterHTML = film.poster
                ? `<img class="st-poster" src="${escHtml(film.poster)}" alt="${escHtml(film.title)} poster" loading="lazy">`
                : `<div class="st-poster-placeholder">🎞️</div>`;

            //group times by cinema
            const byCinema = {};
            for (const t of film.times) {
                if (!byCinema[t.cinema]) byCinema[t.cinema] = [];
                byCinema[t.cinema].push(t.showtime);
            }

            let cinemasHTML = '';
            for (const [cinema, times] of Object.entries(byCinema)) {
                const timePills = times.map(t =>
                    `<span class="st-time">${escHtml(to12h(t))}</span>`
                ).join('');
                // pick the distance from the first entry for this cinema
                const distEntry = film.times.find(t => t.cinema === cinema);
                const distLabel = (distEntry && distEntry.distance != null)
                    ? ` <span class="st-distance">${parseFloat(distEntry.distance).toFixed(1)} mi</span>`
                    : '';
                cinemasHTML += `<div class="st-cinema">
                    <div class="st-cinema-name">🎬 ${escHtml(cinema)}${distLabel}</div>
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
        ${radiusHTML()}
        <div class="showtime-date-row showtime-date-retry">
            <label for="showtime-date-retry" class="showtime-date-label">📅 Try a different date</label>
            <input type="date" id="showtime-date-retry" class="showtime-date-input">
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

</body>
</html>