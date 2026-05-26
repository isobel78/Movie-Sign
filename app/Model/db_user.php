<?php
// Atlanta Daniel
// May 2026
// db_user.php - Class for doing CRUD queries on the users table
// Uses prepared statements throughout to prevent SQL injection

require_once(__DIR__ . '/db.php');

class UserDB {

    //get user by user_ID
    public static function getUser($userID) {
        $db = new Database();
        $dbConn = $db->getDbConn();

        if (!$dbConn) return false;

        $stmt = $dbConn->prepare("SELECT * 
                                  FROM users 
                                  WHERE user_ID = ?");
        $stmt->bind_param("i", $userID);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc();
    }

    //get user by email (used for login lookup)
    public static function getUserByEmail($email) {
        $db = new Database();
        $dbConn = $db->getDbConn();

        if (!$dbConn) return false;

        $stmt = $dbConn->prepare("SELECT * 
                                  FROM users 
                                  WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc();
    }

    //add a new user
    //$pw should already be a bcrypt hash
    public static function addUser($email, $pw_hash, $zip) {
        $db = new Database();
        $dbConn = $db->getDbConn();

        if (!$dbConn) return false;

        $stmt = $dbConn->prepare("INSERT INTO users (email, pw_hash, zip_code) 
                                  VALUES (?, ?, ?)"
        );
        $stmt->bind_param("sss", $email, $pw_hash, $zip);

        return $stmt->execute();
    }

    //update an existing user's email, password hash, and zip
    public static function updateUser($userID, $email, $pw_hash, $zip) {
        $db = new Database();
        $dbConn = $db->getDbConn();

        if (!$dbConn) return false;

        $stmt = $dbConn->prepare( "UPDATE users 
                                   SET email = ?, 
                                       pw_hash = ?, 
                                       zip_code = ? 
                                   WHERE user_ID = ?"
        );
        $stmt->bind_param("sssi", $email, $pw_hash, $zip, $userID);

        return $stmt->execute();
    }

    //delete a user by user_ID
    public static function deleteUser($userID) {
        $db = new Database();
        $dbConn = $db->getDbConn();

        if (!$dbConn) return false;

        $stmt = $dbConn->prepare("DELETE FROM users 
                                  WHERE user_ID = ?");
        $stmt->bind_param("i", $userID);

        return $stmt->execute();
    }

    //check whether an email address is already registered
    public static function emailExists($email) {
        $db = new Database();
        $dbConn = $db->getDbConn();

        if (!$dbConn) return false;

        $stmt = $dbConn->prepare("SELECT user_ID 
                                  FROM users 
                                  WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        return $stmt->num_rows > 0;
    }
}