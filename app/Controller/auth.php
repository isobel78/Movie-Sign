 <?php
// Atlanta Daniel
// May 2026
// auth.php — Controller to handle all POST actions: registration, login, logout, and account edits

session_start();

require_once(__DIR__ . '/../Model/db_user.php');
require_once(__DIR__ . '/../Model/db_session.php');

//Helper function: 
//redirect with a flash message stored in session
function redirect_with_msg($url, $type, $message) {
    $_SESSION['flash_type'] = $type; // 'error' or 'success'
    $_SESSION['flash_message'] = $message;
    header("Location: $url");
    exit;
}

//Helper function:
//validate password complexity — returns an error string or null on success
function validate_password($password) {
    if (strlen($password) < 10) {
        return "Password must be at least 10 characters.";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return "Password must contain at least one uppercase letter.";
    }
    if (!preg_match('/[a-z]/', $password)) {
        return "Password must contain at least one lowercase letter.";
    }
    if (!preg_match('/[0-9]/', $password)) {
        return "Password must contain at least one number.";
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return "Password must contain at least one special character (e.g. !@#\$%).";
    }
    return null;
}

//check action query param
$action = $_POST['action'] ?? '';

// REGISTER
if ($action === 'register') {

    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm']  ?? '');
    $zip = trim($_POST['zip'] ?? '');

    //server-side validation
    $errors = [];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    $pwError = validate_password($password);
    if ($pwError !== null) {
        $errors[] = $pwError;
    }

    if ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    }

    //zip code optional if users chooses geolocation
    if ($zip !== '' && !preg_match('/^\d{5}(-\d{4})?$/', $zip)) {
        $errors[] = "Please enter a valid US zip code (e.g., 23510).";
    }
    if ($zip === '') {
        //store a placeholder in the DB
        //user can update it from account settings
        $zip = '00000';
    }

    if (!empty($errors)) {
        redirect_with_msg('../../public/register.php', 'error', implode(' ', $errors));
    }

    if (UserDB::emailExists($email)) {
        redirect_with_msg('../../public/register.php', 'error', "That email is already registered. Try logging in.");
    }

    $pw_hash = password_hash($password, PASSWORD_BCRYPT);

    if (UserDB::addUser($email, $pw_hash, $zip)) {
        //log them in right away
        $user = UserDB::getUserByEmail($email);

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['user_ID'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_zip'] = $user['zip_code'];

        //start a server-side session token
        SessionDB::pruneExpired();
        SessionDB::createSession((int) $user['user_ID'], false);
        
        redirect_with_msg('../../public/index.php', 'success', "Account created — welcome aboard.");
    } else {
        redirect_with_msg('../../public/register.php', 'error', "Registration failed. Please try again.");
    }
}

