/* users table for the Caligrafy authentication module.
 * Safe to run more than once: it never drops an existing table.
 * passcode holds a password_hash() value (60+ characters).
 */
CREATE TABLE IF NOT EXISTS users (
    id int(15) NOT NULL AUTO_INCREMENT,
    username varchar(100) NOT NULL,
    passcode varchar(255) NOT NULL,
    permissions tinyint UNSIGNED DEFAULT 0,
    created_at datetime NOT NULL DEFAULT now(),
    modified_at datetime NOT NULL DEFAULT now() ON UPDATE now(),
    PRIMARY KEY (id),
    UNIQUE KEY users_username_unique (username)
)ENGINE = InnoDB DEFAULT CHARSET = utf8;

/* Existing installations created with an older script can upgrade with:
 * ALTER TABLE users MODIFY passcode varchar(255) NOT NULL;
 */
