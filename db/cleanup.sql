DROP TABLE phorum_site123_virtual_hosts;
DELETE FROM phorum_site123_settings WHERE name='mod_virtual_hosting';
DELETE FROM phorum_site123_settings WHERE name='mod_virtual_hosting_installed';
ALTER TABLE phorum_site123_forums DROP COLUMN virtualhost_config;

