<?php
//Atlanta Daniel
// May 2026
// watchlist.php — Controller for watchlist add / remove actions

session_start();

require_once(__DIR__ . '/../Model/db_watchlist.php');

//check that user is logged in
if (empty($_SESSION['user_id'])) {
    header('Location: ../../public/login.php');
    exit;
}

$userID = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

//Helper function: 
//redirect with a flash message stored in session
function redirect($type, $message) {
    $_SESSION['flash_type'] = $type;
    $_SESSION['flash_message'] = $message;
    header('Location: ../../public/index.php');
    exit;
}

// ADD
if ($action === 'add') {

    $filmID = trim($_POST['film_id'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $posterURL = trim($_POST['poster_url'] ?? '') ?: null;

    //basic validation
    if (empty($filmID) || empty($title)) {
        redirect('error', 'Missing movie data. Please try your search again.');
    }

    //sanitize title and poster URL before storing
    $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $posterURL = $posterURL ? filter_var($posterURL, FILTER_SANITIZE_URL) : null;

    //film_ID must only contain digits (TMDB returns numeric IDs)
    if (!ctype_digit($filmID)) {
        redirect('error', 'Invalid movie ID.');
    }

    $added = WatchlistDB::addToWatchlist($userID, $filmID, $title, $posterURL);

    if ($added) {
        redirect('success', "🎬 <strong>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</strong> added to your watchlist.");
    } else {
        //affected_rows === 0 means it was already on the list (INSERT IGNORE)
        redirect('error', "That film is already on your watchlist.");
    }
}

// REMOVE
elseif ($action === 'remove') {

    $watchlistID = (int) ($_POST['watchlist_id'] ?? 0);

    if ($watchlistID <= 0) {
        redirect('error', 'Invalid watchlist entry.');
    }

    $removed = WatchlistDB::removeFromWatchlist($userID, $watchlistID);

    if ($removed) {
        redirect('success', 'Removed from your watchlist.');
    } else {
        redirect('error', 'Could not remove that item. It may have already been deleted.');
    }
}

//
else {
    header('Location: ../../public/index.php');
    exit;
}