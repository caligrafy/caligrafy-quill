-- Wiki schema (4. Models) with uniquely named constraints. QA fixture: reset on every run
DROP TABLE IF EXISTS books;
DROP TABLE IF EXISTS publishers;

CREATE TABLE IF NOT EXISTS publishers (
    id INT(25) UNSIGNED NOT NULL AUTO_INCREMENT,
    publishername varchar(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8;

CREATE TABLE IF NOT EXISTS books (
    id INT(25) UNSIGNED NOT NULL AUTO_INCREMENT,
    title varchar(255) NOT NULL,
    author varchar(255) NOT NULL,
    publisher_id INT(25) UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_books_publisher FOREIGN KEY (publisher_id) REFERENCES publishers(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8;

INSERT INTO publishers (publishername) VALUES ('Penguin');
