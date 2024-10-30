<?php

/**
 * Plugin Name:       Luciditi Age Assurance
 * Plugin URI:        https://luciditi.co.uk/age-assurance
 * Description:       Add frictionless, privacy-preserving Age Assurance using Luciditi Age Estimation or Age Verification.
 * Version:           1.0.3
 * Author:            Luciditi
 * Author URI:        https://luciditi.co.uk
 * Text Domain:       luciditi-age-assurance
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
	die;
}

define('_LUCIDITI_AA_VERSION', '1.0.3');
define('_LUCIDITI_AA_PATH', plugin_dir_path(__FILE__));
define('_LUCIDITI_AA_URL', plugin_dir_url(__FILE__));
define('_LUCIDITI_AA_PREFIX', 'luciditi_aa');
define('_LUCIDITI_AA_API_URL', 'https://sdk-live3.luciditi-api.net');
define('_LUCIDITI_AA_SDK_URL', 'https://sdk-live3.luciditi-api.net/js/luciditi-sdk.js');
define('_LUCIDITI_AA_UI_SDK_URL', 'https://sdk-live3.luciditi-api.net/js-sdk');

/**
 * Load the plugin class that is used to define activation, deactivation,
 * and uninstall callbacks.
 *
 */
require plugin_dir_path(__FILE__) . 'includes/class-luciditi-age-assurance-actions.php';

/**
 * Register plugin activation, deactivation, and uninstall hooks and their callbacks.
 *
 */

register_activation_hook(__FILE__, 'Luciditi_Age_Assurance_Actions::activate');
register_deactivation_hook(__FILE__, 'Luciditi_Age_Assurance_Actions::deactivate');
register_uninstall_hook(__FILE__, 'Luciditi_Age_Assurance_Actions::uninstall');

/**
 * Load the core plugin class that is used to define all related hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-luciditi-age-assurance.php';

/**
 * Execute the plugin class to kick-off all related functionality,
 * that is registered via hooks.
 *
 * @since    1.0.0
 */
function luciditi_run_luciditi_age_assurance()
{
	new Luciditi_Age_Assurance();
}
luciditi_run_luciditi_age_assurance();
