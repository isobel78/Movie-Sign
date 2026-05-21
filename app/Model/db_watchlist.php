<!--
Atlanta Daniel
May 2026
-->

<?php

require_once(__DIR__ . '/db.php');

//class for doing CRUD queries on the users table
class WatchlistDB {
    
    //get watchlst by userID
    public static function getWatchlist($userID) {
        $db = new Database();
        $dbConn = $db->getDbConn();

        if ($dbConn) {
            //create the query string
            $query = "SELECT *
                      FROM watchlist_items
                      WHERE user_ID = '$userID';";
            
            //execute the query
            return $dbConn->query($query);
        } else {
            return false;
        }
    }

    
}
?>
