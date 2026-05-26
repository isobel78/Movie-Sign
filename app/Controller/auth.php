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

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }

    if ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    }

    //zip code is now optional if users chooses geolocation
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
        if (strlen($newPassword) < 8) {
            redirect_with_msg('../../public/account.php', 'error', "New password must be at least 8 characters.");
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

else {
    //just go home
    header("Location: ../../public/index.php");
    exit;
}