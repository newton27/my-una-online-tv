-- Standalone tables only. Never access Modzzz tables during installation.
CREATE TABLE IF NOT EXISTS `nw_online_tv_channels` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `stream_url` TEXT NOT NULL,
  `stream_hash` CHAR(64) NOT NULL,
  `source` VARCHAR(64) NOT NULL DEFAULT 'manual',
  `country_code` VARCHAR(8) NOT NULL DEFAULT '',
  `language_code` VARCHAR(16) NOT NULL DEFAULT '',
  `category` VARCHAR(100) NOT NULL DEFAULT '',
  `status` VARCHAR(24) NOT NULL DEFAULT 'pending',
  `failure_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `review_required` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` INT UNSIGNED NOT NULL,
  `updated_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `stream_hash` (`stream_hash`), KEY `review_required` (`review_required`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `nw_online_tv_reports` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel_id` INT UNSIGNED NOT NULL,
  `reporter_profile_id` INT NOT NULL DEFAULT 0,
  `reason` VARCHAR(120) NOT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  `resolved_at` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`), KEY `channel_id` (`channel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `nw_online_tv_sync_runs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source` VARCHAR(64) NOT NULL,
  `result` VARCHAR(24) NOT NULL DEFAULT 'queued',
  `discovered` INT UNSIGNED NOT NULL DEFAULT 0,
  `added` INT UNSIGNED NOT NULL DEFAULT 0,
  `finished_at` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
