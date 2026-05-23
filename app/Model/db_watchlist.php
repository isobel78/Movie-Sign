<?php
// Atlanta Daniel
// May 2026
// db_watchlist.php - Class for doing CRUD queries on the watchlist table

require_once(__DIR__ . '/db.php');

class WatchlistDB {

    /***** CREATE *****/

    //add a film to the user's watchlist
    //UNIQUE KEY uq_user_film in the schema prevents duplicate entries
    public static function addToWatchlist($userID, $filmID, $title, $posterURL) {
        $db = new Database();
        $dbConn = $db->getDbConn();

        if (!$dbConn) return false;

        $stmt = $dbConn->prepare(
            "INSERT IGNORE INTO watchlist_items (user_ID, film_ID, title, poster_url)
             VALUES (?, ?, ?, ?)"
        );

        $stmt->bind_param("isss", $userID, $filmID, $title, $posterURL);
        
        return $stmt->execute() && $stmt->affected_rows > 0;
    }


    /***** READ *****/

    //return all watchlist rows for a user as an array of associative arrays
    public static function getWatchlist($userID) {
        $db = new Database();
        $dbConn = $db->getDbConn();

        if (!$dbConn) return [];

        $stmt = $dbConn->prepare(
            "SELECT watchlist_ID, film_ID, title, poster_url, added_at
             FROM watchlist_items
             WHERE user_ID = ?
             ORDER BY added_at DESC"
        );

        $stmt->bind_param("i", $userID);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    //check whether a specific film is already on a user's watchlist
    //returns true/false
    public static function isOnWatchlist($userID, $filmID) {
        $db = new Database();
        $dbConn = $db->getDbConn();

        if (!$dbConn) return false;

        $stmt = $dbConn->prepare(
            "SELECT watchlist_ID
             FROM watchlist_items
             WHERE user_ID = ? AND film_ID = ?"
        );

        $stmt->bind_param("is", $userID, $filmID);
        $stmt->execute();
        $stmt->store_result();

        return $stmt->num_rows > 0;
    }

    //return the count of items on a user's watchlist
    public static function getWatchlistCount($userID) {
        $db = new Database();
        $dbConn = $db->getDbConn();

        if (!$dbConn) return 0;

        $stmt = $dbConn->prepare(
            "SELECT COUNT(*) AS cnt 
             FROM watchlist_items 
             WHERE user_ID = ?"
        );

        $stmt->bind_param("i", $userID);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return (int) $row['cnt'];
    }
    

    /***** DELETE *****/

    //remove a film from the watchlist
    public static function removeFromWatchlist($userID, $watchlistID) {
        $db = new Database();
        $dbConn = $db->getDbConn();

        if (!$dbConn) return false;

        $stmt = $dbConn->prepare(
            "DELETE FROM watchlist_items
             WHERE watchlist_ID = ? AND user_ID = ?"
        );

        $stmt->bind_param("ii", $watchlistID, $userID);

        return $stmt->execute() && $stmt->affected_rows > 0;
    }
}
?>
