<!--
Atlanta Daniel
May 2026
-->

<?php

require_once(__DIR__ . '/db.php');

//class for doing CRUD queries on the users table
class UserDB {
    
    //get user by userID
    public static function getUser($userID) {
        $db = new Database();
        $dbConn = $db->getDbConn();

        if ($dbConn) {
            //create the query string
            $query = "SELECT * 
                      FROM users 
                      WHERE user_ID = '$userID';";
            
            //execute the query
            $result = $dbConn->query($query);

            //return the associative array
            return $result->fetch_assoc();
        } else {
            return false;
        }
    }

    //add a new user to the DB
    public static function addUser($userID, $email, $pw, $zip) {
        $db = new Database();
        $dbConn = $db->getDbConn();

        if ($dbConn) {
            //create the query string
            $query = "INSERT INTO users (user_ID, email, pw_hash, zip_code)
                      VALUES ('$userID', '$email', '$pw', '$zip')";

            //execute the query, returning status
            return $dbConn->query($query) === TRUE;
        } else {
            return false;
        }
    }

    //update an existing user
    public static function updateUser($userID, $email, $pw, $zip) {
        $db = new Database();
        $dbConn = $db->getDbConn();

        if ($dbConn) {
            //create the query string
            $query = "UPDATE users
                      SET email = '$email',
                          pw_hash = '$pw',
                          zipcode = '$zip'
                      WHERE user_ID = '$userID'";

            //execute the query, returning status
            return $dbConn->query($query) === TRUE;

        } else {
            return false;
        }
    }

    //delete a user by userID
    public static function deleteUser($userID) {
        $db = new Database();
        $dbConn = $db->getDbConn();

        if ($dbConn) {
            //create the query string
            $query = "DELETE FROM users 
                      WHERE user_ID = '$userID'";
            
            //execute the query, returning status
            return $dbConn->query($query) === TRUE;
        } else {
            return false;
        }
    }
}
?>
