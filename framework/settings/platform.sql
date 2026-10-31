-- Framework tables of Caligrafy Quill: personal access tokens and the audit log.
-- Created by `php caligrafer.php platform install` (safe to run again: IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS `caligrafy_tokens` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `scopes` VARCHAR(1000) NOT NULL DEFAULT '',
    `user_id` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL,
    `expires_at` DATETIME NULL,
    `last_used_at` DATETIME NULL,
    `revoked_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `caligrafy_tokens_hash` (`token_hash`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS `caligrafy_audit` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `occurred_at` DATETIME NOT NULL,
    `actor_type` VARCHAR(20) NOT NULL,
    `actor_id` VARCHAR(64) NULL,
    `channel` VARCHAR(10) NOT NULL,
    `capability` VARCHAR(100) NOT NULL,
    `outcome` VARCHAR(20) NOT NULL,
    `detail` VARCHAR(500) NULL,
    PRIMARY KEY (`id`),
    KEY `caligrafy_audit_capability` (`capability`, `occurred_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
