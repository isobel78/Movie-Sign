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
        <input type="text" id="zip" name="zip" required autocomplete="postal-code" placeholder="e.g. 23510" maxlength="10" pattern="\d{5}(-\d{4})?">
        <p class="hint">Used to find theaters near you</p>
    </div>

    <button type="submit" class="btn-primary">🚨Sound the Alarm</button>
</form>

<p class="switch-link" style="margin-top:1.2rem;">
    Already have an account? <a href="login.php">Sign in</a>
</p>

<?php
$content = ob_get_clean();
render_auth_page("Create Account", $content);
?>
