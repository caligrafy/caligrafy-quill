/* Tasks example (php caligrafer.php quickstart --example). Safe to run again: it never drops anything. */
CREATE TABLE IF NOT EXISTS tasks (
    id INT(25) UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    notes VARCHAR(1000) NOT NULL DEFAULT '',
    done TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT now(),
    modified_at DATETIME NOT NULL DEFAULT now() ON UPDATE now(),
    PRIMARY KEY (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
