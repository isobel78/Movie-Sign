<?php
// Atlanta Daniel
// May 2026
// reset_password.php — set a new password via a one-time token link

session_start();

//check if user is already logged in
// if yes, direct to home page
if (!empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

require_once(__DIR__ . '/../app/Model/db_user.php');
require_once(__DIR__ . '/../app/View/auth_layout.php');

//grab token
$token = trim($_GET['token'] ?? '');

//validate token
$tokenValid = false;
if ($token) {
    $user = UserDB::getUserByResetToken($token);
    $tokenValid = ($user !== false);
}

ob_start();
?>

<h1>New Password</h1>

<?php if (!$tokenValid): ?>
    <div class="flash error">
        This reset link is invalid or has expired (links are good for 1 hour).
        <br>
        <a href="forgot_password.php" style="color:inherit;font-weight:600;">Request a new one →</a>
    </div>
<?php else: ?>

<p class="section-hint" style="margin-bottom:1.2rem;">
    Choose a strong password — at least 8 characters.
</p>

<form method="POST" action="../app/Controller/auth.php" novalidate>
    <input type="hidden" name="action" value="reset_password">
    <input type="hidden" name="token"  value="<?= htmlspecialchars($token) ?>">

    <div class="field">
        <label for="password">New Password</label>
        <input type="password" id="password" name="password" required
               autocomplete="new-password" placeholder="••••••••"
               minlength="8">
    </div>

    <div class="field">
        <label for="confirm">Confirm New Password</label>
        <input type="password" id="confirm" name="confirm" required
               autocomplete="new-password" placeholder="••••••••">
    </div>

    <button type="submit" class="btn-primary">🚨 Set New Password</button>
</form>

<?php endif; ?>

<p class="switch-link" style="margin-top:1.2rem;">
    Back to <a href="login.php">Sign In</a>
</p>

<?php
$content = ob_get_clean();
render_auth_page("Reset Password", $content);
?>
