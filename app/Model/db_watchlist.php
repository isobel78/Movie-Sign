<!-- Atlanta Daniel -->
<!-- May 2026 -->
<!-- db_watchlist.php - Class for doing CRUD queries on the watchlist table -->

<?php

require_once(__DIR__ . '/db.php');

//class for doing CRUD queries on the users table
class WatchlistDB {
    
    //get watchlst by userID
    public static function getWatchlist($userID) {
        $db = new Database();
        $dbConn = $db->getDbConn();

        if ($dbConn) {
            $stmt = $dbConn->prepare("SELECT * 
                                      FROM watchlist_items 
                                      WHERE user_ID = ?");
            $stmt->bind_param("i", $userID);
            $stmt->execute();
            return $stmt->get_result();
        } else {
            return false;
        }
    }

    
}
?>