// LOGIN
elseif ($action === 'login') {

    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $remember = !empty($_POST['remember']); //"Remember me" checkbox

    if (empty($email) || empty($password)) {
        redirect_with_msg('../../public/login.php', 'error', "Please fill in both fields.");
    }

    $user = UserDB::getUserByEmail($email);

    if (!$user || !password_verify($password, $user['pw_hash'])) {
        redirect_with_msg('../../public/login.php', 'error', "Invalid email or password.");
    }

    //successful login
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['user_ID'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_zip'] = $user['zip_code'];

    //create server-side token
    SessionDB::pruneExpired();
    SessionDB::createSession((int) $user['user_ID'], $remember);

    redirect_with_msg('../../public/index.php', 'success', "Welcome back.");
}

// LOGOUT
elseif ($action === 'logout') {
    //invalidate the server-side remember-me token if one exists
    $token = $_COOKIE[SessionDB::COOKIE_NAME] ?? '';
    if ($token) {
        SessionDB::deleteToken($token);
        SessionDB::clearRememberCookie();
    }

    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: ../../public/login.php");
    exit;
}

// UPDATE ACCOUNT
elseif ($action === 'update_account') {

    //user must be logged in
    if (empty($_SESSION['user_id'])) {
        redirect_with_msg('../../public/login.php', 'error', "Please sign in first.");
    }

    $userID = (int) $_SESSION['user_id'];

    //get user's current info
    $current = UserDB::getUser($userID);

    if (!$current) {
        redirect_with_msg('../../public/account.php', 'error', "Could not load your account. Please try again.");
    }

    //update EMAIL
    $newEmail = trim($_POST['email'] ?? '');

    if (empty($newEmail) || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        redirect_with_msg('../../public/account.php', 'error', "Please enter a valid email address.");
    }

    //check that email doesn't already exist in DB
    if ($newEmail !== $current['email'] && UserDB::emailExists($newEmail)) {
        redirect_with_msg('../../public/account.php', 'error', "That email is already in use by another account.");
    }

    //update PASSWORD
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPw = $_POST['confirm_password'] ?? '';
    $currentPw = $_POST['current_password'] ?? '';

    $newHash = $current['pw_hash'];

    if ($newPassword !== '') {
        //require the current password to authorize a change.
        if (!password_verify($currentPw, $current['pw_hash'])) {
            redirect_with_msg('../../public/account.php', 'error', "Current password is incorrect.");
        }
        $pwError = validate_password($newPassword);
        if ($pwError !== null) {
            redirect_with_msg('../../public/account.php', 'error', $pwError);
        }
        if ($newPassword !== $confirmPw) {
            redirect_with_msg('../../public/account.php', 'error', "New passwords do not match.");
        }
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);

        //when setting new password, invalidate all other sessions
        SessionDB::deleteAllForUser($userID);

        //create a fresh token for this session
        SessionDB::createSession($userID, false);
    }

    // ZIPCODE / GEOLOCATION
    $geoZip = trim($_POST['geo_zip'] ?? '');
    $typedZip = trim($_POST['zip']    ?? '');
    $newZip = $geoZip !== '' ? $geoZip : $typedZip;

    if ($newZip !== '' && !preg_match('/^\d{5}(-\d{4})?$/', $newZip)) {
        redirect_with_msg('../../public/account.php', 'error', "Please enter a valid US zip code (e.g., 23510).");
    }

    if ($newZip === '') {
        $newZip = $current['zip_code'];
    }

    if (UserDB::updateUser($userID, $newEmail, $newHash, $newZip)) {
        $_SESSION['user_email'] = $newEmail;
        $_SESSION['user_zip']   = $newZip;
        redirect_with_msg('../../public/account.php', 'success', "Account updated successfully.");
    } else {
        redirect_with_msg('../../public/account.php', 'error', "Update failed. Please try again.");
    }
}

