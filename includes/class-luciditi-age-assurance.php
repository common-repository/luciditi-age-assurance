<?php
if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('Luciditi_Age_Assurance')) {
	class Luciditi_Age_Assurance
	{

		public function __construct()
		{
			// Load dependencies and define hooks
			$this->load_dependencies();
			$this->define_hooks();
		}

		/**
		 * Load the required classes and dependencies for this plugin.
		 *
		 * @since    1.0.0
		 */
		private function load_dependencies()
		{
			// Load functions
			foreach (glob(plugin_dir_path(__FILE__) . 'functions/luciditi-*.php') as $file) {
				require_once $file;
			}
			// Load Classes
			foreach (glob(plugin_dir_path(__FILE__) . 'classes/class-luciditi-aa-*.php') as $file) {
				require_once $file;
			}
		}

		/**
		 * Register all of the hooks related to the admin and public functionality
		 * of the plugin.
		 *
		 * @since    1.0.0
		 */
		private function define_hooks()
		{

			/**
			 * General hooks
			 *
			 */

			// Scripts and styles hook
			add_action('admin_enqueue_scripts', array($this, 'enqueue_styles_admin'));
			add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts_admin'));
			add_action('wp_enqueue_scripts', array($this, 'enqueue_styles_public'));
			add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts_public'));
			add_action('style_loader_tag', array($this, 'modify_style_tag'), 999, 3);
			add_action('script_loader_tag', array($this, 'modify_scripts_tag'), 999, 3);

			// Load translations
			add_action('plugins_loaded', array($this, 'load_textdomain'));

			/**
			 * Admin area
			 */
			if (is_admin()) {

				// Add necessary admin dashboard menu items
				add_action('admin_menu', array($this, 'admin_menu_items'));
				// Handle logs download
				add_action('load-settings_page_luciditi_aa_settings', array($this, 'download_logs'));
				// Add plugin settings link to plugin action links
				add_filter('plugin_action_links_luciditi-age-assurance/luciditi-age-assurance.php', array($this, 'settings_link'));

				// Save post meta ( targeting pages and products where using age restriction )
				add_action('save_post', array($this, 'save_post_meta'), 10, 3);

				// Add custom columns to users table
				add_filter('manage_users_columns', array($this, 'add_columns_to_users_table'));
				// Populate custom columns with data
				add_filter('manage_users_custom_column', array($this, 'populate_columns_in_users_table'), 10, 3);

				// Add custom fields to WooCommerce product edit page and category options
				if (
					class_exists('woocommerce') ||
					in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))
				) {
					if ('woocommerce' === luciditi_get_option('enable_mode', '')) {
						// Create the the plugin options for products
						add_filter('woocommerce_product_data_tabs', array($this, 'wc_product_tab'));
						add_action('woocommerce_product_data_panels', array($this, 'wc_product_options'));
						// Save the plugin options for products
						add_action('woocommerce_process_product_meta', array($this, 'wc_product_options_save'));

						// Create custom fields for product categories and handle saving them
						add_action('product_cat_edit_form_fields', array($this, 'wc_product_catgeory_fields'), 99);
						add_action('edited_product_cat', array($this, 'wc_product_category_fileds_save'), 10, 1);
						add_action('create_product_cat', array($this, 'wc_product_category_fileds_save'), 10, 1);

						// Add column to the products list to indicate a product is restricted or not
						add_filter('manage_edit-product_columns', array($this, 'wc_product_columns'));
						add_filter('manage_product_posts_custom_column', array($this, 'wc_product_columns_output'), 10, 2);
					}
				}
			} else {
				// Handle callbacks if needed
				add_action('init', array($this, 'maybe_handle_api_callback'), 1);
				// Process verifications if needed
				// Note: replace the hook with `init` and set priority to `2` in case of issues with `wp` hook.
				// `wp` hook is only used to be able to call `is_page` correctly to omit under-age fallback page.
				add_action('wp', array($this, 'maybe_verify_age'), 1);
			}

			/**
			 * AJAX
			 */

			// Handle session reset
			add_action('wp_ajax_luciditi_aa_reset_session', array($this, 'ajax_reset_session'), 10);
			add_action('wp_ajax_nopriv_luciditi_aa_reset_session', array($this, 'ajax_reset_session'), 10);
			// Handle auto verification ( validate user session )
			add_action('wp_ajax_luciditi_aa_validate_external_session', array($this, 'ajax_validate_external_session'), 10);
			add_action('wp_ajax_nopriv_luciditi_aa_validate_external_session', array($this, 'ajax_validate_external_session'), 10);
			// Handle AJAX API authentication
			add_action('wp_ajax_luciditi_aa_api_auth', array($this, 'ajax_api_auth'), 10);
			add_action('wp_ajax_nopriv_luciditi_aa_api_auth', array($this, 'ajax_api_auth'), 10);
			// Handle AJAX temp session retreival
			add_action('wp_ajax_luciditi_aa_get_tmp_session', array($this, 'ajax_get_tmp_session'), 10);
			add_action('wp_ajax_nopriv_luciditi_aa_get_tmp_session', array($this, 'ajax_get_tmp_session'), 10);
			// Handle AJAX temp session retreival
			add_action('wp_ajax_luciditi_aa_get_signup_data', array($this, 'ajax_get_signup_data'), 10);
			add_action('wp_ajax_nopriv_luciditi_aa_get_signup_data', array($this, 'ajax_get_signup_data'), 10);
			// Handle AJAX temp session retreival
			add_action('wp_ajax_luciditi_aa_save_temp_session_codeid', array($this, 'ajax_save_temp_session_codeid'), 10);
			add_action('wp_ajax_nopriv_luciditi_aa_save_temp_session_codeid', array($this, 'ajax_save_temp_session_codeid'), 10);
			// Handle AJAX temp session retreival
			add_action('wp_ajax_luciditi_aa_set_session_state_failed', array($this, 'ajax_set_session_state_failed'), 10);
			add_action('wp_ajax_nopriv_luciditi_aa_set_session_state_failed', array($this, 'ajax_set_session_state_failed'), 10);
		}
		/**********************************************************************
		 ***************************** General ********************************
		 **********************************************************************/


		/**
		 * Enqueue style and javascript files
		 * of the plugin.
		 *
		 * @since    1.0.0
		 */
		public function enqueue_styles_admin($hook)
		{
			if ('settings_page_luciditi_aa_settings' == $hook) {
				wp_enqueue_style(_LUCIDITI_AA_PREFIX . '-styles', plugin_dir_url(__FILE__) . 'assets/css/min/admin.min.css', array('wp-color-picker'), _LUCIDITI_AA_VERSION, 'all');
			}
		}
		public function enqueue_scripts_admin($hook)
		{
			if (
				'settings_page_luciditi_aa_settings' === $hook ||
				('post-new.php' === $hook && 'product' === get_post_type()) ||
				('post.php' === $hook && 'product' === get_post_type()) ||
				('term.php' === $hook && isset($_GET['tag_ID']) &&  isset($_GET['taxonomy']) && 'product_cat' === $_GET['taxonomy'])
			) {
				wp_register_script('mf-conditional-fields', plugin_dir_url(__FILE__) . 'assets/js/deps/mf-conditional-fields.min.js', array(), _LUCIDITI_AA_VERSION, false);
				wp_enqueue_script(_LUCIDITI_AA_PREFIX . '-scripts', plugin_dir_url(__FILE__) . 'assets/js/min/admin.min.js', array('jquery', 'wp-color-picker', 'mf-conditional-fields'), _LUCIDITI_AA_VERSION, false);
			}
		}

		public function enqueue_styles_public()
		{
			wp_register_style(_LUCIDITI_AA_PREFIX . '-google-fonts', 'https://fonts.googleapis.com', array(), null);
			wp_register_style(_LUCIDITI_AA_PREFIX . '-gstatic-fonts', 'https://fonts.gstatic.com', array(), null);
			wp_register_style(_LUCIDITI_AA_PREFIX . '-google-fonts-stylesheet', 'https://fonts.googleapis.com/css2?family=Play:wght@400;700&family=Poppins:wght@400;700&display=swap', array(_LUCIDITI_AA_PREFIX . '-google-fonts', _LUCIDITI_AA_PREFIX . '-gstatic-fonts'), null);
			wp_register_style(_LUCIDITI_AA_PREFIX . '-styles', plugin_dir_url(__FILE__) . 'assets/css/min/public.min.css', array(_LUCIDITI_AA_PREFIX . '-google-fonts-stylesheet'), _LUCIDITI_AA_VERSION, 'all');
		}
		public function enqueue_scripts_public()
		{
			wp_register_script(_LUCIDITI_AA_PREFIX . '-sdk', _LUCIDITI_AA_SDK_URL, array(), _LUCIDITI_AA_VERSION, true);
			wp_register_script(_LUCIDITI_AA_PREFIX . '-ui-sdk', _LUCIDITI_AA_UI_SDK_URL, array(), _LUCIDITI_AA_VERSION, true);
			wp_register_script(
				_LUCIDITI_AA_PREFIX . '-scripts',
				plugin_dir_url(__FILE__) . 'assets/js/min/public.min.js',
				array(
					'jquery',
					_LUCIDITI_AA_PREFIX . '-sdk',
					_LUCIDITI_AA_PREFIX . '-ui-sdk',
				),
				_LUCIDITI_AA_VERSION,
				true
			);
			wp_localize_script(_LUCIDITI_AA_PREFIX . '-scripts', 'luciditi_aa_strings', $this->get_js_strings());
		}
		public function modify_style_tag($tag, $handle, $src)
		{
			// Modify the style tag to add the necessary attributes
			if (_LUCIDITI_AA_PREFIX . '-google-fonts' === $handle || _LUCIDITI_AA_PREFIX . '-gstatic-fonts' === $handle) {
				$tag = str_replace('stylesheet', 'preconnect', $tag);
				$tag = str_replace("type='text/css'", '', $tag);
				$tag = str_replace("media='all'", '', $tag);

				if (_LUCIDITI_AA_PREFIX . '-gstatic-fonts' === $handle) {
					$tag = str_replace("/>", 'crossorigin />', $tag);
				}
			} elseif (_LUCIDITI_AA_PREFIX . '-google-fonts-stylesheet' === $handle) {
				$tag = str_replace("type='text/css'", '', $tag);
				$tag = str_replace("media='all'", '', $tag);
			}

			return $tag;
		}
		public function modify_scripts_tag($tag, $handle, $src)
		{
			// Modify the script type of Luciditi UI SDK script
			if (_LUCIDITI_AA_PREFIX . '-ui-sdk' === $handle) {
				if (strpos($tag, 'text/javascript') !== false) {
					$tag = str_replace('text/javascript', 'module', $tag);
				} else {
					$tag = str_replace('src=', "type='module' src=", $tag);
				}
			}

			return $tag;
		}

		/**
		 * Loads the plugin's translated strings.
		 *
		 * @since    1.0.0
		 */
		public function load_textdomain()
		{
			// Get translations path relative to the plugins directory, which in our case is `luciditi-age-assurance/languages`
			$plugin_rel_path = basename(dirname(__DIR__)) . '/languages';
			// Load the translated strings
			load_plugin_textdomain('luciditi-age-assurance', false, $plugin_rel_path);
		}


		/**********************************************************************
		 ************************** Admin functions ***************************
		 *** All the admin functions that are used for back end porpuses
		 **********************************************************************/

		/**
		 * Add admin dashboard menu items
		 *
		 * @since    1.0.0
		 */
		public function admin_menu_items()
		{

			// Settings sub menu
			add_submenu_page(
				'options-general.php',
				'Age Assurance',
				'Age Assurance',
				'manage_options',
				'luciditi_aa_settings',
				array($this, 'admin_menu_settings')
			);

			// Register settings
			register_setting(
				'luciditi_aa_settings:general', // group
				luciditi_prepare_key('general'), // field key
				array('type' => 'array') // sanitization/type
			);
			register_setting(
				'luciditi_aa_settings:general',
				luciditi_prepare_key('enable_mode')
			);

			register_setting(
				'luciditi_aa_settings:api',
				luciditi_prepare_key('api'),
				array('type' => 'array')
			);

			register_setting(
				'luciditi_aa_settings:landing',
				luciditi_prepare_key('landing'),
				array('type' => 'array')
			);

			register_setting(
				'luciditi_aa_settings:conf',
				luciditi_prepare_key('stepup'),
				array('type' => 'array')
			);
			register_setting(
				'luciditi_aa_settings:conf',
				luciditi_prepare_key('underage_redirection'),
				array('type' => 'array')
			);
			register_setting(
				'luciditi_aa_settings:conf',
				luciditi_prepare_key('geolocation_enabled'),
				array('type' => 'array')
			);
			register_setting(
				'luciditi_aa_settings:conf',
				luciditi_prepare_key('geographical_conf'),
				array(
					'type' => 'array',
					'sanitize_callback' => function ($input) {
						// Check if geolocation is enabled in the submitted form data
						$geolocation_enabled = isset($_POST[luciditi_prepare_key('geolocation_enabled')]) && 'yes' === $_POST[luciditi_prepare_key('geolocation_enabled')]; // phpcs:ignore
						if (!$geolocation_enabled) {
							// If geolocation is not enabled, unset all fields in geographical_conf
							return array();
						}
						// Loop through the access rules and remove incomplete ones
						foreach ($input['access_rules'] as $index => $rule) {
							if (empty($rule['country']) || empty($rule['rule'])) {
								// Make sure access rules with empty country are not saved
								unset($input['access_rules'][$index]);
							} elseif ('US' === $rule['country']) {
								// Unset regions if US is selected ( usefull if the user has select GB before and reverted to US )
								unset($input['access_rules'][$index]['region']);
							} elseif ('GB' === $rule['country']) {
								// Unset states if GB is selected ( usefull if the user has select US before and reverted to GB )
								unset($input['access_rules'][$index]['state']);
							}
						}

						// Reindex the array to remove any gaps
						$input['access_rules'] = array_values($input['access_rules']);

						return $input;
					},
				)
			);

			register_setting('luciditi_aa_settings:uninstall', luciditi_prepare_key('uninstall'));
		}
		/**
		 * Handle logs download
		 *
		 * @since    1.0.0
		 */
		public function download_logs()
		{

			if (isset($_GET['download_logs']) && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'luciditi-download-logs')) {

				// Create the temp folder, if it doesn't already exists
				$upload_dir = wp_upload_dir();
				$upload_path = $upload_dir['basedir'] . '/luciditi_tmp';
				if (!is_dir($upload_path)) {
					wp_mkdir_p($upload_path);

					// Write htaccess file
					$htaccess_file = $upload_path . '/.htaccess';
					if (file_exists($htaccess_file)) {
						@unlink($htaccess_file);
					}

					if (!function_exists('insert_with_markers')) {
						require_once ABSPATH . 'wp-admin/includes/misc.php';
					}
					insert_with_markers($htaccess_file, 'Luciditi Rules', 'deny from all');
				}

				// Clean the folder
				$files = glob($upload_path . '/*'); // get all file names
				foreach ($files as $file) { // iterate files
					if (is_file($file)) {
						unlink($file); // delete file
					}
				}

				// Prapare file name and path
				$filename  = 'luciditi-aa-logs-' . gmdate('d-m-Y-his') . '.csv';
				$file_path = $upload_path . '/' . $filename;

				// Open the file
				$handle = fopen($file_path, 'w');
				if (!$handle) {
					die("can't create the CSV file.");
				}

				// Insert the data into the file
				fputcsv($handle, array('session_id', 'code_id', 'info', 'creation_date', 'expiry_date', 'state', 'user_agent'));

				global $wpdb;
				$table = $wpdb->prefix . 'luciditi_sessions';

				// Prepare the query
				$query  = "SELECT * FROM `$table`";
				$query .= ' ORDER BY `creation_date` DESC';

				// Get the player
				$sessions = $wpdb->get_results($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				if (!empty($sessions)) {
					foreach ($sessions as $session) {
						fputcsv($handle, array($session->session_id, $session->code_id, $session->data['info'] ?? '', gmdate('Y-m-d H:i:s', $session->creation_date), gmdate('Y-m-d H:i:s', $session->expiry_date), $session->state, $session->user_agent));
					}
				}

				fclose($handle);
				// Download the file
				$filetype = wp_check_filetype($file_path);
				nocache_headers();
				header('X-Robots-Tag: noindex', true);
				header('Content-Type: ' . $filetype['type'] ?? 'text/csv');
				header('Content-Description: File Transfer');
				header('Content-Disposition: attachment; filename="' . $filename . '"');
				header('Content-Transfer-Encoding: binary');

				// Clear the buffer and turn it off completely to prevent the file from getting corrupt for the reason
				// This can happen due to manipulation, or printed content before the header is sent
				if (ob_get_contents()) {
					ob_end_clean();
				}
				if (readfile($file_path)) {
					unlink($file_path); // Delete file after it's downloaded
				}
				exit;
			}
		}

		/**
		 * Add settings page link to the plugin
		 *
		 * @since    1.0.0
		 */
		public function settings_link($links)
		{

			// Build and escape the URL.
			$url = esc_url(
				add_query_arg(
					array(
						'page' => 'luciditi_aa_settings',
					),
					admin_url('options-general.php')
				)
			);
			// Create the link.
			$settings_link = '<a href="' . $url . '">' . esc_html__('Settings', 'luciditi-age-assurance') . '</a>';
			// Adds the link to the end of the array.
			array_push(
				$links,
				$settings_link
			);
			return $links;
		}

		/**
		 * Save meta on posts and pages where age restriction is used
		 *
		 * @since    1.0.3
		 */
		public function save_post_meta($post_id, $post, $update)
		{
			// Bail out if `page_based` or `woocommerce` age restriction is not enabled.
			$enable_mode = luciditi_get_option('enable_mode', '');
			if ('page_based' === $enable_mode || 'woocommerce' === $enable_mode) {
				return;
			} else {
				$expected_post_type = 'page_based' === $enable_mode ? 'page' : 'product';
			}


			// Ignore if the current type is not the expected post type
			$post_type = get_post_type($post_id);
			if ($expected_post_type !== $post_type) {
				return;
			}
			// Ignore if this is just a revision
			if (wp_is_post_revision($post_id)) {
				return;
			}
			// Ignore if this is just an auto draft
			if ('auto-draft' === $post->post_status) {
				return;
			}

			$redirect_type = get_post_meta($post_id, luciditi_prepare_key('redirection'), true);
			$page_id = get_post_meta($post_id, luciditi_prepare_key('fallback_page'), true);

			if ('page' === $redirect_type && !empty($page_id)) {
				luciditi_update_fallback_pages_option($page_id, $post_type, $post_id);
			} else {
				luciditi_remove_fallback_page_option($post_type, $post_id);
			}
		}
		/**
		 * Add custom columns to users table
		 *
		 * @since    1.0.3
		 */
		public function add_columns_to_users_table($columns)
		{
			$custom_columns = array(
				'luciditi_age_assurance' => esc_html__('Age Assurance', 'luciditi-age-assurance'),
			);

			return array_merge($columns, $custom_columns);
		}

		/**
		 * Populate custom columns with data
		 *
		 * @since    1.0.3
		 */
		public function populate_columns_in_users_table($value, $column_name, $user_id)
		{
			if ('luciditi_age_assurance' === $column_name) {
				$previously_verified_user = get_user_meta($user_id, luciditi_prepare_key('verified_user'), true);
				if ('yes' === $previously_verified_user) {
					$previously_verified_user_age = get_user_meta($user_id, luciditi_prepare_key('verified_user_min_age'), true);
					return $previously_verified_user_age . esc_html__(' Verified', 'luciditi-age-assurance');
				} else {
					return 'N/A';
				}
			}
			return $value;
		}
		/**
		 * Add custom tab to the product edit screen
		 *
		 * @since    1.0.3
		 * @access   public
		 */
		public function wc_product_tab($tabs)
		{
			if (is_array($tabs)) {
				$tabs[_LUCIDITI_AA_PREFIX . '_age_assurance'] = array(
					'label'  => esc_html__('Age Assurance', 'luciditi-age-assurance'),
					'target' => _LUCIDITI_AA_PREFIX . '_options',
					'class'  => array('show_if_simple', 'show_if_variable'),
				);
			}

			return $tabs;
		}
		/**
		 * Render the age assurance tab options
		 *
		 * @since    1.0.3
		 * @access   public
		 */
		public function wc_product_options()
		{

			echo "<div id='" . _LUCIDITI_AA_PREFIX . "_options' class='panel woocommerce_options_panel' style='display:none;'>";
			echo '<div class="options_group">';

			// Age Restriction Enabled
			woocommerce_wp_checkbox(
				array(
					'id'    => luciditi_prepare_key('enabled'),
					'label' => __('Enable Age Restriction', 'luciditi-age-assurance'),
				),
			);

			// Prepare a condition to show subsequent fields only if the "enable" checkbox above is checked
			$age_assurance_options_condition = get_mf_conditional_rules(
				'show',
				array(
					array(
						'name'     => luciditi_prepare_key('enabled'),
						'operator' => 'is',
						'value'    => 'yes',
					),
				),
				'and',
				'.form-field'
			);

			// Minimum Age
			$general_settings = luciditi_get_option('general', array());
			$default_min_age  = luciditi_get('min_age', $general_settings, 18);
			woocommerce_wp_text_input(
				array(
					'id'                => luciditi_prepare_key('minimum_age'),
					'label'             => __('Minimum Age', 'luciditi-age-assurance'),
					'type'              => 'number',
					'placeholder'       => $default_min_age,
					'custom_attributes' => array(
						'step'                   => '1',
						'min'                    => '0',
						'data-conditional-rules' => esc_html($age_assurance_options_condition),
					),
				),
			);

			// Check if redirect is enabled
			$underage_redirect         = luciditi_get_option('underage_redirection', array());
			$underage_fallback_enabled = 'yes' === luciditi_get('enabled', $underage_redirect);
			// Only enable these fields if the main one is enabled
			if ($underage_fallback_enabled) {

				// Failed Verification or under-age redirection
				woocommerce_wp_select(
					array(
						'id'      =>  luciditi_prepare_key('redirection'),
						'label'   => __('Under-Age Redirection', 'luciditi-age-assurance'),
						'options' => array(
							'default'  => __('Default', 'luciditi-age-assurance'),
							'none'     => __('None', 'luciditi-age-assurance'),
							'page'     => __('Page', 'luciditi-age-assurance'),
							'redirect' => __('Redirect', 'luciditi-age-assurance'),
						),
						'custom_attributes' => array(
							'data-conditional-rules' => esc_html($age_assurance_options_condition),
						),
					),
				);

				/*
				 * Conditional options based on Under-Age redirection field value
				 */
				// Prepare a condition to show subsequent fields based on `Under-Age Redirection` selection
				$redirection_condition_for_page = get_mf_conditional_rules(
					'show',
					array(
						array(
							'name'     => luciditi_prepare_key('enabled'),
							'operator' => 'is',
							'value'    => 'yes',
						),
						array(
							'name'     => luciditi_prepare_key('redirection'),
							'operator' => 'is',
							'value'    => 'page',
						),
					),
					'and',
					'.form-field'
				);

				$redirection_condition_for_url = get_mf_conditional_rules(
					'show',
					array(
						array(
							'name'     => luciditi_prepare_key('enabled'),
							'operator' => 'is',
							'value'    => 'yes',
						),
						array(
							'name'     => luciditi_prepare_key('redirection'),
							'operator' => 'is',
							'value'    => 'redirect',
						),
					),
					'and',
					'.form-field'
				);
				// Get all WordPress pages
				$pages       = get_pages(array('post_type' => 'page'));
				$pages_array = array();
				foreach ($pages as $page) {
					$pages_array[$page->ID] = $page->post_title;
				}
				// Fallback Page
				woocommerce_wp_select(
					array(
						'id'                =>  luciditi_prepare_key('fallback_page'),
						'label'             => __('Select Page', 'luciditi-age-assurance'),
						'options'           => $pages_array,
						'desc_tip'          => true,
						'custom_attributes' => array(
							'data-conditional-rules' => esc_html($redirection_condition_for_page),
						),
					),
				);

				// Redirect URL
				woocommerce_wp_text_input(
					array(
						'id'                => luciditi_prepare_key('fallback_url'),
						'label'             => __('Redirect URL', 'luciditi-age-assurance'),
						'desc_tip'          => true,
						'type'              => 'url',
						'custom_attributes' => array(
							'data-conditional-rules' => esc_html($redirection_condition_for_url),
						),
					),
				);
			}
			echo '</div>';
			echo '</div>';
		}
		/**
		 * Save the age assurance options to product meta
		 *
		 * @since    1.0.3
		 * @access   public
		 */
		public function wc_product_options_save($post_id)
		{
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$age_restriction_enabled = isset($_POST[luciditi_prepare_key('enabled')]) ? 'yes' : 'no';
			update_post_meta(
				$post_id,
				luciditi_prepare_key('enabled'),
				esc_attr($age_restriction_enabled)
			);
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if (isset($_POST[luciditi_prepare_key('minimum_age')])) {
				update_post_meta(
					$post_id,
					luciditi_prepare_key('minimum_age'),
					sanitize_text_field($_POST[luciditi_prepare_key('minimum_age')])
				);
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if (isset($_POST[luciditi_prepare_key('redirection')])) {
				update_post_meta(
					$post_id,
					luciditi_prepare_key('redirection'),
					sanitize_text_field($_POST[luciditi_prepare_key('redirection')])
				);
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if (isset($_POST[luciditi_prepare_key('fallback_page')])) {
				update_post_meta(
					$post_id,
					luciditi_prepare_key('fallback_page'),
					absint($_POST[luciditi_prepare_key('fallback_page')])
				);
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if (isset($_POST[luciditi_prepare_key('fallback_url')])) {
				update_post_meta(
					$post_id,
					luciditi_prepare_key('fallback_url'),
					sanitize_text_field($_POST[luciditi_prepare_key('fallback_url')])
				);
			}
		}

		/**
		 * Create custom fields for product categories on WooCommerce
		 *
		 * @since    1.0.3
		 * @access   public
		 */
		public function wc_product_catgeory_fields($term)
		{

			// Prepare term ID and field values
			$term_id       = $term->term_id;
			$enabled       = get_term_meta($term_id, luciditi_prepare_key('enabled'), true);
			$minimum_age   = get_term_meta($term_id, luciditi_prepare_key('minimum_age'), true);
			$redirection   = get_term_meta($term_id, luciditi_prepare_key('redirection'), true);
			$fallback_page = get_term_meta($term_id, luciditi_prepare_key('fallback_page'), true);
			$fallback_url  = get_term_meta($term_id, luciditi_prepare_key('fallback_url'), true);
			// Check if redirect is enabled
			$underage_redirect         = luciditi_get_option('underage_redirection', array());
			$underage_fallback_enabled = 'yes' === luciditi_get('enabled', $underage_redirect);
			// Minimum Age
			$general_settings = luciditi_get_option('general', array());
			$default_min_age  = luciditi_get('min_age', $general_settings, 18);

			$show_when_enabled = get_mf_conditional_rules(
				'show',
				array(
					'name'     => luciditi_prepare_key('enabled'),
					'operator' => 'is',
					'value'    => 'yes',
				)
			);
?>
			<tr class="form-field">
				<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('enabled')); ?>"><?php esc_html_e('Enable Age Assurance', 'luciditi-age-assurance'); ?></label></th>
				<td>
					<input type="checkbox" id="<?php echo esc_attr(luciditi_prepare_key('enabled')); ?>" name="<?php echo esc_attr(luciditi_prepare_key('enabled')); ?>" value="yes" <?php checked('yes', $enabled, true); ?> />
				</td>
			</tr>
			<tr class="form-field" hidden="true">
				<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('minimum_age')); ?>"><?php esc_html_e('Minimum Age', 'luciditi-age-assurance'); ?></label></th>
				<td>
					<input type="text" id="<?php echo esc_attr(luciditi_prepare_key('minimum_age')); ?>" name="<?php echo esc_attr(luciditi_prepare_key('minimum_age')); ?>" value="<?php echo !empty($minimum_age) ? absint($minimum_age) : ''; ?>" placeholder="<?php echo absint($default_min_age); ?>" data-conditional-rules="<?php echo esc_html($show_when_enabled); ?>" />
				</td>
			</tr>
			<?php
			$redirect_options = array(
				'default'  => __('Default', 'luciditi-age-assurance'),
				'none'     => __('None', 'luciditi-age-assurance'),
				'page'     => __('Page', 'luciditi-age-assurance'),
				'redirect' => __('Redirect', 'luciditi-age-assurance'),
			);

			if ($underage_fallback_enabled) {
			?>

				<tr class="form-field" hidden="true">
					<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('redirection')); ?>"><?php esc_html_e('Under-Age Redirection', 'luciditi-age-assurance'); ?></label></th>
					<td>
						<select id="<?php echo esc_attr(luciditi_prepare_key('redirection')); ?>" name="<?php echo esc_attr(luciditi_prepare_key('redirection')); ?>" data-conditional-rules="<?php echo esc_html($show_when_enabled); ?>">
							<?php foreach ($redirect_options as $key => $label) { ?>
								<option value="<?php echo $key; ?>" <?php selected($redirection, $key); ?>><?php echo $label; ?></option>
							<?php } ?>
						</select>
					</td>
				</tr>

				<?php
				$condition_page = get_mf_conditional_rules(
					'show',
					array(
						array(
							'name'     => luciditi_prepare_key('enabled'),
							'operator' => 'is',
							'value'    => 'yes',
						),
						array(
							'name'     => luciditi_prepare_key('redirection'),
							'operator' => 'is',
							'value'    => 'page',
						),
					),
					'and'
				);

				$condition_redirect = get_mf_conditional_rules(
					'show',
					array(
						array(
							'name'     => luciditi_prepare_key('enabled'),
							'operator' => 'is',
							'value'    => 'yes',
						),
						array(
							'name'     => luciditi_prepare_key('redirection'),
							'operator' => 'is',
							'value'    => 'redirect',
						),
					),
					'and'
				);

				// Get all WordPress pages
				$pages       = get_pages(array('post_type' => 'page'));
				$pages_array = array();
				foreach ($pages as $page) {
					$pages_array[$page->ID] = $page->post_title;
				}
				?>

				<tr class="form-field" hidden="true">
					<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('fallback_page')); ?>"><?php esc_html_e('Select Page', 'luciditi-age-assurance'); ?></label></th>
					<td>
						<select id="<?php echo esc_attr(luciditi_prepare_key('fallback_page')); ?>" name="<?php echo esc_attr(luciditi_prepare_key('fallback_page')); ?>" data-conditional-rules="<?php echo esc_html($condition_page); ?>">
							<option value="" <?php echo empty(luciditi_get('fallback_page', $redirection)) ? 'selected' : ''; ?>>-- Select a page --</option>
							<?php foreach ($pages_array as $id => $title) : ?>
								<option value="<?php echo esc_attr($id); ?>" <?php selected($id, $fallback_page); ?>><?php echo esc_html($title); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr class="form-field" hidden="true">
					<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('fallback_url')); ?>"><?php esc_html_e('Redirect URL', 'luciditi-age-assurance'); ?></label></th>
					<td>
						<input type="url" id="<?php echo esc_attr(luciditi_prepare_key('fallback_url')); ?>" name="<?php echo esc_attr(luciditi_prepare_key('fallback_url')); ?>" value="<?php echo esc_url($fallback_url); ?>" data-conditional-rules="<?php echo esc_html($condition_redirect); ?>" />
					</td>
				</tr>
			<?php
			}
		}
		/**
		 * Save custom fields for product categories on WooCommerce
		 *
		 * @since    1.0.3
		 * @access   public
		 */
		public function wc_product_category_fileds_save($term_id)
		{
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$age_restriction_enabled = isset($_POST[luciditi_prepare_key('enabled')]) ? 'yes' : 'no';
			update_term_meta(
				$term_id,
				luciditi_prepare_key('enabled'),
				esc_attr($age_restriction_enabled)
			);
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if (isset($_POST[luciditi_prepare_key('minimum_age')])) {
				update_term_meta(
					$term_id,
					luciditi_prepare_key('minimum_age'),
					sanitize_text_field($_POST[luciditi_prepare_key('minimum_age')])
				);
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if (isset($_POST[luciditi_prepare_key('redirection')])) {
				update_term_meta(
					$term_id,
					luciditi_prepare_key('redirection'),
					sanitize_text_field($_POST[luciditi_prepare_key('redirection')])
				);
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if (isset($_POST[luciditi_prepare_key('fallback_page')])) {
				update_term_meta(
					$term_id,
					luciditi_prepare_key('fallback_page'),
					absint($_POST[luciditi_prepare_key('fallback_page')])
				);

				// Make sure we store the fallback page to our fallback_pages list, or remove it if no longer set.
				$redirect_type = get_term_meta($term_id, luciditi_prepare_key('redirection'), true);
				if ('page' === $redirect_type && !empty($_POST[luciditi_prepare_key('fallback_page')])) {
					luciditi_update_fallback_pages_option($_POST[luciditi_prepare_key('fallback_page')], 'product_cat', $term_id);
				} else {
					luciditi_remove_fallback_page_option('product_cat', $term_id);
				}
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if (isset($_POST[luciditi_prepare_key('fallback_url')])) {
				update_term_meta(
					$term_id,
					luciditi_prepare_key('fallback_url'),
					esc_url($_POST[luciditi_prepare_key('fallback_url')])
				);
			}
		}

		/**
		 * Create a custom column on the product list page
		 *
		 * @since    1.0.3
		 * @access   public
		 */
		public function wc_product_columns($columns)
		{
			$columns['luciditi_age_restricted'] = esc_html__('Age Restricted', 'luciditi-age-assurance');
			return $columns;
		}
		/**
		 * Add content to out custom column(s) on the product list page
		 *
		 * @since    1.0.3
		 * @access   public
		 */
		public function wc_product_columns_output($column, $product_id)
		{
			if ('luciditi_age_restricted' == $column) {
				if (luciditi_wc_is_product_restricted($product_id) || luciditi_wc_is_product_category_restricted($product_id)) {
					echo '<strong>' . luciditi_wc_get_product_required_min_age($product_id) . '</strong>';
				} else {
					esc_html_e('N/A', 'luciditi-age-assurance');
				}
			}
		}

		/**********************************************************************
		 ************************** Public functions **************************
		 *** All the public functions that are used for front end porpuses
		 **********************************************************************/

		/**
		 * Handle API callbacks ( successful and failed verifications ).
		 *
		 * @since    1.0.0
		 */
		public function maybe_handle_api_callback()
		{
			if (isset($_GET['luciditi_aa_precheck'])) {
				// Handle pre-check callback ( check whether the user has already verified using SDK )

				$body = wp_kses_post(file_get_contents('php://input'));
				// $body = "{\"callbackId\":\"1088162536-10\",\"requiredAge\":18,\"meetsAgeRequirement\":true,\"completedAgeAssuranceAges\":[],\"hasCompletedIdv\":false}";
				// Decode the JSON object
				$message = json_decode($body, true);
				// Make sure it's fully decoded
				if (is_string($message)) {
					$message = json_decode($message, true);
				}
				// Make sure it's an array
				$message = (array)$message;

				// Make sure we've received a callback ID which represent the user session ID
				if (!empty($message)) {
					$session_id                  = $message['callbackId'];
					$completed_age_verifications = (array)$message['completedAgeAssuranceAges'];
					// Now check if the user meets the age requirements ( previosuly verified )
					// If yes, we'll change the user 'pending' session to a valid one.
					if (isset($message['meetsAgeRequirement']) && $message['meetsAgeRequirement'] == true) {

						// Get the session from db
						$session      = luciditi_session()->get_session($session_id);
						$session_type = luciditi_session()->get_session_identifiers($session_id, 'type');

						// Prepare the new `valid` session data
						$general          = luciditi_get_option('general', array());
						$retention_period = luciditi_get('retention_period', $general, 365);
						$min_age          = luciditi_get_minimum_age_from_session($session);
						if (!empty($completed_age_verifications) && absint(max($completed_age_verifications)) > absint($min_age)) {
							$min_age = max($completed_age_verifications);
						}

						$session_state_data = array(
							'code_id' => rand(),
							'data'    => array(
								'info' => 'Completed age assurance externally, verified using SDK.',
								'min_age' => absint($min_age),
							),
							'creation_date' => time(),
							'expiry_date'   => time() + intval(60 * 60 * 24 * absint($retention_period)),
						);

						// If there is an existing tmp session, update it. Otherwise, create a new one.
						// Note: an `external` session simply means that we checked lucidit SDK for exiting verified record of the user using JS.
						// if the SDK found a valid record, it will trigger this callback and we will save this as an `external` temporary session.
						// This is not enough to allow the user to access, so we already have an AJAX call to verify if this `external` temporary
						// session has been created, and it will save a presistant session for the user because we only save this if the user passed.
						// The AJAX function in question is: `ajax_validate_external_session`
						if ($session) {
							luciditi_session()->update_session(
								$session_id,
								array(
									'state'      => 'external',
									'state_data' => $session_state_data,
								),
								true
							);
						} else {
							// Create a new temp session for the user
							luciditi_session()->create_session(
								array(
									'session_key'    => $session_id,
									'session_expiry' => time() + 3600, // keep it for 1 hour
									'state'          => 'external',
									'state_data'     => $session_state_data,
								),
								true
							);
						}
					}
					exit();
				}
			} else if (isset($_GET['luciditi_aa'])) {
				// Handle age assurance/verifcation callback
				$session_id = luciditi_get('luciditi_aa');
				if (!empty($session_id)) {
					// Get the session from db
					$session      = luciditi_session()->get_session($session_id);
					$session_type = luciditi_session()->get_session_identifiers($session_id, 'type');
					if (!empty($session)) {

						// Read the input stream
						$body = wp_kses_post(file_get_contents('php://input'));
						// $body = "{\"UserId\":\"\",\"MsgType\":50,\"MsgData\":\"{\\\"Added\\\":\\\"2023-05-10T22:06:06.4884461+01:00\\\",\\\"CodeId\\\":\\\"9ec74302cc204fa98f0c83e539954373\\\",\\\"Verified\\\":true,\\\"Info\\\":\\\"High confidence over target age using 5 year threshold\\\",\\\"StepUpType\\\":0,\\\"StepUpInfo\\\":\\\"\\\"}\",\"MsgDataEncrypted\":false,\"MsgDataEncKey\":null,\"MsgDataEncIV\":null}";

						// Decode the JSON object
						$message = json_decode($body, true);
						// Make sure it's fully decoded
						if (is_string($message)) {
							$message = json_decode($message, true);
						}

						// Verify message type
						if (absint($message['MsgType']) === 50) {
							// Pull the message data
							$message_data = json_decode($message['MsgData']);
							$verified     = $message_data->Verified ?? false;
							$code_id      = $message_data->CodeId ?? false;

							// Prepare the new `valid` session data
							$general          = luciditi_get_option('general', array());
							$retention_period = luciditi_get('retention_period', $general, 365);
							$session_state_data = array(
								'code_id' => $code_id,
								'data'    => array(
									'info'    => $message_data->Info,
									'min_age' => luciditi_get_minimum_age_from_session($session),
								),
								'creation_date' => strtotime($message_data->Added),
								'expiry_date'   => time() + intval(60 * 60 * 24 * absint($retention_period)),
							);

							// Evaluate the session and make sure to treat it depending on the session type
							$tmp_session    = false;
							$tmp_session_id = false;
							$updated        = false;

							if ('failed_session' === $session_type && isset($session->data) && !empty($session->data['tmp_session'])) {
								$tmp_session    = luciditi_session()->get_session($session['tmp_session']);
								$tmp_session_id = 'tmp_' . $tmp_session->session_id;

								// If no temporary session found for this main session, we'll update the main one with error message
								if (!$tmp_session) {
									luciditi_session()->update_session(
										$session_id,
										array(
											'state' => 'failed',
											'data' => array(
												'info' => esc_html__('A temporary session was not detected for this entry.', 'luciditi-age-assurance'),
											),
										)
									);
									// Bail out here
									exit();
								}
							} elseif ('tmp_session' === $session_type && isset($session->state_data)) {
								$tmp_session    = $session;
								$tmp_session_id = 'tmp_' . $session->session_id;
							}

							// Validate and verify
							if ($verified && $tmp_session) {
								// Check the validity of CodeId against the database to prevent manipulation
								// Here we should evaluate temp session details against the returned data to confirm
								// this is a valid request and not a fake request
								if ($code_id == $session->state_data['codeId']) {
									// Update tmp session state
									$updated = luciditi_session()->update_session(
										$session_id,
										array(
											'state'      => 'valid',
											'state_data' => $session_state_data,
										),
										true
									);
								}
							}

							// If the tmp session wasn't updated, update it with `failed` state
							if (!$updated) {
								// Update tmp session state
								luciditi_session()->update_session(
									$tmp_session_id,
									array(
										'state'      => 'failed',
										'state_data' => $session_state_data,
									),
									true
								);
							}
						}

						exit();
					}
				}
			}
		}
		/**
		 * Handle age verification if out conditions are met.
		 *
		 * @since    1.0.0
		 */
		public function maybe_verify_age()
		{
			// Bail out if we are in the admin view, login page, or if luciditi age assurance is not enabled.
			$enable_mode = luciditi_get_option('enable_mode', '');
			if (is_admin() || empty($enable_mode) || 'disabled' === $enable_mode || 'wp-login.php' === $GLOBALS['pagenow']) {
				return;
			}

			// Bail out if age assurance is enabled for WooCommerce, but we are not on the checkout page
			$restrict_woocommerce_checkout = false;
			if ('woocommerce' === $enable_mode) {
				if (class_exists('woocommerce')) {
					if (!is_checkout() || is_order_received_page() || (is_checkout() && !luciditi_wc_cart_has_restricted_product())) {
						return;
					} else {
						$restrict_woocommerce_checkout = true;
					}
				}
			}

			// Bail out if the age assurance is enabled for specific pages, but not for this page
			$restrict_current_page = false;
			if ('page_based' === $enable_mode) {
				// TODO: write the logic for handling page based age restriction, then make a check here
			}

			// If the current user is a bot, admin, logged in verified user, admin, or a previously verified no need to run the verification.
			$minimum_age       = luciditi_get_required_minimum_age($enable_mode);
			$user_type         = luciditi_get_user_type();
			$user_verfieid_age = 0;
			if (in_array($user_type, array('bot', 'admin', 'logged_in_verified_user', 'verified_user'), true) && !isset($_GET['resetagecheck'])) { // phpcs:ignore
				// If the current page is specifically restricted and the user has already verified, we need to make sure their age is allowed for this specific page.
				if ($restrict_woocommerce_checkout || $restrict_current_page) {
					// Check the logged in user age against the minimum required age for the current page, or for WooCommerce products
					if ('logged_in_verified_user' === $user_type) {
						$user_verfieid_age = luciditi_get_verified_minimum_age_from_usermeta();
						if ($user_verfieid_age >= $minimum_age) {
							return;
						}
					} elseif ('verified_user' === $user_type) {
						$session    = luciditi_session()->get_session_cookie();
						$session_id = $session[0] ?? false;
						if ($session_id) {
							$session           = luciditi_session()->get_session($session_id);
							$user_verfieid_age = luciditi_get_minimum_age_from_session($session);
							if ($user_verfieid_age >= $minimum_age) {
								return;
							}
						}
					} else {
						return;
					}
				} else {
					return;
				}
			}

			// Check if the current page is a fallback page only if the current request is doesn't have WooCommerce or page based restriction applied
			if (!$restrict_woocommerce_checkout || !$restrict_current_page) {
				// Initialize an array to hold fallback page IDs
				$fallback_pages = array();
				// If the current page is selected as a fallback, we'll not apply any logic to it.
				$underage_redirect         = luciditi_get_option('underage_redirection', array());
				$underage_fallback_enabled = 'yes' === luciditi_get('enabled', $underage_redirect);
				$underage_fallback_is_page = 'page' === luciditi_get('fallback_option', $underage_redirect);
				$underage_fallback_page    = absint(luciditi_get('fallback_page', $underage_redirect));
				if ($underage_fallback_enabled && $underage_fallback_is_page) {
					// Add the fallback page from our settings to the array
					$fallback_pages[] = $underage_fallback_page;
					// If the current enable mode is either wooCommerce or page_based, we will loop through all the fallback pages that are set on restricted products or pages.
					// Note that this might not be necessary since site-wide restriction is no implemented when using 'page_based' or 'woocommerce' mode
					// However, this might be benificial in the future, or in rare cases when there is a conflict.
					if (in_array($enable_mode, array('woocommerce', 'page_based'), true)) {
						$stored_fallback_pages = luciditi_get_option('fallback_pages', array());
						// Ensure stored fallback pages are returned as array values only (in case it's an associative array)
						$stored_fallback_page_values = array_values($stored_fallback_pages);
						// Merge and remove duplicates
						$fallback_pages = array_unique(array_merge($fallback_pages, $stored_fallback_page_values));
					}
					// Check and allow the user to access the current page is it's in the list of fallback_pages
					foreach ($fallback_pages as $fallback_page) {
						if ($fallback_page > 0) {
							$current_url = luciditi_get_current_url();
							if (
								is_page($fallback_page) ||
								url_to_postid($current_url) === $fallback_page ||
								luciditi_url_to_postid($current_url) === $fallback_page
							) {
								return;
							}
						}
					}
				}
			}

			// If the user is allowed without age assurance based on their location, we'll NOT continue with the logic.
			$user_geo = $this->get_current_user_geolocation_rule();
			if ('allowed_without_aa' === $user_geo['rule']) {
				// We'll create a valid session for the user to avoid geolocation check on sub-sequent visits
				luciditi_session()->create_session(
					array(
						'data' => array(
							'info' => 'Allowed without age assurance by geolocation.',
						),
						'state' => 'valid',
					),
					false,
					true,
				);

				// Now return ( this will allow them in )
				return;
			}

			/**
			 * Get and prepare necessary data
			 *
			 */

			// Check if the user is disallowed based on their location
			$is_user_location_disallowed = false;
			if ('disallowed' === $user_geo['rule']) {
				$is_user_location_disallowed = true;
			}

			// Get the session cookie
			$session = luciditi_session()->get_session_cookie();
			// Prepare the defaults for "landing pages and styling" settings
			$landing_defaults = array(
				'primary_color'              => '#0A0D19',
				'secondary_color'            => '#337dff',
				'text_color'                 => '#ffffff',
				'start_btn_text'             => esc_html__('Start', 'luciditi-age-assurance'),
				'retry_btn_text'             => esc_html__('Retry', 'luciditi-age-assurance'),
				'self_declare_btn_txt'       => esc_html__('I\'m under {min_age}', 'luciditi-age-assurance'),
				'first_time_msg'             => esc_html__('This site contains age restricted content so as a first time visitor, we\'ll need to verify that you meet the minimum age requirements.', 'luciditi-age-assurance'),
				'returning_msg'              => esc_html__('From time to time we need to re-verify that you meet the minumum age requirements.', 'luciditi-age-assurance'),
				'failed_validation_msg'      => esc_html__('Unfortunately, we were unable to verify your age.  If you havent done so already, you can retry using a valid ID document.', 'luciditi-age-assurance'),
				'disallowed_location_msg'    => esc_html__('Unfortunately, access from this territory is not permitted.', 'luciditi-age-assurance'),
				'disallowed_vpn_msg'         => esc_html__('It looks like you are using a VPN.  Please disable it and refresh the page in order to gain access.', 'luciditi-age-assurance'),
				'disallowed_underage_msg'    => esc_html__('Access to this site by users under the age of {min_age} is not permitted.', 'luciditi-age-assurance'),
				'wc_first_time_msg'          => esc_html__('Before proceeding with your purchase, we need to verify your age due to age restrictions on the following product(s): ', 'luciditi-age-assurance'),
				'wc_failed_validation_msg'   => esc_html__('Our records indicate that you are below the required age for the following product(s), hence you cannot proceed with the checkout: ', 'luciditi-age-assurance'),
				'wc_disallowed_underage_msg' => esc_html__('You have declared yourself as under the required age. Access to the following product(s) is restricted: ', 'luciditi-age-assurance'),
				'wc_disallowed_location_msg' => esc_html__('Unfortunately, submitting orders from this territory is not permitted.', 'luciditi-age-assurance'),
			);

			// Pull "landing pages and styling" settings from database
			$landing_stored = luciditi_get_option('landing', array());
			// Merge stored options with defaults. Stored options will override defaults if they exist.
			$landing_settings = wp_parse_args($landing_stored, $landing_defaults);
			// Prepare the logo URL
			$logo = luciditi_get('logo', $landing_settings);
			if (empty($logo)) {
				$logo = _LUCIDITI_AA_URL . '/includes/assets/img/luciditi-brand.svg';
			}

			// Prepare the "fallback" url for self-declaring users and aborted verifications
			$underage_redirect_link = '';
			if ($underage_fallback_enabled) {
				if (luciditi_get('fallback_option', $underage_redirect) == 'page') {
					$underage_redirect_link = get_the_permalink(luciditi_get('fallback_page', $underage_redirect));
				} elseif ('redirect' === luciditi_get('fallback_option', $underage_redirect)) {
					$underage_redirect_link = luciditi_get('fallback_url', $underage_redirect);
				}

				// Maybe, modify redirect link if the current page is WooCommerce checkout, or page_based restricted page
				// Note that the empty string value for the link indicates a default under-age message instead of a redirect, so we should check correctly using `false !==`.
				if ($restrict_woocommerce_checkout) {
					$product_redirect_link = luciditi_wc_maybe_get_product_redirect_link();
					if (false !== $product_redirect_link) {
						$underage_redirect_link = $product_redirect_link;
					}
					// Check if the new `$underage_redirect_link` is an empty string "". If yes,
					// it means that the option 'none' has been selected, in this case for WooCommerce,
					// we will just return the user to the previous page instead of default behaviour.
					if ('' === $underage_redirect_link) {
						$underage_redirect_link = luciditi_get_referer();
					}
				} elseif ($restrict_current_page) {
					$page_redirect_link = luciditi_maybe_get_page_redirect_link(get_the_ID());
					if (false !== $page_redirect_link) {
						$underage_redirect_link = $page_redirect_link;
					}
				}
			}

			// Make sure the redirect link is an actual URL
			$underage_redirect_link = !empty($underage_redirect_link) && filter_var($underage_redirect_link, FILTER_VALIDATE_URL) ? $underage_redirect_link : '#';

			// Prepare the template type to pull the correct template based on user type
			if ($is_user_location_disallowed) {
				// If the user is not allowed, we'll not evaluate the "user_type"
				// will immediately set "disallowed" template.
				$template_type = 'disallowed';
				if ('vpn_detected' === $user_geo['type']) {
					$message = luciditi_get('disallowed_vpn_msg', $landing_settings);
				} else {
					if ($restrict_woocommerce_checkout) {
						$message = luciditi_get('wc_disallowed_location_msg', $landing_settings);
					} else {
						$message = luciditi_get('disallowed_location_msg', $landing_settings);
					}
				}
			} else {
				switch ($user_type) {
					case 'returning_unverified_visitor':
						$template_type            = 'returning';
						$button_text              = luciditi_get('start_btn_text', $landing_settings);
						$self_declare_button_text = str_replace('{min_age}', $minimum_age, luciditi_get('self_declare_btn_txt', $landing_settings));

						if ($restrict_woocommerce_checkout) {
							$message  = luciditi_get('wc_first_time_msg', $landing_settings);
							$message .= luciditi_wc_get_restricted_products_list($user_verfieid_age);
						} else {
							$message = luciditi_get('returning_msg', $landing_settings);
						}
						break;
					case 'failed_validation_visitor':
						$template_type            = 'failed-validation';
						$button_text              = luciditi_get('retry_btn_text', $landing_settings);
						$self_declare_button_text = str_replace('{min_age}', $minimum_age, luciditi_get('self_declare_btn_txt', $landing_settings));

						if ($restrict_woocommerce_checkout) {
							$message  = luciditi_get('wc_failed_validation_msg', $landing_settings);
							$message .= luciditi_wc_get_restricted_products_list($user_verfieid_age);
						} else {
							$message = luciditi_get('failed_validation_msg', $landing_settings);
						}
						break;
					case 'self_declaring_under_age':
						$template_type = 'disallowed';

						if ($restrict_woocommerce_checkout) {
							$message = str_replace('{min_age}', $minimum_age, luciditi_get('wc_disallowed_underage_msg', $landing_settings));
						} else {
							$message = str_replace('{min_age}', $minimum_age, luciditi_get('disallowed_underage_msg', $landing_settings));
						}
						break;
					default:
						$template_type            = 'first-time';
						$button_text              = luciditi_get('start_btn_text', $landing_settings);
						$self_declare_button_text = str_replace('{min_age}', $minimum_age, luciditi_get('self_declare_btn_txt', $landing_settings));
						if ($restrict_woocommerce_checkout) {
							$message  = luciditi_get('wc_first_time_msg', $landing_settings);
							$message .= luciditi_wc_get_restricted_products_list($user_verfieid_age);
						} else {
							$message = luciditi_get('first_time_msg', $landing_settings);
						}
						break;
				}
			}

			/**
			 * Start rendering the page.
			 *
			 */

			// Disable caching
			nocache_headers();
			// Force "Permissions-Policy" headers to make sure camera is not restricted server side.
			// Note: this is not guaranteed to work in all environments, most of the time, server headers will have priority.
			if (!headers_sent()) {
				header('Permissions-Policy: fullscreen=(self)', true);
			}

			// Call the function responsible for registering scripts and styles
			// Since we'll bail out at the end of this method call, the hook
			// responsible for calling this function is never triggered, so
			// we have to do it manually.
			wp_enqueue_scripts();

			// Pull the our template names
			$header = luciditi_get_template_filename('components', 'header');
			$body   = luciditi_get_template_filename('body', $template_type);
			$footer = luciditi_get_template_filename('components', 'footer');
			// Prepare data to pass to the header template
			$header_args = array(
				'mode'            => $enable_mode,
				'type'            => $template_type,
				'session_id'      => $session[0] ?? '',
				'min_age'         => $minimum_age,
				'primary_color'   => luciditi_get('primary_color', $landing_settings),
				'secondary_color' => luciditi_get('secondary_color', $landing_settings),
				'text_color'      => luciditi_get('text_color', $landing_settings),
			);
			// Prepare data to pass to the body template
			if ('disallowed' === $template_type) {
				$body_args = array(
					'mode'    => $enable_mode,
					'logo'    => $logo,
					'message' => $message,
				);
			} else {
				$body_args = array(
					'mode'                     => $enable_mode,
					'logo'                     => $logo,
					'message'                  => $message,
					'button_text'              => $button_text,
					'self_declare_button_text' => $self_declare_button_text,
					'self_declare_enabled'     => $underage_fallback_enabled,
					'self_declare_redirect'    => $underage_redirect_link,
				);
			}
			// Prepare data to pass to the footer template
			$footer_args = array(
				'mode'                => $enable_mode,
				'geolocation_results' => $user_geo,
			);

			// Display header
			luciditi_locate_template_html($header, $header_args, true);
			// Display body
			luciditi_locate_template_html($body, $body_args, true);
			// Display footer
			luciditi_locate_template_html($footer, $footer_args, true);

			// Stop the script execution here after displaying out template.
			exit();
		}


		/**********************************************************************
		 ************************** AJAX functions **************************
		 *** All the ajax functions that are used in this plugin.
		 **********************************************************************/

		/**
		 *  Handle session reset
		 *
		 * @since    1.0.0
		 * @access   public
		 */
		public function ajax_reset_session()
		{
			// If the user is logged in, delete also their age meta
			luciditi_maybe_clear_user_age_meta();
			// Reset the daily cookie
			luciditi_maybe_clear_daily_cookie();
			// Reset the current session cookie
			luciditi_session()->unset_session_cookie();
			// Delete the session from db
			$session_id = luciditi_post('session_id');
			if (!empty($session_id)) {
				luciditi_session()->delete_session($session_id);
			}
			// Send the response as JSON
			wp_send_json(
				array(
					'success' => true,
					'message' => esc_html__('Session was reset.', 'luciditi-age-assurance'),
				)
			);
		}

		/**
		 *  Handle tmp session validation where allowed
		 *
		 * @since    1.0.0
		 * @access   public
		 */
		public function ajax_validate_external_session()
		{
			// Get the posted session id
			$verified_age = luciditi_post('verified_age');
			// If we are on a local environment, we will pass the validation since "pre-check" callback can't be triggered externally.
			if (in_array($_SERVER['HTTP_HOST'], array('localhost', '127.0.0.1', '::1'))) {

				// If the user is logged in, we need to make sure their stored age is cleared, so the new one can be registered later after reload
				luciditi_maybe_clear_user_age_meta();
				// Clear daily cookie
				luciditi_maybe_clear_daily_cookie();

				wp_send_json(
					array(
						'success' => luciditi_session()->create_session(
							array(
								'data'  => array(
									'info'    => 'Completed age assurance externally, verified using SDK on localhost.',
									'min_age' => absint($verified_age),
								),
								'state' => 'valid',
							),
							false,
							true
						),
						'message' => esc_html__('Localhost environment detected. Success!', 'luciditi-age-assurance'),
					)
				);
			}

			// Start the validation
			$result = array(
				'success' => false,
				'message' => esc_html__('There is no temporary session with the given ID. The provided details are wrong, or the callback handler might have not been triggered.', 'luciditi-age-assurance'),
			);

			// Get the posted session id
			$session_id = luciditi_post('session_id');
			// Retrieve the session using key
			$session = $session_id ? luciditi_session()->get_session($session_id) : false;
			// If there is no existing sessions with the given ID,
			// it means the session was recently created from the callback handler.
			// So we'll try to retrieve the session using `session_key` field instead.
			if (!$session) {
				global $wpdb;
				$table = luciditi_session()->_tmp_table;
				$query = $wpdb->prepare("SELECT * FROM $table WHERE session_key = %s", $session_id);
				$query_result = $wpdb->get_row($query);
				if (!empty($query_result)) {
					$query_result->state_data = maybe_unserialize($query_result->state_data);
					$session                  = $query_result;
				}
			}

			// Now check if the tmp session is available, and create a presistant session from it.
			// This will also update the user cookie with the new session ID.
			if ($session && isset($session->state) && $session->state == 'external') {
				// If the session is no longer pending, create a persistent session
				// and delete the current tmp session. Finally, refresh the page.
				$session_data = array();
				if (empty($session->state_data['data'])) {
					$session_data = array(
						'info'    => 'Completed age assurance externally, verified using the SDK.',
						'min_age' => absint($verified_age),
					);
				} else {
					if (is_array($session->state_data['data'])) {
						$session_data            = $session->state_data['data'];
						$session_data['min_age'] = absint($verified_age);
					} else {
						$session_data = array(
							'info'    => $session->state_data['data'],
							'min_age' => absint($verified_age),
						);
					}
				}
				$session_data = array(
					'data'  => $session_data,
					'state' => 'valid',
				);

				// Delete the tmp session
				luciditi_session()->delete_session('tmp_' . $session->session_id);
				// If the user is logged in, we need to make sure their stored age is cleared, so the new one can be registered later after reload
				luciditi_maybe_clear_user_age_meta();
				// Clear daily cookie
				luciditi_maybe_clear_daily_cookie();
				// Create the persistent session
				$result['success'] = luciditi_session()->create_session($session_data, false, true);
				$result['message'] = $result['success'] ? esc_html__('Session validated.', 'luciditi-age-assurance') : esc_html__('Session was not validated.', 'luciditi-age-assurance');
			}

			// Convert the response to json format and send final result.
			wp_send_json($result);
		}
		/**
		 *  Handle API authentication using AJAX
		 *
		 * @since    1.0.0
		 * @access   public
		 */
		public function ajax_api_auth()
		{
			$result = array(
				'success' => false,
				'message' => esc_html__('Request failed.', 'luciditi-age-assurance'),
			);

			// Get the posted session id
			$session_id = luciditi_post('session_id');
			// Make an API request to authenticate the user and prepare data for a tmp session.
			try {
				$auth = luciditi_api_auth($session_id);
				if (isset($auth['accessToken'])) {
					$result['success'] = true;
					$result['session'] = $auth;
				} else {
					throw new Exception(esc_html__('Authentication failed.', 'luciditi-age-assurance'));
				}
			} catch (Exception $e) {
				$result['message'] = $e->getMessage();
			}
			// Convert the response to json format and send final result.
			wp_send_json($result);
		}
		/**
		 *  Handle temp session retrieval using AJAX.
		 *
		 * @since    1.0.0
		 * @access   public
		 */
		public function ajax_get_tmp_session()
		{
			$result = array(
				'success' => false,
				'message' => esc_html__('Your session could not be retrieved.', 'luciditi-age-assurance'),
			);

			$session_id   = luciditi_post('session_id');
			$session_type = luciditi_session()->get_session_identifiers($session_id, 'type');

			// Retrieve the session
			$session = luciditi_session()->get_session($session_id);

			// If the session type is `failed`, we need to treat it differently
			if ('failed_session' === $session_type) {
				if (!empty($session->data['tmp_session'])) {
					$session = luciditi_session()->get_session($session->data['tmp_session']);
				} else {
					$session = false;
				}
			}

			if (false !== $session && isset($session->session_expiry) && $session->session_expiry > time()) {
				$result['success'] = true;
				$result['session'] = array(
					'sessionKey'     => $session->session_key,
					'sessionIv'      => $session->session_iv,
					'accessToken'    => $session->access_token,
					'secretKeyClear' => $session->secret_key_clear,
				);
			} else {
				// If the tmp retrieved session is outdated, delete it
				if (isset($session->session_expiry) && $session->session_expiry > time()) {
					luciditi_session()->delete_session($session->session_id);
				}
				// Since no valid session is found, send a new auth request to create a new session
				$auth = luciditi_api_auth($session_id);
				if (is_array($auth) && isset($auth['accessToken'])) {
					$result['success'] = true;
					$result['session'] = $auth;
				} elseif (false !== $auth) {
					$result['message'] = $auth;
				}
			}
			// Convert the response to json format and send final result.
			wp_send_json($result);
		}
		/**
		 *  Handle temp retrieval using AJAX.
		 *
		 * @since    1.0.0
		 * @access   public
		 */
		public function ajax_get_signup_data()
		{
			$stepup_settings  = luciditi_get_option('stepup', array());
			$api_settings     = luciditi_get_option('api', array());
			$result = array(
				'success' => true,
				'data' => array(
					'min_age'          => luciditi_get_required_minimum_age(null, true),
					'stepup_with_id'   => luciditi_get('with_id', $stepup_settings, 'yes'),
					'stepup_with_data' => luciditi_get('with_data', $stepup_settings, false),
					'caller_username'  => luciditi_get('caller_username', $api_settings, get_bloginfo('name')),
				),
				'message' => esc_html__('Your sign up data could not be retrieved.', 'luciditi-age-assurance'),
			);

			// Make sure the `min_age` is never empty
			if (empty($result['data']['min_age'])) {
				$result['success'] = false;
			}

			// Convert the response to json format and send final result.
			wp_send_json($result);
		}
		/**
		 *  Save the code ID for temp session.
		 *
		 * @since    1.0.0
		 * @access   public
		 */
		public function ajax_save_temp_session_codeid()
		{

			$result = array(
				'success' => false,
				'message' => esc_html__('A start up code ID was not detected.', 'luciditi-age-assurance'),
			);

			// Prepare required data. Note that min_age is also required for the overall functionality of the plugin
			// failing to to provide a minimum age will cause the system to fallback to default age, which might result
			// in inconsistent behaviour when dealing with products or pages with different age requirements for example.
			$session_id = luciditi_post('session_id');
			$code_id    = luciditi_post('code_id');
			$min_age    = luciditi_post('min_age');

			if (!empty($session_id) && !empty($code_id)) {
				// Get the session record from the database
				$session = luciditi_session()->get_session($session_id);
				// If this appears to be failed session, we need to use it to pull the existing tmp session
				// We also need to make sure we have the correct session ID assigned to `$session_id`.
				// Note that `$session_id` in this case should be a string starting with `tmp_`.
				// Failing to update data or provice correct data, will cause failure saving the code ID to the temp session.
				if (isset($session->data) && !empty($session->data['tmp_session'])) {
					$session_id = $session->data['tmp_session'];
					$session    = luciditi_session()->get_session($session->data['tmp_session']);
				}

				$state_data = $session->state_data;
				if (!empty($state_data) && is_array($state_data)) {
					$state_data['codeId'] = $code_id;
					$state_data['min_age'] = $min_age;
					$updated              = luciditi_session()->update_session(
						$session_id,
						array(
							'state'      => 'pending',
							'state_data' => $state_data,
						),
						true
					);

					if ($updated) {
						$result['success'] = true;
					} else {
						$result['message'] = esc_html__('Sorry, your start up code ID could not be saved.', 'luciditi-age-assurance');
					}
				} else {
					$result['message'] = esc_html__('Sorry, we were unable to store your start up code ID.', 'luciditi-age-assurance');
				}
			}
			// Convert the response to json format and send final result.
			wp_send_json($result);
		}
		/**
		 *  Set the temporary session state to 'failed'.
		 *
		 * @since    1.0.0
		 * @access   public
		 */
		public function ajax_set_session_state_failed()
		{

			$result = array(
				'success' => true,
			);

			$session_id = luciditi_post('session_id');

			if (!empty($session_id)) {
				$session = luciditi_session()->get_session($session_id);
				if ($session) {
					$identifiers    = luciditi_session()->get_session_identifiers($session_id);
					$is_tmp_session = 'tmp_session' == $identifiers['type'] ? true : false;
					if ($is_tmp_session) {
						// Update an existing temp session
						luciditi_session()->update_session(
							$session_id,
							array(
								'state'      => 'failed',
								'state_data' => array(
									'data' => array(
										'type' => 'aborted',
										'info' => esc_html__('Aborted', 'luciditi-age-assurance'),
									),
								),
							),
							true
						);
					} else {
						// Update the existing presistant session
						luciditi_session()->update_session(
							$session_id,
							array(
								'data' => array(
									'type' => 'aborted_or_self_declared',
									'info' => esc_html__('Aborted or self-declared as under-age more than once', 'luciditi-age-assurance'),
								),
							)
						);
					}
				} else {
					// Create a temp session
					luciditi_session()->create_session(
						array(
							'state'      => 'failed',
							'state_data' => array(
								'data' => array(
									'type' => 'self_declaring_under_age',
									'info' => esc_html__('Self-declared as under-age', 'luciditi-age-assurance'),
								),
							),
						),
						true,
						true
					);
				}
			}

			// Convert the response to json format and send final result.
			wp_send_json($result);
		}
		/**********************************************************************
		 ************************** Helper functions **************************
		 *** All the non-hooked functions that are used in the
		 *** context of this class by other hooked functions
		 **********************************************************************/

		/**
		 *  Return the strings to use for errors and javascript.
		 *
		 * @since    1.0.0
		 * @access   public
		 */
		public function get_current_user_geolocation_rule()
		{
			$available_rules  = luciditi_get_geo_rules();
			$default_rule     = isset($available_rules['available_statuses']) ? 'allowed_with_aa' : array_key_first($available_rules);
			$default_response = array(
				'type' => 'location_ignored',
				'rule' => $default_rule,
			);
			// Check if geolocation configurations are enabled
			$geolocation_enabled = luciditi_get_option('geolocation_enabled', 'no');
			if ('yes' === $geolocation_enabled) {

				// Prepare variales and data
				$geo_conf                 = luciditi_get_option('geographical_conf');
				$access_rules             = luciditi_get('access_rules', $geo_conf);
				$rest_of_world_rule       = luciditi_get('rest_of_world_rule', $geo_conf, $default_rule);
				$undetected_location_rule = luciditi_get('undetected_location_rule', $geo_conf, $default_rule);
				$prevent_vpn_access       = luciditi_get('prevent_vpn_access', $geo_conf, 'no') === 'yes' ? true : false;

				// Initialize cURL for Abstract API
				try {

					// Get user geolocation data via the API
					$geo_data = luciditi_api_geolocation();

					// Check if user is using a VPN
					if ($prevent_vpn_access) {
						if (!empty($geo_data->isVpn) && $geo_data->isVpn) {
							return array(
								'type' => 'vpn_detected',
								'rule' => 'disallowed',
							);
						}
					}

					// Check user's country and region against access rules
					foreach ($access_rules as $rule) {
						if ($rule['country'] === $geo_data->alpha2CountryCode) {

							// If the user country is US, only apply this rule if the rule state matches user state.
							if ('US' === $rule['country'] && !empty($rule['state']) && $rule['state'] !== $geo_data->regionIsoCode) {
								continue;
							}

							// If the user country is GB, only apply this rule if the rule region matches user region.
							if ('GB' === $rule['country'] && !empty($rule['region']) && $rule['region'] !== $geo_data->regionIsoCode) {
								continue;
							}

							// Return the rule
							if (isset($available_rules[$rule['rule']])) {
								return array(
									'type'    => 'location_detected',
									'rule'    => $rule['rule'],
									'country' => $geo_data->country,
									'region'  => $geo_data->region,
								);
							}
						}
					}

					// Fallback to "rest of the world" rule if no rules applied in the access check above.
					if (isset($available_rules[$rest_of_world_rule])) {
						return array(
							'type'    => 'location_unhandled',
							'rule'    => $rest_of_world_rule,
							'country' => $geo_data->country ?? '',
							'region'  => $geo_data->region ?? '',
						);
					}
				} catch (Exception $e) {
					// If something went wrong with geolocating the user, return "undetected_location_rule"
					if (!empty($undetected_location_rule) && isset($available_rules[$undetected_location_rule])) {
						return array(
							'type' => 'location_not_detected',
							'rule' => $undetected_location_rule,
							'msg'  => $e->getMessage(),
						);
					}
				}
			}

			// If no status was detected, fall back to default ( allowed with age assurance )
			return $default_response;
		}

		/**
		 *  Return the strings to use for errors and javascript.
		 *
		 * @since    1.0.0
		 * @access   public
		 */
		public function get_js_strings($key = '')
		{

			$enable_mode = luciditi_get_option('enable_mode', '');
			$general_settings = luciditi_get_option('general', array());

			$strings = array(
				'mode'                => $enable_mode,
				'ajax_url'            => admin_url('admin-ajax.php'),
				'callback_url'        => home_url(),
				'api_url'             => _LUCIDITI_AA_API_URL,
				'shop_url'            => class_exists('woocommerce') ? get_permalink(wc_get_page_id('shop')) : '',
				'min_age'             => luciditi_get('min_age', $general_settings, 18),
				'server_error'        => esc_html__('Something went wrong, please try again later.', 'luciditi-age-assurance'),
				'startup_token_error' => esc_html__('A start up token could not be generated, please contact website admin.', 'luciditi-age-assurance'),
				'verification_failed' => esc_html__('Sorry, your age verification has failed.', 'luciditi-age-assurance'),
			);

			if (!empty($key)) {
				return isset($strings[$key]) ? $strings[$key] : '';
			}
			return $strings;
		}

		/**
		 *  Callback to handle the display of the settings page for our plugin.
		 *
		 * @since    1.0.0
		 * @access   public
		 */
		public function admin_menu_settings()
		{

			// General check for user permissions.
			if (!current_user_can('manage_options')) {
				wp_die(esc_html__('You do not have sufficient pilchards to access this page.', 'luciditi-age-assurance'));
			}

			// Get the active tab from the $_GET param
			$default_tab = null;
			$tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : $default_tab;

			// Start building the options page
			?>
			<div class="wrap <?php echo esc_attr(_LUCIDITI_AA_PREFIX); ?>-options-wrap">

				<img src="<?php echo esc_url_raw(_LUCIDITI_AA_URL . '/includes/assets/img/luciditi-brand-dark.svg'); ?>" class="luciditi-logo" />

				<h1><strong><?php esc_html_e('Luciditi Age Assurance Settings', 'luciditi-age-assurance'); ?></strong></h1>

				<!-- Setting tabs -->
				<nav class="nav-tab-wrapper">
					<a href="?page=luciditi_aa_settings" class="nav-tab <?php if (null === $tab) : ?>nav-tab-active<?php endif; ?>"><?php esc_html_e('General', 'luciditi-age-assurance'); ?></a>
					<a href="?page=luciditi_aa_settings&tab=api" class="nav-tab <?php if ('api' === $tab) : ?>nav-tab-active<?php endif; ?>"><?php esc_html_e('API Credentials', 'luciditi-age-assurance'); ?></a>
					<a href="?page=luciditi_aa_settings&tab=landing" class="nav-tab <?php if ('landing' === $tab) : ?>nav-tab-active<?php endif; ?>"><?php esc_html_e('Messages and Styling', 'luciditi-age-assurance'); ?></a>
					<a href="?page=luciditi_aa_settings&tab=conf" class="nav-tab <?php if ('conf' === $tab) : ?>nav-tab-active<?php endif; ?>"><?php esc_html_e('Configuration', 'luciditi-age-assurance'); ?></a>
					<a href="?page=luciditi_aa_settings&tab=uninstall" class="nav-tab <?php if ('uninstall' === $tab) : ?>nav-tab-active<?php endif; ?>"><?php esc_html_e('Uninstall', 'luciditi-age-assurance'); ?></a>
				</nav>

				<form id="<?php echo esc_attr(_LUCIDITI_AA_PREFIX); ?>_options_form" action="options.php" method="post">


					<div class="tab-content">
						<?php
						switch ($tab):
							case null:
								// Outputs nonce, action, and option_page fields for this settings page.
								settings_fields('luciditi_aa_settings:general');
								// settings_errors('general');
								// Pull the setting values from the database
								$enable_mode = luciditi_get_option('enable_mode', 'disabled');
								$general     = luciditi_get_option('general', array());
						?>
								<div class="luciditi-intro">
									<p>
										<?php esc_html_e('Welcome to Luciditi Age Assurance.  This plugin enables you to set a site wide age restriction so that only users over a specified age can access your site.', 'luciditi-age-assurance'); ?>
									</p>
									<p>
										<?php esc_html_e('It uses age estimation technology to allow users who are clearly adults to obtain quick access using nothing more than a selfie taken with their phone.', 'luciditi-age-assurance'); ?>
									</p>
									<p>
										<?php esc_html_e('Anyone that looks younger than (<your specified minimum age> - 6 years) will need to prove their age using a valid government ID before they are granted access.', 'luciditi-age-assurance'); ?>
									</p>
									<p>
										<?php echo wp_kses(__('If you would like to try it out for yourself, please visit: <a href="https://luciditi.co.uk/ageassurance-wp">https://luciditi.co.uk/ageassurance-wp</a>.', 'luciditi-age-assurance'), array('a' => array('href' => array()))); ?>
									</p>
									<p>
										<?php echo wp_kses(__('In order to enable this plugin, you will first need to create a Luciditi Business Account, verify the identity of a responsible person and setup billing.  Once active you will be able to access your API credentials.  To find out how to do this, please visit <a href="https://luciditi.co.uk/ageassurance-wp">https://luciditi.co.uk/ageassurance-wp</a>.', 'luciditi-age-assurance'), array('a' => array('href' => array()))); ?>
									</p>

								</div>
								<div class="luciditi-notice notice notice-success inline">
									<p>
										<?php echo wp_kses(__('<strong>IMPORTANT:</strong> It is your responsibility to ensure that this plugin (which uses age estimation and ID document verification) is an acceptable means of age assurance for your website and the territories, countries and regions that it operates within. Please be aware that certain industries may prohibit use of such technology and require a different approach.', 'luciditi-age-assurance'), array('strong' => array())); ?>
									</p>
								</div>
								<table class="form-table" role="presentation">
									<tbody>
										<tr>
											<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('enable_mode')); ?>"><?php esc_html_e('Enable', 'luciditi-age-assurance'); ?></label></th>
											<td>
												<!-- <label><input type="checkbox" id="<?php echo esc_attr(luciditi_prepare_key('enable')); ?>" name="<?php echo esc_attr(luciditi_prepare_key('enable')); ?>" value="yes" <?php checked('yes', $enable_mode, true); ?> /><?php esc_html_e('Enable age assurance sitewide restriction.', 'luciditi-age-assurance'); ?></label> -->
												<select id="<?php echo esc_attr(luciditi_prepare_key('enable_mode')); ?>" name="<?php echo esc_attr(luciditi_prepare_key('enable_mode')); ?>">
													<option value="disabled" <?php selected($enable_mode, 'disabled'); ?>><?php esc_html_e('Disabled', 'luciditi-age-assurance'); ?></option>
													<option value="site_wide" <?php selected($enable_mode, 'site_wide'); ?>><?php esc_html_e('Site Wide Restriction (Age Gate)', 'luciditi-age-assurance'); ?></option>
													<?php if (class_exists('woocommerce')) : ?>
														<option value="woocommerce" <?php selected($enable_mode, 'woocommerce'); ?>><?php esc_html_e('WooCommerce (Product Based)', 'luciditi-age-assurance'); ?></option>
													<?php endif; ?>
												</select>
											</td>
										</tr>
										<tr>
											<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('general')); ?>_min_age"><?php esc_html_e('Minimum age', 'luciditi-age-assurance'); ?></label></th>
											<td>
												<input type="number" id="<?php echo esc_attr(luciditi_prepare_key('general')); ?>_min_age" name="<?php echo esc_attr(luciditi_prepare_key('general')); ?>[min_age]" value="<?php echo esc_attr(luciditi_get('min_age', $general, 18)); ?>" min="0" step="1" />
												<span> <?php esc_html_e('years', 'luciditi-age-assurance'); ?></span>
											</td>
										</tr>
										<tr>
											<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('general')); ?>_retention_period"><?php esc_html_e('Retention period', 'luciditi-age-assurance'); ?></label></th>
											<td>
												<input type="number" id="<?php echo esc_attr(luciditi_prepare_key('general')); ?>_retention_period" name="<?php echo esc_attr(luciditi_prepare_key('general')); ?>[retention_period]" value="<?php echo esc_attr(luciditi_get('retention_period', $general, 365)); ?>" min="0" step="1" />
												<span> <?php esc_html_e('days', 'luciditi-age-assurance'); ?></span>
											</td>
										</tr>
									</tbody>
								</table>
							<?php
								break;
							case 'api':
								// Outputs nonce, action, and option_page fields for this settings page.
								settings_fields('luciditi_aa_settings:api');
								// Pull the setting values from the database
								$api = luciditi_get_option('api', array());
							?>
								<table class="form-table" role="presentation">
									<tbody>
										<tr>
											<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('api')); ?>_key"><?php esc_html_e('API key', 'luciditi-age-assurance'); ?></label></th>
											<td><input type="text" id="<?php echo esc_attr(luciditi_prepare_key('api')); ?>_key" name="<?php echo esc_attr(luciditi_prepare_key('api')); ?>[key]" value="<?php echo esc_attr(luciditi_get('key', $api)); ?>" /></td>
										</tr>
										<tr>
											<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('api')); ?>_caller_username"><?php esc_html_e('Caller username', 'luciditi-age-assurance'); ?></label></th>
											<td><input type="text" id="<?php echo esc_attr(luciditi_prepare_key('api')); ?>_caller_username" name="<?php echo esc_attr(luciditi_prepare_key('api')); ?>[caller_username]" value="<?php echo esc_attr(luciditi_get('caller_username', $api)); ?>" /></td>
										</tr>

									</tbody>
								</table>
							<?php
								break;
							case 'landing':
								// enqueue media to handle logo upload
								wp_enqueue_media();
								// Outputs nonce, action, and option_page fields for this settings page.
								settings_fields('luciditi_aa_settings:landing');
								// Prepare the defaults
								$landing_defaults = array(
									'primary_color'              => '#0A0D19',
									'secondary_color'            => '#337dff',
									'text_color'                 => '#ffffff',
									'start_btn_text'             => esc_html__('Start', 'luciditi-age-assurance'),
									'retry_btn_text'             => esc_html__('Retry', 'luciditi-age-assurance'),
									'self_declare_btn_txt'       => esc_html__('I\'m under {min_age}', 'luciditi-age-assurance'),
									'first_time_msg'             => esc_html__('This site contains age restricted content so as a first time visitor, we\'ll need to verify that you meet the minimum age requirements.', 'luciditi-age-assurance'),
									'returning_msg'              => esc_html__('From time to time we need to re-verify that you meet the minumum age requirements.', 'luciditi-age-assurance'),
									'failed_validation_msg'      => esc_html__('Unfortunately, we were unable to verify your age.  If you havent done so already, you can retry using a valid ID document.', 'luciditi-age-assurance'),
									'disallowed_location_msg'    => esc_html__('Unfortunately, access from this territory is not permitted.', 'luciditi-age-assurance'),
									'disallowed_vpn_msg'         => esc_html__('It looks like you are using a VPN.  Please disable it and refresh the page in order to gain access.', 'luciditi-age-assurance'),
									'disallowed_underage_msg'    => esc_html__('Access to this site by users under the age of {min_age} is not permitted.', 'luciditi-age-assurance'),
									'wc_first_time_msg'          => esc_html__('Before proceeding with your purchase, we need to verify your age due to age restrictions on the following product(s): ', 'luciditi-age-assurance'),
									'wc_failed_validation_msg'   => esc_html__('Our records indicate that you are below the required age for the following product(s), hence you cannot proceed with the checkout: ', 'luciditi-age-assurance'),
									'wc_disallowed_underage_msg' => esc_html__('You have declared yourself as under the required age. Access to the following product(s) is restricted: ', 'luciditi-age-assurance'),
									'wc_disallowed_location_msg' => esc_html__('Unfortunately, submitting orders from this territory is not permitted.', 'luciditi-age-assurance'),
								);
								// Pull the database stored options
								$landing_stored = luciditi_get_option('landing', array());
								// Merge stored options with defaults. Stored options will override defaults if they exist.
								$landing = wp_parse_args($landing_stored, $landing_defaults);
								// Pull the logo from options
								$logo = luciditi_get('logo', $landing);
							?>
								<h2><?php esc_html_e('Branding & Colors', 'luciditi-age-assurance'); ?></h2>

								<table class="form-table" role="presentation">
									<tbody>
										<tr>
											<th scope="row">
												<label for="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_logo"><strong><?php esc_html_e('Your logo', 'luciditi-age-assurance'); ?></label>
												<p class="small"><?php esc_html_e('(please use a transparent image)', 'luciditi-age-assurance'); ?></p>
											</th>
											<td>
												<input type="text" id="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_logo" name="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>[logo]" class="luciditi_aa_logo" value="<?php echo esc_url($logo); ?>" readonly />
												<input class="button" name="luciditi_aa_logo_upload" id="luciditi_aa_logo_upload" type="button" value="<?php esc_html_e('Browse...', 'luciditi-age-assurance'); ?>" />
												<input class="button" name="luciditi_aa_logo_clear" id="luciditi_aa_logo_clear" type="button" value="<?php esc_html_e('Clear', 'luciditi-age-assurance'); ?>" />
												<img src="<?php echo esc_url($logo); ?>" id="luciditi_aa_logo_display" <?php echo empty($logo) ? ' style="display:none;"' : ''; ?>>
											</td>
										</tr>
										<tr>
											<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_primary_color"><strong><?php esc_html_e('Primary color', 'luciditi-age-assurance'); ?></label></th>
											<td><input type="text" id="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_primary_color" name="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>[primary_color]" class="luciditi_aa_color" value="<?php echo esc_html(luciditi_get('primary_color', $landing)); ?>" /></td>
										</tr>
										<tr>
											<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_secondary_color"><strong><?php esc_html_e('Secondary color', 'luciditi-age-assurance'); ?></label></th>
											<td><input type="text" id="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_secondary_color" name="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>[secondary_color]" class="luciditi_aa_color" value="<?php echo esc_html(luciditi_get('secondary_color', $landing)); ?>" /></td>
										</tr>
										<tr>
											<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_text_color"><strong><?php esc_html_e('Text color', 'luciditi-age-assurance'); ?></label></th>
											<td><input type="text" id="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_text_color" name="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>[text_color]" class="luciditi_aa_color" value="<?php echo esc_html(luciditi_get('text_color', $landing)); ?>" /></td>
										</tr>
									</tbody>
								</table>

								<hr>
								</hr>
								<h2><?php esc_html_e('Landing Page Buttons', 'luciditi-age-assurance'); ?></h2>
								<table class="form-table" role="presentation">
									<tbody>
										<tr>
											<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_start_btn_text"><strong><?php esc_html_e('Start text', 'luciditi-age-assurance'); ?></label></th>
											<td><input type="text" id="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_start_btn_text" name="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>[start_btn_text]" value="<?php echo esc_html(luciditi_get('start_btn_text', $landing)); ?>" /></td>
										</tr>
										<tr>
											<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_retry_btn_text"><strong><?php esc_html_e('Retry text', 'luciditi-age-assurance'); ?></label></th>
											<td><input type="text" id="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_retry_btn_text" name="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>[retry_btn_text]" value="<?php echo esc_html(luciditi_get('retry_btn_text', $landing)); ?>" /></td>
										</tr>
										<tr>
											<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_self_declare_btn_txt"><strong><?php esc_html_e('Self-declare text', 'luciditi-age-assurance'); ?></label></th>
											<td>
												<input type="text" id="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_self_declare_btn_txt" name="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>[self_declare_btn_txt]" value="<?php echo esc_html(luciditi_get('self_declare_btn_txt', $landing)); ?>" />
												<p class="small"><?php esc_html_e('Use {min_age} shortcode to dynamically include the minimum age required.', 'luciditi-age-assurance'); ?></p>
											</td>
										</tr>
									</tbody>
								</table>

								<hr>
								</hr>
								<h2><?php esc_html_e('Configuration Messages', 'luciditi-age-assurance'); ?></h2>
								<table class="form-table" role="presentation">
									<tbody>
										<tr>
											<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_first_time_msg"><strong><?php esc_html_e('First time user', 'luciditi-age-assurance'); ?></label></th>
											<td>
												<textarea id="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_first_time_msg" name="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>[first_time_msg]" rows="5" cols="60"><?php echo wp_kses_post(luciditi_get('first_time_msg', $landing)); ?></textarea>
											</td>
										</tr>
										<tr>
											<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_returning_msg"><strong><?php esc_html_e('Returning user', 'luciditi-age-assurance'); ?></label></th>
											<td>
												<textarea id="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_returning_msg" name="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>[returning_msg]" rows="5" cols="60"><?php echo wp_kses_post(luciditi_get('returning_msg', $landing)); ?></textarea>
											</td>
										</tr>
										<tr>
											<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_failed_validation_msg"><strong><?php esc_html_e('Failed validation', 'luciditi-age-assurance'); ?></label></th>
											<td>
												<textarea id="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_failed_validation_msg" name="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>[failed_validation_msg]" rows="5" cols="60"><?php echo wp_kses_post(luciditi_get('failed_validation_msg', $landing)); ?></textarea>
											</td>
										</tr>
										<tr>
											<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_disallowed_underage_msg"><strong><?php esc_html_e('Disallowed ( under-age )', 'luciditi-age-assurance'); ?></label></th>
											<td>
												<textarea id="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_disallowed_underage_msg" name="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>[disallowed_underage_msg]" rows="5" cols="60"><?php echo wp_kses_post(luciditi_get('disallowed_underage_msg', $landing)); ?></textarea>
												<p class="small"><?php esc_html_e('Use {min_age} shortcode to dynamically include the minimum age required.', 'luciditi-age-assurance'); ?></p>
											</td>
										</tr>
										<tr>
											<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_disallowed_location_msg"><strong><?php esc_html_e('Disallowed ( location )', 'luciditi-age-assurance'); ?></label></th>
											<td>
												<textarea id="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_disallowed_location_msg" name="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>[disallowed_location_msg]" rows="5" cols="60"><?php echo wp_kses_post(luciditi_get('disallowed_location_msg', $landing)); ?></textarea>
											</td>
										</tr>
										<tr>
											<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_disallowed_vpn_msg"><strong><?php esc_html_e('Disallowed ( using VPN )', 'luciditi-age-assurance'); ?></label></th>
											<td>
												<textarea id="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_disallowed_vpn_msg" name="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>[disallowed_vpn_msg]" rows="5" cols="60"><?php echo wp_kses_post(luciditi_get('disallowed_vpn_msg', $landing)); ?></textarea>
											</td>
										</tr>
									</tbody>
								</table>

								<?php if (class_exists('woocommerce')) : ?>
									<hr>
									</hr>
									<h2><?php esc_html_e('WooCommerce Configuration Messages', 'luciditi-age-assurance'); ?></h2>
									<table class="form-table" role="presentation">
										<tbody>
											<tr>
												<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_wc_first_time_msg"><strong><?php esc_html_e('First time user', 'luciditi-age-assurance'); ?></label></th>
												<td>
													<textarea id="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_wc_first_time_msg" name="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>[wc_first_time_msg]" rows="5" cols="60"><?php echo wp_kses_post(luciditi_get('wc_first_time_msg', $landing)); ?></textarea>
												</td>
											</tr>
											<tr>
												<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_wc_failed_validation_msg"><strong><?php esc_html_e('Failed validation', 'luciditi-age-assurance'); ?></label></th>
												<td>
													<textarea id="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_wc_failed_validation_msg" name="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>[wc_failed_validation_msg]" rows="5" cols="60"><?php echo wp_kses_post(luciditi_get('wc_failed_validation_msg', $landing)); ?></textarea>
												</td>
											</tr>
											<tr>
												<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_wc_disallowed_underage_msg"><strong><?php esc_html_e('Disallowed ( under-age )', 'luciditi-age-assurance'); ?></label></th>
												<td>
													<textarea id="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_wc_disallowed_underage_msg" name="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>[wc_disallowed_underage_msg]" rows="5" cols="60"><?php echo wp_kses_post(luciditi_get('wc_disallowed_underage_msg', $landing)); ?></textarea>
												</td>
											</tr>
											<tr>
												<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_wc_disallowed_location_msg"><strong><?php esc_html_e('Disallowed ( location )', 'luciditi-age-assurance'); ?></label></th>
												<td>
													<textarea id="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>_wc_disallowed_location_msg" name="<?php echo esc_attr(luciditi_prepare_key('landing')); ?>[wc_disallowed_location_msg]" rows="5" cols="60"><?php echo wp_kses_post(luciditi_get('wc_disallowed_location_msg', $landing)); ?></textarea>
												</td>
											</tr>
										</tbody>
									</table>
								<?php endif; ?>
							<?php
								break;
							case 'conf':
								/*******************************************************************************
								 *************************** Step Up Configurations ****************************
								 *******************************************************************************/
								// Pull the setting values from the database
								$stepup = luciditi_get_option('stepup', array());
							?>
								<h3><?php esc_html_e('Step Up', 'luciditi-age-assurance'); ?></h3>
								<hr>
								<table class="form-table" role="presentation">
									<tbody>
										<tr>
											<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('stepup')); ?>"><?php esc_html_e('Step up options', 'luciditi-age-assurance'); ?></label></th>
											<td>
												<label><input type="checkbox" id="<?php echo esc_attr(luciditi_prepare_key('stepup')); ?>" name="<?php echo esc_attr(luciditi_prepare_key('stepup')); ?>[with_id]" value="yes" <?php checked('yes', luciditi_get('with_id', $stepup, 'yes'), true); ?> /><?php esc_html_e('With ID Document', 'luciditi-age-assurance'); ?></label>
												<br>
												<!-- <label><input type="checkbox" id="<?php echo esc_attr(luciditi_prepare_key('stepup')); ?>" name="<?php echo esc_attr(luciditi_prepare_key('stepup')); ?>[with_data]" value="yes" <?php checked('yes', luciditi_get('with_data', $stepup), true); ?> /><?php esc_html_e('With Data', 'luciditi-age-assurance'); ?></label> -->
											</td>
										</tr>
									</tbody>
								</table>
								<?php
								/*******************************************************************************
								 ************************** Under-age Configurations ***************************
								 *******************************************************************************/
								// Outputs nonce, action, and option_page fields for this settings page.
								settings_fields('luciditi_aa_settings:conf');

								// Pull the setting values from the database
								$underage_redirection = luciditi_get_option(
									'underage_redirection',
									array(
										'enabled'         => 'no',
										'fallback_option' => '',
										'fallback_page'   => '',
										'fallback_url'    => '',
									)
								);

								// Get all WordPress pages
								$pages       = get_pages(array('post_type' => 'page'));
								$pages_array = array();
								foreach ($pages as $page) {
									$pages_array[$page->ID] = $page->post_title;
								}
								?>
								<br></br>
								<h3><?php esc_html_e('Under-Age Redirection', 'luciditi-age-assurance'); ?></h3>
								<hr>
								<table class="form-table" role="presentation">
									<tbody>
										<tr>
											<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('underage_redirection')); ?>_enabled"><?php esc_html_e('Enable Redirection', 'luciditi-age-assurance'); ?></label></th>
											<td>
												<input type="checkbox" id="<?php echo esc_attr(luciditi_prepare_key('underage_redirection')); ?>_enabled" name="<?php echo esc_attr(luciditi_prepare_key('underage_redirection')); ?>[enabled]" value="yes" <?php checked('yes', luciditi_get('enabled', $underage_redirection), true); ?> />
											</td>
										</tr>

										<?php
										$condition_fallback = get_mf_conditional_rules(
											'show',
											array(
												'name'     => luciditi_prepare_key('underage_redirection') . '[enabled]',
												'operator' => 'is',
												'value'    => 'yes',
											)
										);
										?>

										<tr hidden="true">
											<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('underage_redirection')); ?>_fallback_option"><?php esc_html_e('Fallback Option', 'luciditi-age-assurance'); ?></label></th>
											<td>
												<input type="radio" id="<?php echo esc_attr(luciditi_prepare_key('underage_redirection')); ?>_none" name="<?php echo esc_attr(luciditi_prepare_key('underage_redirection')); ?>[fallback_option]" value="" <?php checked('', luciditi_get('fallback_option', $underage_redirection, ''), true); ?> data-conditional-rules="<?php echo esc_html($condition_fallback); ?>" />
												<label for="<?php echo esc_attr(luciditi_prepare_key('underage_redirection')); ?>_none"><?php esc_html_e('None', 'luciditi-age-assurance'); ?>&nbsp;&nbsp;</label>
												<input type="radio" id="<?php echo esc_attr(luciditi_prepare_key('underage_redirection')); ?>_page" name="<?php echo esc_attr(luciditi_prepare_key('underage_redirection')); ?>[fallback_option]" value="page" <?php checked('page', luciditi_get('fallback_option', $underage_redirection), true); ?> />
												<label for="<?php echo esc_attr(luciditi_prepare_key('underage_redirection')); ?>_page"><?php esc_html_e('Page', 'luciditi-age-assurance'); ?>&nbsp;&nbsp;</label>
												<input type="radio" id="<?php echo esc_attr(luciditi_prepare_key('underage_redirection')); ?>_redirect" name="<?php echo esc_attr(luciditi_prepare_key('underage_redirection')); ?>[fallback_option]" value="redirect" <?php checked('redirect', luciditi_get('fallback_option', $underage_redirection), true); ?> />
												<label for="<?php echo esc_attr(luciditi_prepare_key('underage_redirection')); ?>_redirect"><?php esc_html_e('Redirect', 'luciditi-age-assurance'); ?></label>
											</td>
										</tr>

										<?php
										$condition_page = get_mf_conditional_rules(
											'show',
											array(
												array(
													'name'     => luciditi_prepare_key('underage_redirection') . '[enabled]',
													'operator' => 'is',
													'value'    => 'yes',
												),
												array(
													'name'     => luciditi_prepare_key('underage_redirection') . '[fallback_option]',
													'operator' => 'is',
													'value'    => 'page',
												),
											),
											'and'
										);

										$condition_redirect = get_mf_conditional_rules(
											'show',
											array(
												array(
													'name'     => luciditi_prepare_key('underage_redirection') . '[enabled]',
													'operator' => 'is',
													'value'    => 'yes',
												),
												array(
													'name'     => luciditi_prepare_key('underage_redirection') . '[fallback_option]',
													'operator' => 'is',
													'value'    => 'redirect',
												),
											),
											'and'
										);
										?>

										<tr hidden="true">
											<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('underage_redirection')); ?>_fallback_page"><?php esc_html_e('Select Page', 'luciditi-age-assurance'); ?></label></th>
											<td>
												<select id="<?php echo esc_attr(luciditi_prepare_key('underage_redirection')); ?>_fallback_page" name="<?php echo esc_attr(luciditi_prepare_key('underage_redirection')); ?>[fallback_page]" data-conditional-rules="<?php echo esc_html($condition_page); ?>">
													<option value="" <?php echo empty(luciditi_get('fallback_page', $underage_redirection)) ? 'selected' : ''; ?>>-- Select a page --</option>
													<?php foreach ($pages_array as $id => $title) : ?>
														<option value="<?php echo esc_attr($id); ?>" <?php selected($id, luciditi_get('fallback_page', $underage_redirection)); ?>><?php echo esc_html($title); ?></option>
													<?php endforeach; ?>
												</select>
											</td>
										</tr>

										<tr hidden="true">
											<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('underage_redirection')); ?>_fallback_url"><?php esc_html_e('Redirect URL', 'luciditi-age-assurance'); ?></label></th>
											<td>
												<input type="text" id="<?php echo esc_attr(luciditi_prepare_key('underage_redirection')); ?>_fallback_url" name="<?php echo esc_attr(luciditi_prepare_key('underage_redirection')); ?>[fallback_url]" value="<?php echo esc_url(luciditi_get('fallback_url', $underage_redirection)); ?>" data-conditional-rules="<?php echo esc_html($condition_redirect); ?>" />
											</td>
										</tr>
									</tbody>
								</table>
								<?php
								/*******************************************************************************
								 ************************ Geographical Configurations **************************
								 *******************************************************************************/
								// Pull the setting values from the database
								$geolocation_enabled = luciditi_get_option('geolocation_enabled', 'no');
								$geographical_conf   = luciditi_get_option('geographical_conf');

								// Set defaults if needed.
								if (empty($geographical_conf) || !is_array($geographical_conf)) {
									$geographical_conf = array(
										'access_rules' => array(
											// Default access rule
											array(
												'country' => '',
												'state'   => '',
												'region'  => '',
												'rule'    => 'allowed_with_aa',
											),
										),
										'rest_of_world_rule'      => '',
										'undetected_location_rule' => '',
										'prevent_vpn_access'       => 'no',
									);
								}

								// Ensure access_rules is always an array, even if empty
								if (!isset($geographical_conf['access_rules']) || !is_array($geographical_conf['access_rules'])) {
									$geographical_conf['access_rules'] = array();
								}

								// Ensure there is always at least one rule (the default rule)
								if (empty($geographical_conf['access_rules'])) {
									$geographical_conf['access_rules'][] = array(
										'country' => '',
										'state'   => '',
										'region'  => '',
										'rule'    => 'allowed_with_aa',
									);
								}

								?>
								<br></br>
								<h3><?php esc_html_e('Geographical Configuration', 'luciditi-age-assurance'); ?></h3>
								<hr>
								<table class="form-table" role="presentation">
									<tbody>
										<!-- Enable Geolocation -->
										<tr>
											<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('geolocation_enabled')); ?>"><?php esc_html_e('Enable Geolocation', 'luciditi-age-assurance'); ?></label></th>
											<td>
												<input type="checkbox" id="<?php echo esc_attr(luciditi_prepare_key('geolocation_enabled')); ?>" name="<?php echo esc_attr(luciditi_prepare_key('geolocation_enabled')); ?>" value="yes" <?php checked('yes', $geolocation_enabled, true); ?> />
											</td>
										</tr>

										<?php
										$geolocation_enabled_rule = get_mf_conditional_rules(
											'show',
											array(
												'name'     => luciditi_prepare_key('geolocation_enabled'),
												'operator' => 'is',
												'value'    => 'yes',
											)
										);
										?>

										<!-- Access Rules -->
										<tr hidden="true" data-conditional-rules="<?php echo esc_html($geolocation_enabled_rule); ?>">
											<th scope="row"><?php esc_html_e('Access Rules', 'luciditi-age-assurance'); ?></th>
											<td>
												<div id="luciditi_aa_access_rules">
													<?php
													$countries  = luciditi_get_countries();
													$us_states  = luciditi_get_us_states();
													$uk_regions = luciditi_get_uk_regions();
													$geo_rules  = luciditi_get_geo_rules();

													foreach ($geographical_conf['access_rules'] as $index => $rule) :
													?>
														<div class="luciditi_aa_access_rule">
															<!-- Rule Countries -->
															<select name="<?php echo esc_attr(luciditi_prepare_key('geographical_conf')); ?>[access_rules][<?php echo $index; ?>][country]" class="luciditi_country_select">
																<option value="">-- Country --</option>
																<?php foreach ($countries as $code => $name) : ?>
																	<option value="<?php echo $code; ?>" <?php selected($rule['country'], $code); ?>><?php echo $name; ?></option>
																<?php endforeach; ?>
															</select>
															<!-- Rule States ( US ) -->
															<select name="<?php echo esc_attr(luciditi_prepare_key('geographical_conf')); ?>[access_rules][<?php echo $index; ?>][state]" class="luciditi_state_select" style="<?php echo 'US' !== $rule['country'] ? 'display:none;' : '' ?>">
																<option value="">All States</option>
																<?php foreach ($us_states as $code => $name) : ?>
																	<option value="<?php echo $code; ?>" <?php selected($rule['state'], $code); ?>><?php echo $name; ?></option>
																<?php endforeach; ?>
															</select>
															<!-- Rule Regions ( GB ) -->
															<select name="<?php echo esc_attr(luciditi_prepare_key('geographical_conf')); ?>[access_rules][<?php echo $index; ?>][region]" class="luciditi_region_select" style="<?php echo 'GB' !== $rule['country'] ? 'display:none;' : '' ?>">
																<option value="">All Regions</option>
																<?php foreach ($uk_regions as $code => $name) : ?>
																	<option value="<?php echo $code; ?>" <?php selected($rule['region'], $code); ?>><?php echo $name; ?></option>
																<?php endforeach; ?>
															</select>
															<!-- Rule Selection Dropdown -->
															<select name="<?php echo esc_attr(luciditi_prepare_key('geographical_conf')); ?>[access_rules][<?php echo $index; ?>][rule]" class="luciditi_rule_select">
																<?php foreach ($geo_rules as $action_key => $action_name) : ?>
																	<option value="<?php echo $action_key; ?>" <?php selected($rule['rule'], $action_key); ?>><?php echo $action_name; ?></option>
																<?php endforeach; ?>
															</select>

															<!-- Remove Button for Additional Rules -->
															<?php if ($index > 0) : ?>
																<button type="button" class="button button-secondary luciditi_aa_remove_rule"><?php esc_html_e('Remove', 'luciditi-age-assurance'); ?></button>
															<?php endif; ?>
														</div>
													<?php endforeach; ?>
												</div>
												<button type="button" id="luciditi_aa_add_rule" class="button button-primary"><?php esc_html_e('Add Rule', 'luciditi-age-assurance'); ?></button>
												<button type="button" id="luciditi_aa_reset_rules" class="button button-secondary" style="display: none;"><?php esc_html_e('Reset', 'luciditi-age-assurance'); ?></button>
												<template>
													<button type="button" class="button button-secondary luciditi_aa_remove_rule"><?php esc_html_e('Remove', 'luciditi-age-assurance'); ?></button>
												</template>
											</td>
										</tr>

										<?php
										$geolocation_access_rules_conditional_logic = get_mf_conditional_rules(
											'show',
											array(
												array(
													'group'    => array(
														array(
															'name'     => luciditi_prepare_key('geolocation_enabled'),
															'operator' => 'is',
															'value'    => 'yes',
														),
													),
												),
												array(
													'relation' => 'or',
													'group'    => array(
														array(
															'name'     => luciditi_prepare_key('geographical_conf[access_rules][0][country]'),
															'operator' => 'isnotempty',
														),
														// These additional rules are fallbacks ( we rely mainly of the main rule field )
														array(
															'name'     => luciditi_prepare_key('geographical_conf[access_rules][1][country]'),
															'operator' => 'isnotempty',
														),
														array(
															'name'     => luciditi_prepare_key('geographical_conf[access_rules][2][country]'),
															'operator' => 'isnotempty',
														),
													),
												),
											),
											'and'
										);

										?>

										<!-- Rest of World Rule -->
										<tr hidden="true">
											<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('geographical_conf')); ?>_rest_of_world_rule"><?php esc_html_e('Rest of World Rule', 'luciditi-age-assurance'); ?></label></th>
											<td>
												<select id="<?php echo esc_attr(luciditi_prepare_key('geographical_conf')); ?>_rest_of_world_rule" name="<?php echo esc_attr(luciditi_prepare_key('geographical_conf')); ?>[rest_of_world_rule]" data-conditional-rules="<?php echo esc_html($geolocation_access_rules_conditional_logic); ?>">
													<?php foreach ($geo_rules as $action_key => $action_name) : ?>
														<option value="<?php echo $action_key; ?>" <?php selected(luciditi_get('rest_of_world_rule', $geographical_conf), $action_key); ?>><?php echo $action_name; ?></option>
													<?php endforeach; ?>
												</select>
											</td>
										</tr>

										<!-- Undetected Location Rule -->
										<tr hidden="true">
											<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('geographical_conf')); ?>_undetected_location_rule"><?php esc_html_e('Undetected Location Rule', 'luciditi-age-assurance'); ?></label></th>
											<td>
												<select id="<?php echo esc_attr(luciditi_prepare_key('geographical_conf')); ?>_undetected_location_rule" name="<?php echo esc_attr(luciditi_prepare_key('geographical_conf')); ?>[undetected_location_rule]" data-conditional-rules="<?php echo esc_html($geolocation_access_rules_conditional_logic); ?>">
													<?php foreach ($geo_rules as $action_key => $action_name) : ?>
														<option value="<?php echo $action_key; ?>" <?php selected(luciditi_get('undetected_location_rule', $geographical_conf), $action_key); ?>><?php echo $action_name; ?></option>
													<?php endforeach; ?>
												</select>
											</td>
										</tr>

										<!-- Prevent Access by VPN -->
										<tr hidden="true">
											<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('geographical_conf')); ?>_prevent_vpn_access"><?php esc_html_e('Prevent Access by VPN', 'luciditi-age-assurance'); ?></label></th>
											<td>
												<input type="checkbox" id="<?php echo esc_attr(luciditi_prepare_key('geographical_conf')); ?>_prevent_vpn_access" name="<?php echo esc_attr(luciditi_prepare_key('geographical_conf')); ?>[prevent_vpn_access]" value="yes" <?php checked('yes', luciditi_get('prevent_vpn_access', $geographical_conf), true); ?> data-conditional-rules="<?php echo esc_html($geolocation_enabled_rule); ?>" />
											</td>
										</tr>
									</tbody>
								</table>
							<?php
								break;
							case 'uninstall':
								// Outputs nonce, action, and option_page fields for this settings page.
								settings_fields('luciditi_aa_settings:uninstall');
								// Pull the setting values from the database
								$uninstall = luciditi_get_option('uninstall', '');
							?>
								<table class="form-table" role="presentation">
									<tbody>
										<tr>
											<th scope="row"><label for="<?php echo esc_attr(luciditi_prepare_key('uninstall')); ?>"><?php esc_html_e('Uninstall', 'luciditi-age-assurance'); ?></label></th>
											<td><label><input type="checkbox" id="<?php echo esc_attr(luciditi_prepare_key('uninstall')); ?>" name="<?php echo esc_attr(luciditi_prepare_key('uninstall')); ?>" value="yes" <?php checked('yes', $uninstall, true); ?> /><?php esc_html_e('Wipe all plugin data upon uninstall.', 'luciditi-age-assurance'); ?><br><span style="color:red; font-size: 80%; margin-left:20px;"><?php esc_html_e('(This will delete all sessions, settings and all database entries associated with the plugin.)', 'luciditi-age-assurance'); ?></span></label></td>
										</tr>
									</tbody>
								</table>
						<?php
								break;
							default:
								break;
						endswitch;
						?>
					</div>
					<hr>
					</hr>

					<div class="luciditi-notice notice notice-info inline">
						<p>
							<?php echo wp_kses(__('<strong>DISCLAIMER:</strong> This application is provided by Arissian Ltd on an "as is" and "as available" basis. Arissian makes no representations or warranties of any kind, express or implied, as to the operation of the application or the information, content, materials. To the full extent permissible by applicable law, arissian disclaims all warranties, express or implied, including, but not limited to, implied warranties of merchantability and fitness for a particular purpose. You acknowledge, by your use of the application, that your use of the application is at your sole risk.', 'luciditi-age-assurance'), 'strong'); ?>
						</p>
					</div>


					<p class="submit">
						<?php
						// Save changes button
						submit_button(esc_html__('Save Changes', 'luciditi-age-assurance'), 'primary', _LUCIDITI_AA_PREFIX . '_options_save', false);
						// Download logs button
						$current_url  = luciditi_get_current_url();
						$download_url = add_query_arg('download_logs', true, $current_url);
						echo '<a href="' . esc_url(wp_nonce_url($download_url, 'luciditi-download-logs')) . '" class="button">' . esc_html__('Download Logs', 'luciditi-age-assurance') . '</a>';
						?>
					</p>
				</form>

			</div>
<?php
		}
	}
}
