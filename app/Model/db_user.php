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

    //update default search radius for a user
    public static function updateDefaultRadius(int $userID, int $radius): bool {
        $db = new Database();
        $dbConn = $db->getDbConn();

        if (!$dbConn) return false;

        $stmt = $dbConn->prepare(
            "UPDATE users 
             SET default_radius = ? 
             WHERE user_ID = ?"
        );
        $stmt->bind_param("ii", $radius, $userID);

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

    //password reset
    // Store a time-limited reset token for the given email
    // Returns true on success, false if email not found or DB error
    public static function setResetToken(string $email, string $token, string $expires): bool {
        $db = new Database();
        $dbConn = $db->getDbConn();

        if (!$dbConn) return false;

        $stmt = $dbConn->prepare(
            "UPDATE users
                SET reset_token = ?, 
                    reset_token_expires = ?
              WHERE email = ?"
        );

        $stmt->bind_param("sss", $token, $expires, $email);
        $stmt->execute();

        return $stmt->affected_rows > 0; // 0 = email not in DB
    }

    //look up a user by a valid (non-expired) reset token.
    public static function getUserByResetToken(string $token): array|false {
        $db = new Database();
        $dbConn = $db->getDbConn();

        if (!$dbConn) return false;

        $stmt = $dbConn->prepare(
            "SELECT * 
             FROM users
             WHERE reset_token = ?
               AND reset_token_expires > NOW()"
        );

        $stmt->bind_param("s", $token);
        $stmt->execute();

        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();

        return $user ?: false;
    }

    //clear the reset token after it has been used (or invalidated)
    public static function clearResetToken(int $userID): void {
        $db = new Database();
        $dbConn = $db->getDbConn();

        if (!$dbConn) return;

        $stmt = $dbConn->prepare(
            "UPDATE users
                SET reset_token = NULL, 
                    reset_token_expires = NULL
              WHERE user_ID = ?"
        );
        
        $stmt->bind_param("i", $userID);
        $stmt->execute();
    }
}