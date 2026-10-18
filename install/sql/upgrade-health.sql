-- My UNA World Online TV health tables: additive migration for existing 0.1.0 installs.
-- Run once after backup. Do not modify legacy Modzzz Online TV tables.
CREATE TABLE IF NOT EXISTS `nw_online_tv_health_checks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel_id` INT UNSIGNED NOT NULL,
  `checked_at` INT UNSIGNED NOT NULL,
  `reachable` TINYINT UNSIGNED NOT NULL,
  `consecutive_failures` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `channel_checked` (`channel_id`,`checked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `nw_online_tv_health_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel_id` INT UNSIGNED NOT NULL,
  `attempted_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `attempted_at` (`attempted_at`),
  KEY `channel_attempted` (`channel_id`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
