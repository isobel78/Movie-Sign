<?php
// Atlanta Daniel
// May 2026
// db_session.php - Server side session management using the session table

require_once(__DIR__ . '/db.php');

class SessionDB {

    //default: session valid for30 days.
    const REMEMBER_TTL = 2592000;

    //persistent token
    const COOKIE_NAME = 'ms_remember';

    //generate a secure random token, stores it in the sessions table,
    //optionally sets a long-lived cookie on the client
    public static function createSession(int $userID, bool $remember = false): string|false {
        $db = new Database();
        $dbConn = $db->getDbConn();
        
        if (!$dbConn) return false;

        //32 random bytes → 64-char hex string
        $token = bin2hex(random_bytes(32));
        $ttl = $remember ? self::REMEMBER_TTL : 86400; //24 h default
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);

        $stmt = $dbConn->prepare(
            "INSERT INTO sessions (token, user_ID, expires_at)
             VALUES (?, ?, ?)"
        );

        $stmt->bind_param("sis", $token, $userID, $expiresAt);

        if (!$stmt->execute()) return false;

        //set the cookie if "remember me" was requested.
        if ($remember) {
            self::setRememberCookie($token, $ttl);
        }

        return $token;
    }

    //look up a token and return the matching user row if it exists and hasn't expired
    public static function validateToken(string $token): array|false {
        $db = new Database();
        $dbConn = $db->getDbConn();
        
        if (!$dbConn) return false;

        $stmt = $dbConn->prepare(
            "SELECT s.token, s.user_ID, s.expires_at,
                    u.email, u.zip_code
             FROM sessions s
             JOIN users u 
                  ON u.user_ID = s.user_ID
             WHERE s.token = ?
                  AND s.expires_at > NOW()"
        );

        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc() ?: false;
    }

    //delete token (used on logout)
    public static function deleteToken(string $token): bool {
        $db = new Database();
        $dbConn = $db->getDbConn();
        
        if (!$dbConn) return false;

        $stmt = $dbConn->prepare("DELETE FROM sessions 
                                  WHERE token = ?");
        $stmt->bind_param("s", $token);
        
        return $stmt->execute();
    }

    //delete every session for a user
    //used after a password change so all other devices are forced to re-authenticate
    public static function deleteAllForUser(int $userID): bool {
        $db = new Database();
        $dbConn = $db->getDbConn();

        if (!$dbConn) return false;

        $stmt = $dbConn->prepare("DELETE FROM sessions 
                                  WHERE user_ID = ?");
        $stmt->bind_param("i", $userID);
        
        return $stmt->execute();
    }

    //remove rows that are past their expiry date
    public static function pruneExpired(): void {
        $db = new Database();
        $dbConn = $db->getDbConn();
        
        if (!$dbConn) return;

        $dbConn->query("DELETE FROM sessions 
                        WHERE expires_at <= NOW()");
    }

    //if a remember-me cookie exists and the user isn't already in $_SESSION, 
    // creates $_SESSION automatically
    public static function resumeFromCookie(): bool {
        if (!empty($_SESSION['user_id'])) return true;

        $token = $_COOKIE[self::COOKIE_NAME] ?? '';
        if (empty($token)) return false;

        $row = self::validateToken($token);
        if (!$row) {
            //cookie exists but token is invalid/expired, so clear it
            self::clearRememberCookie();
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $row['user_ID'];
        $_SESSION['user_email'] = $row['email'];
        $_SESSION['user_zip'] = $row['zip_code'];

        return true;
    }

    // Helper Functions
    private static function setRememberCookie(string $token, int $ttl): void {
        setcookie(
            self::COOKIE_NAME,
            $token,
            [
                'expires' => time() + $ttl,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    public static function clearRememberCookie(): void {
        setcookie(self::COOKIE_NAME, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}