-- Additive independent source registry. No existing channels are modified.
CREATE TABLE IF NOT EXISTS nw_online_tv_sources (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 title VARCHAR(120) NOT NULL,
 playlist_url VARCHAR(2048) NOT NULL,
 origin VARCHAR(16) NOT NULL,
 approval_status VARCHAR(16) NOT NULL DEFAULT 'pending',
 enabled TINYINT UNSIGNED NOT NULL DEFAULT 0,
 submitted_profile_id INT UNSIGNED NOT NULL DEFAULT 0,
 created_at INT UNSIGNED NOT NULL,
 updated_at INT UNSIGNED NOT NULL,
 PRIMARY KEY (id),
 KEY approval_status (approval_status),
 KEY origin_enabled (origin,enabled),
 KEY submitted_profile_id (submitted_profile_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
