<?php
// Atlanta Daniel
// May 2026
// login.php

session_start();

//resume from a remember-me cookie if the session has expired
require_once(__DIR__ . '/../app/Model/db_session.php');
SessionDB::resumeFromCookie();

//check if user is already logged in
// if yes, direct to home page
if (!empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

require_once(__DIR__ . '/../app/View/auth_layout.php');

ob_start();
?>

<h1>Sign In</h1>

<form method="POST" action="../app/Controller/auth.php" novalidate>
    <input type="hidden" name="action" value="login">

    <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required autocomplete="email" placeholder="you@example.com">
    </div>

    <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
    </div>

    <div class="field remember-row">
        <label class="checkbox-label">
            <input type="checkbox" name="remember" value="1">
            Keep me signed in for 30 days
        </label>
    </div>

    <button type="submit" class="btn-primary">🚨MovieSign!</button>
</form>

<p class="switch-link" style="margin-top:0.9rem;">
    <a href="forgot_password.php">Forgot your password?</a>
</p>

<p class="switch-link" style="margin-top:1.2rem;">
    No account yet? <a href="register.php">Create one</a>
</p>

<?php
$content = ob_get_clean();
render_auth_page("Sign In", $content);
?>
