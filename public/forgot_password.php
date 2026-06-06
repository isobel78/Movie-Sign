<?php
// Atlanta Daniel
// May 2026
// forgot_password.php

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

<h1>Reset Password</h1>

<p class="section-hint" style="margin-bottom:1.2rem;">
    Enter the email address on your account and we'll send you a one-time reset link.
</p>

<form method="POST" action="../app/Controller/auth.php" novalidate>
    <input type="hidden" name="action" value="forgot_password">

    <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required
               autocomplete="email" placeholder="you@example.com">
    </div>

    <button type="submit" class="btn-primary">Send Reset Link</button>
</form>

<p class="switch-link" style="margin-top:1.2rem;">
    Remembered it? <a href="login.php">Sign In</a>
</p>

<?php
$content = ob_get_clean();
render_auth_page("Forgot Password", $content);
?>
