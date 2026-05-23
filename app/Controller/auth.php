 <?php
// Atlanta Daniel
// May 2026
// auth.php — Controller to handle all POST actions: registration, login, and logout

session_start();

require_once(__DIR__ . '/../Model/db_user.php');

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
    if (!preg_match('/^\d{5}(-\d{4})?$/', $zip)) {
        $errors[] = "Please enter a valid US zip code (e.g., 23510).";
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
        
        redirect_with_msg('../../public/index.php', 'success', "Account created — welcome aboard.");
    } else {
        redirect_with_msg('../../public/register.php', 'error', "Registration failed. Please try again.");
    }
}

// LOGIN
elseif ($action === 'login') {

    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        redirect_with_msg('../../public/login.php', 'error', "Please fill in both fields.");
    }

    $user = UserDB::getUserByEmail($email);

    // Use a constant-time comparison to resist timing attacks
    if (!$user || !password_verify($password, $user['pw_hash'])) {
        redirect_with_msg('../../public/login.php', 'error', "Invalid email or password.");
    }

    //successful login
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['user_ID'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_zip'] = $user['zip_code'];

    redirect_with_msg('../../public/index.php', 'success', "Welcome back.");
}

// LOGOUT
elseif ($action === 'logout') {
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

else {
    //just go home
    header("Location: ../../public/index.php");
    exit;
}
?>
