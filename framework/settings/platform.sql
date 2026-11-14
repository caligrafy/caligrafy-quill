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

-- Snap Cards and the assistant (Caligrafy\Snap, Route::snap(), 2.23.0): every row is one person's (user_id)
-- A person's shortcuts: a capability with the inputs they saved, one click away on Home
CREATE TABLE IF NOT EXISTS `caligrafy_snap_shortcuts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(80) NOT NULL,
    `capability` VARCHAR(64) NOT NULL,
    `inputs` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `caligrafy_snap_shortcuts_user` (`user_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Work that runs in the background for one person (an async capability): where it is, and the card it ended with
CREATE TABLE IF NOT EXISTS `caligrafy_snap_tasks` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `capability` VARCHAR(64) NOT NULL,
    `inputs` TEXT NULL,
    `status` VARCHAR(16) NOT NULL DEFAULT 'queued',
    `job_id` INT UNSIGNED NULL,
    `card` MEDIUMTEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `started_at` DATETIME NULL,
    `finished_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `caligrafy_snap_tasks_user` (`user_id`, `id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Noticing (opt-in, per person), what it kept, the routines it set up, and the offers dismissed
CREATE TABLE IF NOT EXISTS `caligrafy_snap_settings` (
    `user_id` INT UNSIGNED NOT NULL,
    `noticing` TINYINT(1) NOT NULL DEFAULT 0,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS `caligrafy_snap_events` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `capability` VARCHAR(64) NOT NULL,
    `inputs` TEXT NULL,
    `fingerprint` CHAR(40) NOT NULL,
    `at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `caligrafy_snap_events_user` (`user_id`, `fingerprint`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS `caligrafy_snap_routines` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `capability` VARCHAR(64) NOT NULL,
    `inputs` TEXT NULL,
    `fingerprint` CHAR(40) NOT NULL,
    `weekday` TINYINT NULL,
    `hour` TINYINT NOT NULL,
    `level` VARCHAR(8) NOT NULL,
    `next_at` DATETIME NOT NULL,
    `last_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `caligrafy_snap_routines_due` (`next_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS `caligrafy_snap_dismissed` (
    `user_id` INT UNSIGNED NOT NULL,
    `fingerprint` CHAR(40) NOT NULL,
    `kind` VARCHAR(12) NOT NULL,
    `until_at` DATETIME NULL,
    PRIMARY KEY (`user_id`, `fingerprint`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- The conversations of the assistant, turn by turn (history, back and forth, rating, a different answer)
CREATE TABLE IF NOT EXISTS `caligrafy_snap_turns` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `conversation` CHAR(16) NOT NULL,
    `text` VARCHAR(500) NOT NULL,
    `caption` TEXT NULL,
    `cards` MEDIUMTEXT NULL,
    `suggestions` TEXT NULL,
    `via` VARCHAR(8) NOT NULL,
    `rating` TINYINT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `caligrafy_snap_turns_user` (`user_id`, `conversation`, `id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
