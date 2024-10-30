<?php


if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

if (is_admin() && !defined('DOING_AJAX')) {
	return; // Exit if current request is coming from admin screen, but is not an ajax request
}
if (defined('DOING_CRON')) {
	return; // Exit if current request is coming from a cron job
}

class Luciditi_AA_Session
{

	/**
	 * The single instance of the class.
	 *
	 * @var Luciditi_AA_Session
	 * @since 1.0.0
	 */
	protected static $_instance = null;

	/**
	 * Cookie name used for the session.
	 *
	 * @var string cookie name
	 */
	protected $_cookie;

	/**
	 * The latest cookie value.
	 * We use this to make sure we can get values set
	 * by `setcookie()` function on the same page load.
	 *
	 * @var string cookie value
	 */
	protected $_cookie_value;

	/**
	 * Session ID.
	 *
	 * @var string The current session unique ID
	 */
	protected $_session_id;
	/**
	 * Stores session expiry.
	 *
	 * @var string session due to expire timestamp
	 */
	protected $_session_expiring;

	/**
	 * Stores session due to expire timestamp.
	 *
	 * @var string session expiration timestamp
	 */
	protected $_session_expiration;

	/**
	 * True when the cookie exists.
	 *
	 * @var bool Based on whether a cookie exists.
	 */
	protected $_has_cookie = false;

	/**
	 * Table name for tmp session data.
	 *
	 * @var string Custom tmp session table name
	 */
	public $_tmp_table;
	/**
	 * Table name for session data.
	 *
	 * @var string Custom session table name
	 */
	public $_table;

