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

-- Check history is append-only. A failed URL probe is not proof of dead playback.
CREATE TABLE IF NOT EXISTS `nw_online_tv_health_checks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel_id` INT UNSIGNED NOT NULL,
  `checked_at` INT UNSIGNED NOT NULL,
  `reachable` TINYINT UNSIGNED NOT NULL,
  `consecutive_failures` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `channel_checked` (`channel_id`, `checked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Attempt ledger includes inconclusive probes so they still count toward rate limiting.
CREATE TABLE IF NOT EXISTS `nw_online_tv_health_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel_id` INT UNSIGNED NOT NULL,
  `attempted_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `attempted_at` (`attempted_at`),
  KEY `channel_attempted` (`channel_id`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Source registry required by Studio source approval, archiving and imports.
CREATE TABLE IF NOT EXISTS `nw_online_tv_sources` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(120) NOT NULL,
  `playlist_url` TEXT NOT NULL,
  `origin` VARCHAR(24) NOT NULL DEFAULT 'webmaster',
  `approval_status` VARCHAR(24) NOT NULL DEFAULT 'pending',
  `enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `submitted_profile_id` INT NOT NULL DEFAULT 0,
  `created_at` INT UNSIGNED NOT NULL,
  `updated_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`), KEY `source_status` (`approval_status`,`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `nw_online_tv_source_archives` (
  `source_id` INT UNSIGNED NOT NULL,
  `archived_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `nw_online_tv_source_classification` (
  `source_id` INT UNSIGNED NOT NULL,
  `classification` VARCHAR(24) NOT NULL,
  `updated_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`source_id`)
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

-- Native UNA Pages registration for the member-submitted feed form.
-- This is deliberately idempotent and does not fetch or approve any feeds.
INSERT INTO sys_objects_page
  (object, title_system, title, module, layout_id, visible_for_levels,
   visible_for_levels_editable, uri, url, content_info, meta_title,
   meta_description, meta_keywords, meta_robots, inj_head, inj_footer,
   config_api, cache_lifetime, cache_editable, deletable,
   override_class_name, override_class_file)
SELECT
  'nw_online_tv_submit_feed', 'Submit IPTV feed', 'Submit IPTV feed',
  'nw_online_tv', 5, 2147483647, 1, 'online-tv-submit-feed',
  'page.php?i=online-tv-submit-feed', '', '', '', '', '', '', '',
  '', 0, 1, 1, '', ''
FROM DUAL WHERE NOT EXISTS (
  SELECT 1 FROM sys_objects_page WHERE object = 'nw_online_tv_submit_feed'
);
INSERT INTO sys_pages_blocks
  (object, cell_id, module, title_system, title, icon, designbox_id,
   visible_for_levels, type, content, content_empty, text, text_updated,
   help, config_api, deletable, copyable, `order`)
SELECT
  'nw_online_tv_submit_feed', 1, 'nw_online_tv', 'IPTV feed submission',
  'IPTV feed submission', '', 11, 2147483647, 'service',
  'a:2:{s:6:"module";s:12:"nw_online_tv";s:6:"method";s:33:"get_member_feed_submission_markup";}',
  '', '', 0, '', '', 1, 0, 1
FROM DUAL WHERE NOT EXISTS (
  SELECT 1 FROM sys_pages_blocks
  WHERE object = 'nw_online_tv_submit_feed' AND module = 'nw_online_tv'
);

-- Native site navigation: keep any webmaster-created equivalent, avoiding duplicates.
SET @nw_site_menu_order := (
  SELECT COALESCE(MAX(`order`), 0) + 1
  FROM sys_menu_items WHERE set_name = 'sys_site' AND active = 1 AND `order` < 9999
);
INSERT INTO sys_menu_items
  (set_name, module, name, title_system, title, link, onclick, target,
   icon, submenu_object, visible_for_levels, active, copyable, `order`)
SELECT 'sys_site', 'nw_online_tv', 'nw-online-tv-submit-feed',
  'Submit IPTV Feed', 'Submit IPTV Feed', 'page.php?i=online-tv-submit-feed',
  '', '', 'tv', '', 2147483647, 1, 1, @nw_site_menu_order
FROM DUAL WHERE NOT EXISTS (
  SELECT 1 FROM sys_menu_items WHERE set_name = 'sys_site'
  AND (name IN ('nw-online-tv-submit-feed','submit-iptv-feed')
       OR link IN ('online-tv-submit-feed','page.php?i=online-tv-submit-feed'))
);
