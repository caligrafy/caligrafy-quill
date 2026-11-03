-- Framework tables of Caligrafy Quill: personal access tokens, the audit log, the runs of scheduled jobs and
-- notifications (push subscriptions, confirmed emails, choices, the log).
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

CREATE TABLE IF NOT EXISTS `caligrafy_job_runs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `job` VARCHAR(40) NOT NULL,
    `started_at` DATETIME NOT NULL,
    `finished_at` DATETIME NULL,
    `outcome` VARCHAR(10) NOT NULL,
    `detail` VARCHAR(500) NULL,
    PRIMARY KEY (`id`),
    KEY `caligrafy_job_runs_job` (`job`, `started_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS `caligrafy_push_subscriptions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` VARCHAR(64) NOT NULL,
    `endpoint` TEXT NOT NULL,
    `endpoint_hash` CHAR(64) NOT NULL,
    `p256dh` VARCHAR(255) NOT NULL,
    `auth` VARCHAR(255) NOT NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL,
    `last_success_at` DATETIME NULL,
    `failures` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `caligrafy_push_endpoint` (`endpoint_hash`),
    KEY `caligrafy_push_user` (`user_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS `caligrafy_notify_emails` (
    `user_id` VARCHAR(64) NOT NULL,
    `email` VARCHAR(254) NOT NULL,
    `confirmed_at` DATETIME NULL,
    `token_hash` CHAR(64) NULL,
    `token_expires_at` DATETIME NULL,
    PRIMARY KEY (`user_id`),
    KEY `caligrafy_notify_token` (`token_hash`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS `caligrafy_notify_prefs` (
    `user_id` VARCHAR(64) NOT NULL,
    `channel` VARCHAR(10) NOT NULL,
    `enabled` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (`user_id`, `channel`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS `caligrafy_notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` VARCHAR(64) NOT NULL,
    `title` VARCHAR(80) NOT NULL,
    `tag` VARCHAR(64) NULL,
    `push` INT NOT NULL DEFAULT 0,
    `email` VARCHAR(20) NOT NULL DEFAULT 'off',
    `skipped` VARCHAR(10) NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `caligrafy_notifications_user` (`user_id`, `created_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