	/**
	 * The key for session cookie
	 * We are using the `wordpress_` prefix to ensure to cookie is ignored
	 * by some caching plugins, and hosting providers (eg; WPEngine).
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $session_cookie_key = 'wordpress_luciditi';
	/**
	 * The key for session cache
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $session_cache_key = 'luciditi_session_cache';

	/**
	 * Main Luciditi_AA_Session Instance.
	 *
	 * Ensures only one instance of Luciditi_AA_Session is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @see luciditi_session()
	 * @return Luciditi_AA_Session - Main instance.
	 */
	public static function instance()
	{
		if (is_null(self::$_instance)) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Init hooks and session data.
	 *
	 */
	public function start()
	{

		$cookiehash = (defined('COOKIEHASH')) ? COOKIEHASH : md5(home_url());

		global $wpdb;
		$this->_cookie    = $this->session_cookie_key . '_' . $cookiehash;
		$this->_tmp_table = $wpdb->prefix . 'luciditi_tmp_sessions';
		$this->_table     = $wpdb->prefix . 'luciditi_sessions';

		add_action('plugins_loaded', array($this, 'init_session'), 0);
	}

	/**
	 * Init Mega Forms session hooks and data.
	 *
	 */
	public function init_session()
	{

		$this->init_session_cookie();

		// Set session cookie when the form is loaded only
		add_action('plugins_loaded', array($this, 'set_session_cookie'), 1);
	}


	/**
	 * Setup cookie and session ID.
	 *
	 * @since 3.6.0
	 */
	public function init_session_cookie()
	{

		$cookie = $this->get_session_cookie();

		if ($cookie) {

			// To be modified
			$this->_session_id         = $cookie[0];
			$this->_session_expiration = $cookie[1];
			$this->_session_expiring   = $cookie[2];
			$this->_has_cookie         = true;

			// Update session if its close to expiring.
			if (time() > $this->_session_expiring) {

				$general          = luciditi_get_option('general', array());
				$retention_period = luciditi_get('retention_period', $general, 365);

				$this->set_session_expiration($retention_period);
			}
		} else {

			$general          = luciditi_get_option('general', array());
			$retention_period = luciditi_get('retention_period', $general, 365);

			$this->set_session_expiration($retention_period);
			$this->_session_id = $this->generate_session_id();
		}
	}
	/**
	 * Set cookie.
	 *
	 */
	public function setcookie($name, $value, $expire = 0, $secure = false, $httponly = false)
	{
		if (!headers_sent()) {
			// Set the cookie value in the headers. Note that this
			// will only be available on $_COOKIE on next page load.
			setcookie($name, $value, $expire, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, $secure, $httponly);

			// If this is a session cookie, set `$this->_cookie_value`
			// property to use instead of $_COOKIE for the current page load.
			if ($name == $this->_cookie) {
				$this->_cookie_value = $value;
			}
		} elseif (defined('WP_DEBUG') && WP_DEBUG) {
			headers_sent($file, $line);
			trigger_error("{$name} cookie cannot be set - headers already sent by {$file} on line {$line}", E_USER_NOTICE); // @codingStandardsIgnoreLine
		}
	}
	/**
	 * Sets the session cookie on-demand.
	 *
	 * Warning: Cookies will only be set if this is called before the headers are sent.
	 *
	 */
	public function set_session_cookie()
	{
		$this->_has_cookie = true;
		$to_hash = $this->_session_id . '|' . $this->_session_expiration;
		$cookie_hash = hash_hmac('md5', $to_hash, wp_hash($to_hash));
		$cookie_value = $this->_session_id . '||' . $this->_session_expiration . '||' . $this->_session_expiring . '||' . $cookie_hash;

		if (!isset($_COOKIE[$this->_cookie]) || $_COOKIE[$this->_cookie] !== $cookie_value) {
			$this->setcookie($this->_cookie, $cookie_value, $this->_session_expiration, $this->use_secure_cookie(), true);
		}
	}
	/**
	 * Unsets the session cookie on-demand.
	 *
	 */
	public function unset_session_cookie()
	{
		$this->_has_cookie = false;

		if (isset($_COOKIE[$this->_cookie])) {
			$this->setcookie($this->_cookie, '', -1, $this->use_secure_cookie(), true);
		}
		if (isset($_COOKIE[$this->_cookie])) {
			unset($_COOKIE[$this->_cookie]);
		}
	}

	/**
	 * Should the session cookie be secure?
	 *
	 * @return bool
	 */
	protected function use_secure_cookie()
	{
		return is_ssl();
	}

	/**
	 * Set session expiration.
	 */
	public function set_session_expiration($duration)
	{
		$this->_session_expiring   = time() + intval(60 * 60 * 24 * $duration);
		$this->_session_expiration = time() + intval(60 * 60 * 24 * ($duration + 1));
	}

	/**
	 * Generate a unique user ID for guests, or return user ID if logged in.
	 *
	 * Uses Portable PHP password hashing framework to generate a unique cryptographically strong ID.
	 *
	 * @return string
	 */
	public function generate_session_id()
	{

		require_once ABSPATH . 'wp-includes/class-phpass.php';
		$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
		$hasher     = new PasswordHash(8, false);
		$session_id = md5($hasher->get_random_bytes(32) . base64_encode($user_agent) . time());

		return $session_id;
	}

	/**
	 * Get the session cookie, if set. Otherwise return false.
	 *
	 * Session cookies without a user ID are invalid.
	 *
	 * @return bool|array
	 */
	public function get_session_cookie()
	{
		if (!empty($this->_cookie_value)) {
			$cookie_value = $this->_cookie_value;
		} else {
			$cookie_value = isset($_COOKIE[$this->_cookie]) ? sanitize_text_field(wp_unslash($_COOKIE[$this->_cookie])) : false;
		}

		if (empty($cookie_value) || !is_string($cookie_value)) {
			return false;
		}

		list($session_id, $session_expiration, $session_expiring, $cookie_hash) = explode('||', $cookie_value);

		if (empty($session_id)) {
			return false;
		}

		// Validate hash.
		$to_hash = $session_id . '|' . $session_expiration;
		$hash    = hash_hmac('md5', $to_hash, wp_hash($to_hash));

		if (empty($cookie_hash) || !hash_equals($hash, $cookie_hash)) {
			return false;
		}

		return array($session_id, $session_expiration, $session_expiring, $cookie_hash);
	}


	/**
	 * Gets a cache prefix. This is used in session names so the entire cache can be invalidated with 1 function call.
	 *
	 * @return string
	 */
	private function get_cache_prefix()
	{
		return _LUCIDITI_AA_PREFIX . '_cache_' . $this->session_cache_key . '_';
	}

	/**
	 * Returns the session.
	 *
	 * @param array $data the data that should be saved to the created session.
	 * @param bool  $is_temp are we creating a tmp session or persistent one.
	 * @param bool  $update_session_cookie whether we should update our cookie with the new session.
	 * @return int
	 */
	public function create_session($data, $is_temp = false, $update_session_cookie = true)
	{

		global $wpdb;

		if ($is_temp) {
			$table = $this->_tmp_table;
		} else {
			$table = $this->_table;
		}

		// Prepare data
		if ($is_temp) {
			$prepared_data = array(
				'session_key'      => isset($data['session_key']) ? sanitize_text_field($data['session_key']) : '',
				'session_iv'       => isset($data['session_iv']) ? sanitize_text_field($data['session_iv']) : '',
				'access_token'     => isset($data['access_token']) ? sanitize_text_field($data['access_token']) : '',
				'secret_key_clear' => isset($data['secret_key_clear']) ? sanitize_text_field($data['secret_key_clear']) : '',
				'session_expiry'   => isset($data['session_expiry']) ? (int) $data['session_expiry'] : '',
				'state'            => isset($data['state']) ? sanitize_key($data['state']) : 'pending',
				'state_data'       => is_array($data['state_data']) && !empty($data['state_data']) ? maybe_serialize($data['state_data']) : maybe_serialize(array()),
				'user_agent'       => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : 'N/A',
			);

			$format = array('%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s');
		} else {
			// Prepare expiry date
			$expiry_date = !empty($data['expiry_date']) ? $data['expiry_date'] : '';
			if (empty($expiry_date)) {
				$general          = luciditi_get_option('general', array());
				$retention_period = luciditi_get('retention_period', $general, 365);
				$expiry_date      = time() + intval(60 * 60 * 24 * absint($retention_period));
			}
			// Prepare all the data
			$prepared_data = array(
				'code_id'       => !empty($data['code_id']) ? sanitize_text_field($data['code_id']) : md5(time()),
				'data'          => is_array($data['data']) && !empty($data['data']) ? maybe_serialize($data['data']) : maybe_serialize(array()),
				'creation_date' => !empty($data['creation_date']) ? (int) $data['creation_date'] : time(),
				'expiry_date'   => $expiry_date,
				'state'         => !empty($data['state']) ? sanitize_key($data['state']) : '',
				'user_agent'    => !empty($data['user_agent']) ? sanitize_text_field($data['user_agent']) : sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])),
			);

