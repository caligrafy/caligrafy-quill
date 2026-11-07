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

-- Background jobs (Caligrafy\Queue, 2.18.0): a capability and its input, run later by a worker
CREATE TABLE IF NOT EXISTS `caligrafy_jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue` VARCHAR(40) NOT NULL DEFAULT 'default',
    `capability` VARCHAR(100) NOT NULL,
    `input` MEDIUMTEXT NOT NULL,
    `status` VARCHAR(10) NOT NULL,
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `max_attempts` INT UNSIGNED NOT NULL DEFAULT 3,
    `timeout` INT UNSIGNED NOT NULL DEFAULT 60,
    `available_at` DATETIME NOT NULL,
    `reserved_at` DATETIME NULL,
    `reserved_by` CHAR(32) NULL,
    `finished_at` DATETIME NULL,
    `last_error` VARCHAR(1000) NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `caligrafy_jobs_due` (`queue`, `status`, `available_at`),
    KEY `caligrafy_jobs_reserved` (`reserved_by`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- The cache when CACHE_STORE=database (Caligrafy\Cache, 2.18.0)
CREATE TABLE IF NOT EXISTS `caligrafy_cache` (
    `key_hash` CHAR(64) NOT NULL,
    `cache_key` VARCHAR(255) NOT NULL,
    `value` MEDIUMTEXT NOT NULL,
    `expires_at` INT UNSIGNED NULL,
    PRIMARY KEY (`key_hash`),
    KEY `caligrafy_cache_expires` (`expires_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- The migrations of application/migrations that ran (Caligrafy\Migrations, 2.18.0)
CREATE TABLE IF NOT EXISTS `caligrafy_migrations` (
    `name` VARCHAR(190) NOT NULL,
    `sha256` CHAR(64) NOT NULL,
    `ran_at` DATETIME NOT NULL,
    PRIMARY KEY (`name`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
