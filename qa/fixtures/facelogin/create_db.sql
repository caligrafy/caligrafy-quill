/* Face login and passkeys for the users of the Caligrafy authentication module (users table).
 * Safe to run more than once. MySQL runs in UTC: write and compare times with gmdate() in PHP.
 */
CREATE TABLE IF NOT EXISTS face_prints (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  descriptor TEXT NOT NULL,                      -- encryptDecrypt('encrypt', json) with APP_KEY, never plain
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_face_prints_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- hashes of the prints of accepted logins: the same prints sent again with a new nonce are refused
CREATE TABLE IF NOT EXISTS face_login_used_prints (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  print_hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_face_login_used_prints_hash UNIQUE (print_hash),
  CONSTRAINT fk_face_login_used_prints_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- failures live in their own table, NOT on users: Auth::authorized() compares the whole users row with the copy in
-- the session, so any later change to the row (a failed attempt by someone else) would log the user out
CREATE TABLE IF NOT EXISTS face_login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  attempt_key VARCHAR(191) NOT NULL,             -- client IP + username ('*' for 1:N)
  failures INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  modified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_face_login_attempts_key UNIQUE (attempt_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- passkeys (Caligrafy\Passkey)
CREATE TABLE IF NOT EXISTS passkeys (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  credential_id VARCHAR(255) NOT NULL,           -- base64url
  public_key TEXT NOT NULL,                      -- PEM
  sign_count INT UNSIGNED NOT NULL DEFAULT 0,
  name VARCHAR(64) NOT NULL DEFAULT 'Passkey',
  transports VARCHAR(255) NULL,
  aaguid CHAR(32) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME NULL,
  CONSTRAINT uq_passkeys_credential_id UNIQUE (credential_id),
  CONSTRAINT fk_passkeys_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
