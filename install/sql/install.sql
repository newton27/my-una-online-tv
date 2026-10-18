-- Standalone tables only. Never access legacy Online TV tables during installation.
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

-- Register the native UNA Studio page and home launcher on fresh installs.
SET @nw_page := (SELECT id FROM sys_std_pages WHERE name = 'nw_online_tv' LIMIT 1);
INSERT INTO sys_std_pages (`index`, name, header, caption, icon)
SELECT 3, 'nw_online_tv', '_nw_online_tv', '_nw_online_tv', 'nw_online_tv@modules/newton/my_una_online_tv/|std-icon.svg'
FROM DUAL WHERE @nw_page IS NULL;
SET @nw_page := (SELECT id FROM sys_std_pages WHERE name = 'nw_online_tv' LIMIT 1);
INSERT INTO sys_std_widgets (page_id, module, type, url, click, icon, caption, cnt_notices, cnt_actions)
SELECT @nw_page, 'nw_online_tv', 'manage', '{url_studio}module.php?name=nw_online_tv', '', 'nw_online_tv@modules/newton/my_una_online_tv/|std-icon.svg', '_nw_online_tv', '', 'a:4:{s:6:"module";s:6:"system";s:6:"method";s:11:"get_actions";s:6:"params";a:0:{}s:5:"class";s:18:"TemplStudioModules";}'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM sys_std_widgets WHERE module = 'nw_online_tv');
SET @nw_widget := (SELECT id FROM sys_std_widgets WHERE module = 'nw_online_tv' LIMIT 1);
SET @nw_home := (SELECT id FROM sys_std_pages WHERE name = 'home' LIMIT 1);
INSERT INTO sys_std_pages_widgets (page_id, widget_id, `order`)
SELECT @nw_home, @nw_widget, COALESCE((SELECT MAX(`order`) + 1 FROM sys_std_pages_widgets WHERE page_id = @nw_home), 1)
FROM DUAL WHERE @nw_home IS NOT NULL AND @nw_widget IS NOT NULL AND NOT EXISTS (SELECT 1 FROM sys_std_pages_widgets WHERE page_id = @nw_home AND widget_id = @nw_widget);