			$format = array('%s', '%s', '%s', '%d', '%s', '%s');
		}

		$result = $wpdb->insert($table, $prepared_data, $format);

		if (!$result) {
			return 0;
		}

		// Get the new session id
		$cid = absint($wpdb->insert_id);
		if ($is_temp) {
			$session_id = 'tmp_' . $cid;
		} else {
			$session_id = 'failed' === $prepared_data['state'] ? $prepared_data['code_id'] . '_' . $cid : $prepared_data['code_id'] . '-' . $cid;
		}

		// Update session cookie
		if ($update_session_cookie) {
			// Save the session id
			$this->_session_id = $session_id;
			// Update the session cookie with the new id. This will work mainly
			// for temp sessions, but will not work for presistant sessions,
			// because they are triggered externally, not from the user browser.
			// The only exception is calls from `ajax_validate_external_session`
			// since it runs on the user browser.
			$this->set_session_cookie();
		}

		return $session_id;
	}
	/**
	 * Returns the session.
	 *
	 * @param string $session_id Custom ID.
	 * @param mixed  $default Default session value.
	 * @return object|bool
	 */
	public function get_session($session_id, $default = false)
	{
		// Extract session id and other identifiers from the `$session_id` variable
		$identifiers = luciditi_session()->get_session_identifiers($session_id);
		if (!$identifiers || empty($identifiers['session_id']) || (!('tmp_session' == $identifiers['type']) && empty($identifiers['code_id']))) {
			return false;
		}

		// We'll save the session id as defined in the cookie to avoid conflict saving and pulling cache.
		$cookie_session_id = $session_id;
		// Try to get it from the cache, it will return false if not present or if object cache not in use.
		$value = wp_cache_get($this->get_cache_prefix() . $cookie_session_id, $this->session_cache_key);

		if (false === $value) {

			global $wpdb;
			if ('tmp_session' == $identifiers['type']) {
				$table = $this->_tmp_table;
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$query = $wpdb->prepare("SELECT * FROM $table WHERE session_id = %d", $identifiers['session_id']);
			} else {
				$table = $this->_table;
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$query = $wpdb->prepare("SELECT * FROM $table WHERE session_id = %d AND code_id = %s", $identifiers['session_id'], $identifiers['code_id']);
			}

			$result = $wpdb->get_row($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if (!empty($result)) {

				if (isset($result->state_data)) {
					$result->state_data = maybe_unserialize($result->state_data);
				}
				if (isset($result->data)) {
					$result->data = maybe_unserialize($result->data);
				}

				$value = $result;
			} else {
				$value = $default;
			}

			$cache_duration = $this->_session_expiration - time();

			if (0 < $cache_duration) {
				wp_cache_add($this->get_cache_prefix() . $cookie_session_id, $value, $this->session_cache_key, $cache_duration);
			}
		}

		return maybe_unserialize($value);
	}

	/**
	 * Delete the session from the cache and database.
	 *
	 * @param int $session_id Session ID.
	 */
	public function delete_session($session_id)
	{
		// Extract session id and other identifiers from the `$session_id` variable
		$identifiers = luciditi_session()->get_session_identifiers($session_id);
		if (!$identifiers || empty($identifiers['session_id']) || (!('tmp_session' == $identifiers['type']) && empty($identifiers['code_id']))) {
			return false;
		}

		// Delete from cache
		wp_cache_delete($this->get_cache_prefix() . $session_id, $this->session_cache_key);

		global $wpdb;
		// Delete from database ( make sure it's deleted from the right table )
		if ('tmp_session' == $identifiers['type']) {
			return $wpdb->delete(
				$this->_tmp_table,
				array('session_id' => $identifiers['session_id']),
				array('%s'),
			);
		} else {
			return $wpdb->delete(
				$this->_table,
				array(
					'session_id' => $identifiers['session_id'],
					'code_id' => $identifiers['code_id'],
				),
				array('%s', '%s')
			);
		}
	}
	/**
	 * Update the session state and data.
	 *
	 * @param int|string $session_id Session ID.
	 * @param array      $data new session state.
	 * @param bool       $is_temp are we updating a temporary session.
	 */
	public function update_session($session_id, $data, $is_temp = false)
	{

		// Extract session id and other identifiers from the `$session_id` variable
		$identifiers = luciditi_session()->get_session_identifiers($session_id);

		if (!$identifiers || empty($identifiers['session_id']) || (!$is_temp && empty($identifiers['code_id']))) {
			return false;
		}

		global $wpdb;
		if ($is_temp) {
			$table = $this->_tmp_table;
		} else {
			$table = $this->_table;
		}

		// Prepare data
		if ($is_temp) {

			// Available properties and formats
			$formats = array(
				'session_key'      => '%s',
				'session_iv'       => '%s',
				'access_token'     => '%s',
				'secret_key_clear' => '%s',
				'session_expiry'   => '%d',
				'state'            => '%s',
				'state_data'       => '%s',
				'user_agent'       => '%s',
			);

			// Prepare data and data format ( cleaning and sanitization )
			$prepared_data = array();
			$data_format = array();
			foreach ($data as $key => $val) {
				if (isset($formats[$key])) {
					$sanitized_value = '';
					switch ($key) {
						case 'state_data':
							$sanitized_value = is_array($val) ? maybe_serialize($val) : maybe_serialize(array());
							break;
						default:
							$sanitized_value = sanitize_text_field($val);
							break;
					}
					$prepared_data[$key] = $sanitized_value;
					$data_format[] = $formats[$key];
				}
			}

			// Prepare where and where format
			$where = array('session_id' => $identifiers['session_id']);
			$where_format = array('%d');
		} else {

			// Available properties and formats
			$formats = array(
				'code_id'       => '%s',
				'data'          => '%s',
				'creation_date' => '%d',
				'expiry_date'   => '%d',
				'state'         => '%s',
				'user_agent'    => '%s',
			);

			// Prepare data and data format ( cleaning and sanitization )
			$prepared_data = array();
			$data_format = array();
			foreach ($data as $key => $val) {
				if (isset($formats[$key])) {
					$sanitized_value = '';
					switch ($key) {
						case 'data':
							$sanitized_value = is_array($val) ? maybe_serialize($val) : maybe_serialize(array());
							break;
						default:
							$sanitized_value = sanitize_text_field($val);
							break;
					}
					$prepared_data[$key] = $sanitized_value;
					$data_format[] = $formats[$key];
				}
			}

			// Prepare where and where format
			$where = array(
				'session_id' => $identifiers['session_id'],
				'code_id'    => $identifiers['code_id'],
			);
			$where_format = array('%d');
		}

		$updated = $wpdb->update($table, $prepared_data, $where, $data_format, $where_format);

		// If the `state` is updated for a non temp session, we need to update the session cookie
		if ($updated && !$is_temp && isset($prepared_data['state'])) {
			// prepare the id
			$_code_id    = !empty($prepared_data['code_id']) ? $prepared_data['code_id'] : $identifiers['code_id'];
			$_session_id = $identifiers['session_id'];
			$this->_session_id = 'failed' === $prepared_data['state'] ? $_code_id . '_' . $_session_id : $_code_id . '-' . $_session_id;
			// Update the session cookie with the new id. This will work mainly
			// for persistent sessions because an update to the `state` of persistent
			// sessions means different session id structure unlike tmp sessions
			$this->set_session_cookie();
		}

		return $updated;
	}
	/**
	 * Cleanup session data from the database and clear caches.
	 */
	public function cleanup_sessions()
	{
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query($wpdb->prepare("DELETE FROM $this->_tmp_table WHERE session_expiry < %d", time()));
	}

	/**
	 * Returns the following session identifiers from the session ID.
	 * - Session Id
	 * - Session Type
	 * - Code ID
	 */
	public function get_session_identifiers($session_id, $return = '')
	{
		$identifiers = array();
		if (strpos($session_id, 'tmp_') === 0) {
			$identifiers['session_id'] = str_replace('tmp_', '', $session_id);
			$identifiers['type']       = 'tmp_session';
		} elseif (strpos($session_id, '_') !== false) {

			$code_id    = strstr($session_id, '_', true); // Return the string before the occurance of '_'
			$session_id = strstr($session_id, '_'); // Return the string from the occurance of '_'
			$session_id = str_replace('_', '', $session_id); // Clean the session id by deleting '_'

			$identifiers['session_id'] = $session_id;
			$identifiers['code_id']    = $code_id;
			$identifiers['type']       = 'failed_session';
		} elseif (strpos($session_id, '-') !== false) {

			$code_id    = strstr($session_id, '-', true); // Return the string before the occurance of '-'
			$session_id = strstr($session_id, '-'); // Return the string from the occurance of '-'
			$session_id = str_replace('-', '', $session_id); // Clean the session id by deleting '-'

			$identifiers['session_id'] = $session_id;
			$identifiers['code_id']    = $code_id;
			$identifiers['type']       = 'valid_session';
		}

		// Only return a specific key if required
		if (!empty($identifiers) && !empty($return) && isset($identifiers[$return])) {
			return $identifiers[$return];
		}

		return !empty($identifiers) ? $identifiers : false;
	}
}

/**
 * Create a helper function that calls an instance of Luciditi_AA_Session
 * so to that the same instance can be called anywhere.
 */
function luciditi_session()
{
	return Luciditi_AA_Session::instance();
}
// Start a session
luciditi_session()->start();