// UPDATE ZIP ONLY
// used by the geolocation button on index.php
elseif ($action === 'update_zip') {

    header('Content-Type: application/json');

    if (empty($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
        exit;
    }

    $userID = (int) $_SESSION['user_id'];
    $zip = trim($_POST['zip'] ?? '');

    if (!preg_match('/^\d{5}(-\d{4})?$/', $zip)) {
        echo json_encode(['success' => false, 'message' => 'Invalid zip code.']);
        exit;
    }

    $current = UserDB::getUser($userID);
    if (!$current) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    if (UserDB::updateUser($userID, $current['email'], $current['pw_hash'], $zip)) {
        $_SESSION['user_zip'] = $zip;
        echo json_encode(['success' => true, 'zip' => $zip, 'message' => 'Location updated.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database update failed.']);
    }
    exit;
}

// FORGOT PASSWORD
//send a reset link
elseif ($action === 'forgot_password') {

    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirect_with_msg('../../public/forgot_password.php', 'error', 'Please enter a valid email address.');
    }

    // Generate a secure token (hex, 64 chars)
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600); 

    //show a success message to avoid leaking whether an email is registered
    $genericMsg = 'If that email is on file, a reset link has been sent. Check your inbox (and spam folder).';

    if (UserDB::setResetToken($email, $token, $expires)) {
        //build the reset URL
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $resetUrl = $scheme . '://' . $host . '/public/reset_password.php?token=' . urlencode($token);

        /* PHP's built-in mail() for testing purposes.
        $subject = '🚨 MovieSign! — Password Reset';
        $body = "Hiya, Kid!"
                 . " "
                 . "Someone (hopefully you) requested a password reset for your MovieSign! account."
                 . "Click the link below to choose a new password. It expires in 1 hour."
                 . $resetUrl 
                 . " "
                 . "If you didn't request this, just ignore this email — your password won't change."
                 . " "
                 . "— The MovieSign! Bot 🤖";

        $headers = "From: noreply@" . ($_SERVER['HTTP_HOST'] ?? 'moviesign.local') . "\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n";

        mail($email, $subject, $body, $headers);
        */

        // PHPMailer via GoDaddy's internal localhost relay (no auth required)
        require_once(__DIR__ . '/../../vendor/phpmailer/src/Exception.php');
        require_once(__DIR__ . '/../../vendor/phpmailer/src/PHPMailer.php');
        require_once(__DIR__ . '/../../vendor/phpmailer/src/SMTP.php');

        // local deployment
        // $mailConfig = require __DIR__ . '/../Model/mail_config_local.php';

        // live deployment
        $mailConfig = require __DIR__ . '/../Model/mail_config.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $mailConfig['host'];
            $mail->Port = $mailConfig['port'];
            $mail->SMTPAuth = !empty($mailConfig['username']);
            $mail->Username = $mailConfig['username'];
            $mail->Password = $mailConfig['password'];
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

            $mail->setFrom($mailConfig['from'], $mailConfig['from_name']);
            $mail->addAddress($email);

            $mail->Subject = '🚨 MovieSign! — Password Reset';
            $mail->Body    = "Hiya, Kid!\n\n"
                        . "Someone (hopefully you) requested a password reset for your MovieSign! account.\n\n"
                        . "Click the link below to choose a new password. It expires in 1 hour.\n\n"
                        . $resetUrl . "\n\n"
                        . "If you didn't request this, just ignore this email — your password won't change.\n\n"
                        . "— The MovieSign! Bot 🤖";

            $mail->send();

        } catch (Exception $e) {
            error_log('MovieSign password reset mailer error: ' . $mail->ErrorInfo);
            // Remove this redirect once confirmed working — it exposes internal errors:
            redirect_with_msg('../../public/forgot_password.php', 'error',
                'Mailer failed: ' . $mail->ErrorInfo);
        }
    }

    redirect_with_msg('../../public/forgot_password.php', 'success', $genericMsg);
}

// RESET PASSWORD
//process the token and set a new password
elseif ($action === 'reset_password') {

    $token = trim($_POST['token'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm']  ?? '');

    if (!$token) {
        redirect_with_msg('../../public/login.php', 'error', 'Invalid or missing reset token.');
    }

    $user = UserDB::getUserByResetToken($token);

    if (!$user) {
        // Token expired or doesn't exist
        redirect_with_msg('../../public/forgot_password.php', 'error',
            'That reset link has expired or already been used. Request a new one.');
    }

    $pwError = validate_password($password);
    if ($pwError !== null) {
        redirect_with_msg(
            '../../public/reset_password.php?token=' . urlencode($token),
            'error', $pwError
        );
    }

    if ($password !== $confirm) {
        redirect_with_msg(
            '../../public/reset_password.php?token=' . urlencode($token),
            'error', 'Passwords do not match.'
        );
    }

    $newHash = password_hash($password, PASSWORD_BCRYPT);
    $userID  = (int) $user['user_ID'];

    if (UserDB::updateUser($userID, $user['email'], $newHash, $user['zip_code'])) {
        // Invalidate token and all existing sessions
        UserDB::clearResetToken($userID);
        SessionDB::deleteAllForUser($userID);
        redirect_with_msg('../../public/login.php', 'success',
            'Password updated! Sign in with your new password.');
    } else {
        redirect_with_msg(
            '../../public/reset_password.php?token=' . urlencode($token),
            'error', 'Something went wrong. Please try again.'
        );
    }
}

else {
    //just go home
    header("Location: ../../public/index.php");
    exit;
}