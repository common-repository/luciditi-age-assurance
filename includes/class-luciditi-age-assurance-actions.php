<?php
if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('Luciditi_Age_Assurance_Actions')) {
	class Luciditi_Age_Assurance_Actions
	{

		/**
		 * Run the necessary processes during plugin activation.
		 *
		 * @since    1.0.0
		 */
		public static function activate()
		{

			/**
			 * Create database table(s)
			 *
			 */
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			global $wpdb;
			$charset_collate = $wpdb->get_charset_collate();

			$luciditi_tmp_sessions = $wpdb->prefix . 'luciditi_tmp_sessions';
			$luciditi_sessions = $wpdb->prefix . 'luciditi_sessions';

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ($wpdb->get_var("SHOW TABLES LIKE '$luciditi_tmp_sessions'") != $luciditi_tmp_sessions) {
				// Used to store users associated lat/long based on their profile address
				// Note that `session_state` can be either `pending`, `valid`, `failed`, or 'external'
				$sql1 = "CREATE TABLE $luciditi_tmp_sessions (
                        session_id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
                        session_key VARCHAR(96) NOT NULL,
                        session_iv VARCHAR(96) NOT NULL,
                        access_token TEXT NOT NULL,
                        secret_key_clear VARCHAR(96) NOT NULL,
                        session_expiry BIGINT(20) NOT NULL,
                        state VARCHAR(10) NOT NULL DEFAULT 'pending',
                        state_data MEDIUMTEXT NOT NULL,
                        user_agent VARCHAR(255) NOT NULL,
                        UNIQUE KEY id (session_id)
                    ) $charset_collate;";
				dbDelta($sql1);

				// If the table is created for the first time, store database version
				// This can be useful if we decided to modify the database structure in the future
				if (is_multisite()) {
					update_site_option('luciditi_aa_db_version', _LUCIDITI_AA_VERSION);
				} else {
					update_option('luciditi_aa_db_version', _LUCIDITI_AA_VERSION);
				}
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ($wpdb->get_var("SHOW TABLES LIKE '$luciditi_sessions'") != $luciditi_sessions) {
				// used to store users associated lat/long based on their profile address
				$sql2 = "CREATE TABLE $luciditi_sessions (
                        session_id mediumint(9) NOT NULL AUTO_INCREMENT,
                        code_id VARCHAR(96) NOT NULL,
                        data MEDIUMTEXT NOT NULL,
                        creation_date BIGINT(20) NOT NULL,
                        expiry_date BIGINT(20) NOT NULL,
						state VARCHAR(10) NOT NULL,
                        user_agent VARCHAR(255) NOT NULL,
                        UNIQUE KEY id (session_id)
                    ) $charset_collate;";
				dbDelta($sql2);
			}

			// Setup cron jobs
			Luciditi_AA_Crons::setup_daily_cron();
		}
		/**
		 * Run the necessary processes during plugin deactivation.
		 *
		 * @since    1.0.0
		 */
		public static function deactivate()
		{
			// Unregister settings ( previously registered from Luciditi_Age_Assurance->admin_menu_items() )
			unregister_setting('luciditi_aa_settings:general', 'luciditi_aa_enable');
			unregister_setting('luciditi_aa_settings:general', 'luciditi_aa_general');
			unregister_setting('luciditi_aa_settings:api', 'luciditi_aa_api');
			unregister_setting('luciditi_aa_settings:stepup', 'luciditi_aa_stepup');
			unregister_setting('luciditi_aa_settings:landing', 'luciditi_aa_landing');
			unregister_setting('luciditi_aa_settings:uninstall', 'luciditi_aa_uninstall');

			// Clear cron jobs
			Luciditi_AA_Crons::clear_all();
		}

		/**
		 * Run the necessary processes on plugin uninstall.
		 *
		 * @since    1.0.0
		 */
		public static function uninstall()
		{
			global $wpdb;
			// Delete any transients related to megaforms
			$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_luciditi_aa_\_%'");
			$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_site\_transient\_luciditi_aa_\_%'");
			$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\_luciditi_aa_\_%'");
			$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_site\_transient\_timeout\_luciditi_aa_\_%'");

			// Check if the user really wants to uninstall the plugin and remove all associated data
			$luciditi_aa_uninstall = get_option('luciditi_aa_uninstall', false);
			if (true === $luciditi_aa_uninstall|| 'yes' === $luciditi_aa_uninstall) {

				// Delete locations table.
				$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'luciditi_tmp_sessions');
				$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'luciditi_sessions');

				// Delete all database options
				delete_option('luciditi_aa_db_version');
				delete_option('luciditi_aa_enable');
				delete_option('luciditi_aa_general');
				delete_option('luciditi_aa_api');
				delete_option('luciditi_aa_stepup');
				delete_option('luciditi_aa_landing');
				delete_option('luciditi_aa_uninstall');

				// Removes all cache items.
				wp_cache_flush();
			}
		}
	}
}
