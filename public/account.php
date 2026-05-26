<?php

// Atlanta Daniel
// May 2026
// account.php - User account settings: edit email, password, and location

session_start();

//look for remember-me cookie if session is empty
require_once(__DIR__ . '/../app/Model/db_session.php');
if (!SessionDB::resumeFromCookie()) {
    if (empty($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

require_once(__DIR__ . '/../app/View/auth_layout.php');

$userID = (int) $_SESSION['user_id'];
$userEmail  = htmlspecialchars($_SESSION['user_email'] ?? '');
$userZip = htmlspecialchars($_SESSION['user_zip']   ?? '');

//flash message
$flash_type = '';
$flash_msg  = '';
if (!empty($_SESSION['flash_message'])) {
    $flash_type = htmlspecialchars($_SESSION['flash_type'] ?? 'success');
    $flash_msg = htmlspecialchars($_SESSION['flash_message']);
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

ob_start();
?>

<h1>Account Settings</h1>

<?php if ($flash_msg): ?>
    <div class="flash <?= $flash_type ?>"><?= $flash_msg ?></div>
<?php endif; ?>

<form method="POST" action="../app/Controller/auth.php" novalidate id="account-form">
    <input type="hidden" name="action" value="update_account">
    
    <!-- hidden field populated by geolocation JS -->
    <input type="hidden" name="geo_zip" id="geo_zip" value="">

    <!-- Email -->
    <div class="field">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" value="<?= $userEmail ?>" required autocomplete="email">
    </div>

    <hr class="divider">

    <!-- Password  -->
    <p class="section-hint">Leave the password fields blank to keep your current password.</p>

    <div class="field">
        <label for="current_password">Current Password</label>
        <input type="password" id="current_password" name="current_password" autocomplete="current-password" placeholder="Required to change password">
    </div>

    <div class="field">
        <label for="new_password">New Password</label>
        <input type="password" id="new_password" name="new_password" autocomplete="new-password" placeholder="At least 8 characters">
        <p class="hint">Changing your password will sign you out on other devices.</p>
    </div>

    <div class="field">
        <label for="confirm_password">Confirm New Password</label>
        <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" placeholder="Repeat new password">
    </div>

    <hr class="divider">

    <!-- Location — zip code or geolocation -->
    <div class="field">
        <label for="zip">Zip Code</label>
        <div class="zip-row">
            <input type="text" id="zip" name="zip" value="<?= $userZip ?>" placeholder="e.g. 23510" maxlength="10" pattern="\d{5}(-\d{4})?">
            <button type="button" id="geo-btn" class="btn-geo" title="Use my current location">
                📍 Use My Location
            </button>
        </div>
        <p class="hint" id="geo-status">Used to find theaters near you.</p>
    </div>

    <button type="submit" class="btn-primary">💾 Save Changes</button>
</form>

<p class="switch-link" style="margin-top:1.4rem;">
    <a href="index.php">← Back to my watchlist</a>
</p>

<script>
// Geolocation → zip code via reverse geocoding (BigDataCloud)
const geoBtn = document.getElementById('geo-btn');
const geoStatus = document.getElementById('geo-status');
const geoZipField = document.getElementById('geo_zip');
const zipField  = document.getElementById('zip');

geoBtn.addEventListener('click', () => {
    if (!navigator.geolocation) {
        geoStatus.textContent = '⚠️ Geolocation is not supported by your browser.';
        return;
    }

    geoBtn.disabled = true;
    geoBtn.textContent = '📡 Locating…';
    geoStatus.textContent = 'Getting your location…';

    navigator.geolocation.getCurrentPosition(
        async (pos) => {
            const { latitude, longitude } = pos.coords;
            try {
                // Free reverse-geocoder: returns postal code for US coordinates.
                const res  = await fetch(
                    `https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${latitude}&longitude=${longitude}&localityLanguage=en`
                );
                const data = await res.json();
                const zip  = data.postcode?.replace(/\s/g, '').slice(0, 10) ?? '';

                if (zip && /^\d{5}(-\d{4})?$/.test(zip)) {
                    zipField.value    = zip;
                    geoZipField.value = zip;
                    geoStatus.textContent = `✅ Location found: ${zip}`;
                } else {
                    geoStatus.textContent = '⚠️ Could not determine a US zip code from your location. Please enter it manually.';
                }
            } catch (err) {
                geoStatus.textContent = '⚠️ Reverse geocoding failed. Please enter your zip manually.';
            }
            geoBtn.disabled = false;
            geoBtn.textContent = '📍 Use My Location';
        },
        (err) => {
            const msgs = {
                1: 'Location access was denied. Please allow location access or enter your zip manually.',
                2: 'Your position could not be determined.',
                3: 'Location request timed out.',
            };
            geoStatus.textContent = '⚠️ ' + (msgs[err.code] ?? 'Unknown geolocation error.');
            geoBtn.disabled = false;
            geoBtn.textContent = '📍 Use My Location';
        },
        { timeout: 10000, maximumAge: 300000 }
    );
});
</script>

<?php
$content = ob_get_clean();
render_auth_page("Account Settings", $content);
?>