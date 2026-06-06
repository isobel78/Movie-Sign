CREATE DATABASE IF NOT EXISTS moviesign CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE moviesign;

CREATE TABLE IF NOT EXISTS users (
  user_ID     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email       VARCHAR(255) NOT NULL UNIQUE,
  pw_hash     VARCHAR(255) NOT NULL,
  zip_code    VARCHAR(10) NOT NULL DEFAULT '00000',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reset_token VARCHAR(64) NULL DEFAULT NULL,
  reset_token_expires TIMESTAMP NULL DEFAULT NULL,
  INDEX idx_reset_token (reset_token)
);

CREATE TABLE watchlist_items (
  watchlist_ID   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_ID        INT UNSIGNED NOT NULL,
  film_ID        VARCHAR(50) NOT NULL,
  title          VARCHAR(255) NOT NULL,
  poster_url     VARCHAR(512),
  added_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_ID) REFERENCES users(user_ID) ON DELETE CASCADE,
  UNIQUE KEY uq_user_film (user_ID, film_ID)
);

CREATE TABLE IF NOT EXISTS sessions (
  token      VARCHAR(64)  NOT NULL PRIMARY KEY,
  user_ID    INT UNSIGNED NOT NULL,
  expires_at TIMESTAMP    NOT NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_ID) REFERENCES users(user_ID) ON DELETE CASCADE,
  INDEX idx_user_id (user_ID),
  INDEX idx_expires (expires_at)
);