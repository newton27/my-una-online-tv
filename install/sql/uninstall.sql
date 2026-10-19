-- Remove only this module's Studio registration, before dropping its own tables.
DELETE FROM sys_std_pages_widgets WHERE widget_id IN (SELECT id FROM sys_std_widgets WHERE module = 'nw_online_tv');
DELETE FROM sys_std_widgets WHERE module = 'nw_online_tv';
DELETE FROM sys_std_pages WHERE name = 'nw_online_tv';
DROP TABLE IF EXISTS `nw_online_tv_sync_runs`;
DROP TABLE IF EXISTS `nw_online_tv_reports`;
DROP TABLE IF EXISTS `nw_online_tv_channels`;
DROP TABLE IF EXISTS `nw_online_tv_source_classification`;
DROP TABLE IF EXISTS `nw_online_tv_source_archives`;
DROP TABLE IF EXISTS `nw_online_tv_sources`;
