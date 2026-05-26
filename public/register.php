<?php
//Atlanta Daniel
// May 2026
// register.php

session_start();

//check if user is already logged in
// if yes, direct to home page
if (!empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

require_once(__DIR__ . '/../app/View/auth_layout.php');

ob_start();
?>

<h1>Create Account</h1>

<form method="POST" action="../app/Controller/auth.php" novalidate>
    <input type="hidden" name="action" value="register">

    <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required autocomplete="email" placeholder="you@example.com">
    </div>

    <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="new-password" placeholder="At least 8 characters">
        <p class="hint">Minimum 8 characters</p>
    </div>

    <div class="field">
        <label for="confirm">Confirm Password</label>
        <input type="password" id="confirm" name="confirm" required autocomplete="new-password" placeholder="Repeat your password">
    </div>

    <hr class="divider">

    <div class="field">
        <label for="zip">Zip Code</label>
        <div class="zip-row">
            <input type="text" id="zip" name="zip" autocomplete="postal-code" placeholder="e.g. 23510" maxlength="10" pattern="\d{5}(-\d{4})?">
            <button type="button" id="geo-btn" class="btn-geo" title="Use my current location">
                📍 Use My Location
            </button>
        </div>
        <input type="hidden" name="geo_zip" id="geo_zip" value="">
        <p class="hint" id="geo-status">Used to find theaters near you. Or enter your zip manually.</p>
    </div>

    <button type="submit" class="btn-primary">🚨Sound the Alarm</button>
</form>

<p class="switch-link" style="margin-top:1.2rem;">
    Already have an account? <a href="login.php">Sign in</a>
</p>

<script>
// Geolocation
(function () {
    const geoBtn = document.getElementById('geo-btn');
    const geoStatus = document.getElementById('geo-status');
    const geoZipHid = document.getElementById('geo_zip');
    const zipField  = document.getElementById('zip');
    if (!geoBtn) return;

    geoBtn.addEventListener('click', () => {
        if (!navigator.geolocation) {
            geoStatus.textContent = '⚠️ Geolocation not supported. Please enter your zip manually.';
            return;
        }
        geoBtn.disabled = true;
        geoBtn.textContent = '📡 Locating…';
        geoStatus.textContent = 'Getting your location…';

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
                        zipField.value    = zip;
                        geoZipHid.value   = zip;
                        geoStatus.textContent = `✅ Found: ${zip}`;
                    } else {
                        geoStatus.textContent = '⚠️ Could not determine zip. Please enter it manually.';
                    }
                } catch (_) {
                    geoStatus.textContent = '⚠️ Lookup failed. Please enter your zip manually.';
                }
                geoBtn.disabled = false;
                geoBtn.textContent = '📍 Use My Location';
            },
            (err) => {
                const msgs = { 1: 'Access denied.', 2: 'Position unavailable.', 3: 'Timed out.' };
                geoStatus.textContent = '⚠️ ' + (msgs[err.code] ?? 'Error.') + ' Please enter your zip manually.';
                geoBtn.disabled = false;
                geoBtn.textContent = '📍 Use My Location';
            },
            { timeout: 10000, maximumAge: 0 }
        );
    });
})();
</script>

<?php
$content = ob_get_clean();
render_auth_page("Create Account", $content);
?>
