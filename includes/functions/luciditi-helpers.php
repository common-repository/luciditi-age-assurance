<?php

/**
 * This file is used to produce all helper functions related to this plugin.
 *
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!function_exists('luciditi_is_bot')) :

    function luciditi_is_bot()
    {

        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $user_agent = sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']));
            return preg_match('/rambler|abacho|acoi|accona|aspseek|altavista|estyle|scrubby|lycos|geona|ia_archiver|alexa|sogou|skype|facebook|twitter|pinterest|linkedin|naver|bing|google|yahoo|duckduckgo|yandex|baidu|teoma|xing|java\/1.7.0_45|bot|crawl|slurp|spider|mediapartners|\sask\s|\saol\s/i', $user_agent);
        }

        return false;
    }

endif;

if (!function_exists('luciditi_get_user_ip')) :

    function luciditi_get_user_ip()
    {
        // Compatible with CloudFlare
        // Get real visitor IP behind CloudFlare network
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
            $_SERVER['HTTP_CLIENT_IP'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                // Handle the case where there are multiple proxies involved
                // HTTP_X_FORWARDED_FOR can have multiple ip like '1.1.1.1,2.2.2.2'
                foreach (array_map('trim', explode(',', $_SERVER[$key])) as $ip) {
                    // Remove port number if present
                    if (strpos($ip, ':') !== false) {
                        $ip = current(explode(':', $ip));
                    }
                    // Filter private and/or reserved IPs;
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            } else {
                // Work if $_SERVER was not available.
                foreach (array_map('trim', explode(',', getenv($key))) as $ip) {
                    // Remove port number if present
                    if (strpos($ip, ':') !== false) {
                        $ip = current(explode(':', $ip));
                    }
                    // Filter private and/or reserved IPs;
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return false;
    }

endif;

if (!function_exists('luciditi_get_user_type')) :

    function luciditi_get_user_type()
    {
        // Evaluate bots
        if (luciditi_is_bot()) {
            return 'bot';
        }
        // Evaluate admin and WordPress users
        $is_user_logged_in = is_user_logged_in();
        if ($is_user_logged_in) {
            if (current_user_can('manage_options')) {
                return 'admin';
            } else {
                $previously_verified_user = get_user_meta(get_current_user_id(), luciditi_prepare_key('verified_user'), true);
                if ('yes' === $previously_verified_user) {
                    return 'logged_in_verified_user';
                }
            }
        }
        /**
         * Evaluate session type based on session ID.
         * We have 3 session cookie types:
         * - Initial: first time visitors with no database record
         * - Temp: first time visitors with a temp database record
         * - Database: existing visitor/user with a valid database record
         */
        $session    = luciditi_session()->get_session_cookie();
        $session_id = $session[0] ?? false;
        if ($session_id) {
            /**
             * If the `session_id` is an md5 hash, then we only have
             * a cookie session and no database records.
             * This indicates that this is a first time visitor, and
             * they have not started the verification process yet.
             */
            if (strlen($session_id) === 32 && ctype_xdigit($session_id)) {
                // return visitor type, no validation required.
                return 'first_time_visitor';
            }
            /**
             * If the `session_id` starts with 'tmp_', then we have a
             * temporary session stored in the databse for this visitor
             * This indicates that this visitor has started the verification
             * process but didn't complete.
             */
            elseif (strpos($session_id, 'tmp_') === 0) {
                // Since the tmp_sessions in the database are temporary,
                // we don't need to perform any validation here. However,
                // We need to check if this tmp session needs to be converted
                // to a persistent session before going forward.
                // If the session is still in `pending` state We'll pull the
                // session, in the next step, from the database for this
                // visitor and use Luciditi session keys if they are still valid.
                // Otherwise, we'll create a new Luciditi session if the
                // they decided to re-start their verification process.

                $session = luciditi_session()->get_session($session_id);

                if ($session) {
                    // If the tmp session is still in pending state, return `returning_unverified_visitor`
                    if ('pending' == $session->state) {
                        return 'returning_unverified_visitor';
                    } else {
                        // If the session is no longer pending, create a persistent session
                        // and delete the current tmp session. Finally, refresh the page.
                        $session_data = array(
                            'code_id'       => $session->state_data['code_id'] ?? '',
                            'data'          => $session->state_data['data'] ?? '',
                            'creation_date' => $session->state_data['creation_date'] ?? '',
                            'expiry_date'   => $session->state_data['expiry_date'] ?? '',
                            'state'         => $session->state,
                            'user_agent'    => $session->user_agent,
                        );
                        // Create the persistent session
                        luciditi_session()->create_session($session_data);
                        // Delete the tmp session
                        luciditi_session()->delete_session($session_id);
                        // Refresh the page
                        $current_url = luciditi_get_current_url();
                        if (wp_redirect($current_url)) {
                            exit;
                        }
                    }
                }
            }
            /**
             * If the `session_id` has the character `_`, then we
             * have a failed session stored in the database for this visitor.
             * This indicates that this visitor aborted, self declared as
             * under age, or failed the verification process.
             */
            elseif (strpos($session_id, '_') !== false) {
                // Pull the failed session from the database
                $session = luciditi_session()->get_session($session_id);
                if ($session) {
                    if (isset($session->data) && !empty($session->data['tmp_session'])) {
                        $tmp_session = luciditi_session()->get_session($session->data['tmp_session']);
                        if ($tmp_session) {
                            // If the tmp session is not pending, we'll update the main session
                            if ('pending' !== $tmp_session->state) {
                                // If the session is no longer pending, update the exustubg persistent session
                                // and delete the current tmp session. Finally, refresh the page.
                                $session_data = array();
                                $session_data['state'] = $tmp_session->state;
                                $session_data['data'] = $tmp_session->state_data['data'] ?? '';
                                $session_data['user_agent'] = $tmp_session->user_agent;

                                if (isset($tmp_session->state_data['code_id'])) {
                                    $session_data['code_id'] = $tmp_session->state_data['code_id'];
                                }
                                if (isset($tmp_session->state_data['creation_date'])) {
                                    $session_data['creation_date'] = $tmp_session->state_data['creation_date'];
                                }
                                if (isset($tmp_session->state_data['expiry_date'])) {
                                    $session_data['expiry_date'] = $tmp_session->state_data['expiry_date'];
                                }

                                // Update the existing persistent session
                                luciditi_session()->update_session($session_id, $session_data);

                                // Delete the tmp session
                                luciditi_session()->delete_session($session->data['tmp_session']);
                                // Refresh the page
                                $current_url = luciditi_get_current_url();
                                if (wp_redirect($current_url)) {
                                    exit;
                                }
                            }
                        }
                    }
                    // Check if this session is for a "self declaring under age" user.
                    elseif (
                        isset($session->data) &&
                        isset($session->data['type']) &&
                        (
                            'self_declaring_under_age' === $session->data['type'] ||
                            'aborted' === $session->data['type'] ||
                            'aborted_or_self_declared' === $session->data['type']
                        )
                    ) {

                        // We are using a special template "disallowed" when the user abort/self-declare first time,
                        // but we need tp fallback to default behaviour on subsequent visits, so we'll just change
                        // the session to make sure the user is not "disallowed" on their next visit, and can retry.
                        $session_data = (array)$session;
                        $session_data['data']['type'] = 'previously_' . $session_data['data']['type'];
                        luciditi_session()->update_session($session_id, $session_data);

                        // Now return `self_declaring_under_age` type to display our one time template to the user.
                        // On their next visit, they'll fallback to `failed_validation_visitor` automatically.
                        return 'self_declaring_under_age';
                    }

                    return 'failed_validation_visitor';
                }
            }
            /**
             * If the `session_id` has the character `-`, then we
             * have a valid session stored in the database for this visitor.
             * This indicates that this visitor completed the verification
             * successfuly.
             */
            elseif (strpos($session_id, '-') !== false) {
                // Verify if the visitor session id against the database
                // to make sure it's valid. However, to avoid database calls
                // on each page load, we'll only verify against the database
                // once per day. Subsequent verifications will use cookies
                // and other techniqus to verify the validity of `session_id`

                // Get user http agent
                $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

                // Define the daily cookie name
                $cookiehash = (defined('COOKIEHASH')) ? COOKIEHASH : md5(home_url());
                $cookiename = 'wordpress_luciditi_d_' . $cookiehash;

                // Pull the existing value, if available
                $daily_cookie_value = isset($_COOKIE[$cookiename]) ? sanitize_text_field(wp_unslash($_COOKIE[$cookiename])) : '';

                // Prepare the expected cookie value to use for comparision
                $code_id                  = strstr($session_id, '-', true); // Return the string before the occurance of '-'
                $raw_session_id           = strstr($session_id, '-'); // Return the string from the occurance of '-'
                $raw_session_id           = str_replace('-', '', $raw_session_id); // Clean the session id by deleting '-'
                $daily_cookie_to_evaluate = md5($raw_session_id . gmdate('-Y-m-d-') . $code_id) . '.' . md5($user_agent);

                // Now check if our daily cookie is valid ( and user is not logged, if logged in, we need to associate their age with their account )
                if ($daily_cookie_value === $daily_cookie_to_evaluate && !$is_user_logged_in) {
                    return 'verified_user';
                } else {
                    // If the daily cookie is not valid, or doesn't exist, pull the session
                    // from the database and do the necessary evaluation. Once done, save
                    // a new daily cookie to be used for validation for a single day.
                    $session = luciditi_session()->get_session($session_id);
                    if ($session && 'valid' === $session->state) {
                        if (md5($session->user_agent) == md5($user_agent)) {

                            // Save this validation cookie for 24 hours
                            $daily_cookie_to_save = md5($session->session_id . gmdate('-Y-m-d-') . $session->code_id) . '.' . md5($session->user_agent);
                            luciditi_session()->setcookie($cookiename, $daily_cookie_to_save, time() + intval(60 * 60 * 24), is_ssl(), true);
                            // If the user is logged in, but doesn't have a meta indicating the age they tested for, we need to create on for them
                            if ($is_user_logged_in) {
                                $previously_verified_user     = get_user_meta(get_current_user_id(), luciditi_prepare_key('verified_user'), true);
                                $previously_verified_user_age = get_user_meta(get_current_user_id(), luciditi_prepare_key('verified_user_min_age'), true);

                                if ('yes' !== $previously_verified_user || empty($previously_verified_user_age)) {
                                    $min_age = luciditi_get_minimum_age_from_session($session);
                                    // Now set the `verified_user` meta to `yes`, and also save the user verified min_age as different meta
                                    update_user_meta(get_current_user_id(), luciditi_prepare_key('verified_user'), 'yes');
                                    update_user_meta(get_current_user_id(), luciditi_prepare_key('verified_user_min_age'), $min_age);
                                    return 'logged_in_verified_user';
                                }
                            }
                            return 'verified_user';
                        } else {
                            // If the session id is correct, but the validation failed,
                            // this means it might have been compromised, so it should be deleted.
                            luciditi_session()->delete_session($session->session_id);
                        }
                    }
                }
            }
        }

        return 'first_time_visitor';
    }

endif;

if (!function_exists('luciditi_get')) :

    function luciditi_get($name, $array = null, $fallback = '')
    {
        if (null === $array) {
            // For GET values, strip all HTML tags before sending it back. ( Prevent XSS )
            $val = isset($_GET[$name]) ? wp_kses_post(wp_unslash($_GET[$name])) : $fallback;
        } else {
            $val = isset($array[$name]) ? $array[$name] : $fallback;
        }

        return $val;
    }

endif;

if (!function_exists('luciditi_post')) :

    function luciditi_post($name, $array = null, $stripslashes = true)
    {

        if (null === $array && isset($_POST[$name])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return $stripslashes ? stripslashes_deep(wp_kses_post(wp_unslash($_POST[$name]))) : wp_kses_post(wp_unslash($_POST[$name])); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        } elseif (is_array($array) && isset($array[$name])) {
            return $stripslashes ? stripslashes_deep($array[$name]) : $array[$name]; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        }

        return null;
    }

endif;

if (!function_exists('luciditi_get_option')) :

    function luciditi_get_option($key, $default = '')
    {
        return get_option(luciditi_prepare_key($key), $default);
    }

endif;

if (!function_exists('luciditi_prepare_key')) :

    function luciditi_prepare_key($key)
    {
        return _LUCIDITI_AA_PREFIX . '_' . $key;
    }

endif;

if (!function_exists('luciditi_api_auth')) :

    function luciditi_api_auth($existing_session_id = '')
    {

        // Create a session using the stored API credentials
        $api_creds = luciditi_get_option('api', array());

        // Check if all credentials are populated
        if (!isset($api_creds['key']) || empty($api_creds['key'])) {
            throw new Exception(esc_html__('Age Assurance API credentials are not setup correctly.', 'luciditi-age-assurance'));
        }

        // Send the order to Ordercentraal
        $auth_options = array(
            'headers'     => array(
                'Content-Type' => 'application/json',
                'accept'       => 'application/json',
            ),
            'body'        => wp_json_encode(
                array(
                    'apiKey' => $api_creds['key'],
                ),
            ),
            'timeout'     => 60,
            'httpversion' => '1.1',
            // 'redirection' => 5,
            // 'blocking'    => true,
            // 'sslverify'   => true,
            // 'data_format' => 'body',
        );

        $auth_response = wp_remote_post(_LUCIDITI_AA_API_URL . '/auth/ApiKey', $auth_options);
        if (is_wp_error($auth_response)) {
            throw new Exception(esc_html($auth_response->get_error_message()));
        } else {

            $response = (object) array();
            $response_body = wp_remote_retrieve_body($auth_response);
            if (!empty($response_body)) {
                $response = json_decode($response_body);
            }

            if (200 === wp_remote_retrieve_response_code($auth_response)) {
                if (
                    isset($response->sessionExchangeIv) &&
                    isset($response->sessionExchangeKey) &&
                    isset($response->accessToken) &&
                    isset($response->secretKeyClear)
                ) {
                    // Each successful auth must be saved to the database for subsequent use
                    $tmp_session_data = array(
                        'session_key'      => $response->sessionExchangeKey,
                        'session_iv'       => $response->sessionExchangeIv,
                        'access_token'     => $response->accessToken,
                        'secret_key_clear' => $response->secretKeyClear,
                        'session_expiry'   => time() + $response->expiresInSeconds,
                        'state_data'       => array(
                            'userId'   => $response->expiresInSeconds,
                            'userName' => $response->userName,
                        ),
                    );
                    // The session cookie should be updated with the newly generated session ID by default
                    $update_session_cookie = true;

                    // Check the type of the session ID that was provided.
                    // If the given session id belongs to a `failed_session` we should NOT update
                    // the session cookie. On the other hand, we should update the actual session
                    // record with the newly generated tmp session ID.
                    $existing_failed_session = false;
                    $existing_session_type   = luciditi_session()->get_session_identifiers($existing_session_id, 'type');
                    if ('failed_session' === $existing_session_type) {
                        $existing_failed_session = luciditi_session()->get_session($existing_session_id);
                        if ($existing_failed_session) {
                            $update_session_cookie = false;
                        }
                    }

                    // Now save the temporary session to the database
                    $session_id = luciditi_session()->create_session($tmp_session_data, true, $update_session_cookie);

                    // If the existing failed session exists, update it with the new tmp session id
                    if ($existing_failed_session) {
                        if (!is_array($existing_failed_session->data)) {
                            $existing_failed_session->data = array(
                                'tmp_session' => $session_id,
                            );
                        } else {
                            $existing_failed_session->data['tmp_session'] = $session_id;
                        }

                        luciditi_session()->update_session(
                            $existing_session_id,
                            array(
                                'state' => 'failed',
                                'data'  => $existing_failed_session->data,
                            ),
                        );
                    }

                    // Now return the response
                    return array(
                        'sessionId'      => $session_id,
                        'sessionKey'     => $response->sessionExchangeKey,
                        'sessionIv'      => $response->sessionExchangeIv,
                        'accessToken'    => $response->accessToken,
                        'secretKeyClear' => $response->secretKeyClear,
                    );
                } else {
                    throw new Exception(esc_html__('Age Assurance API failed authentication.', 'luciditi-age-assurance'));
                }
            } else if (isset($response->error)) {
                // translators: the error message
                throw new Exception(sprintf(esc_html__('Something went wrong (%s).', 'luciditi-age-assurance'), esc_html($response->error)));
            } else {
                // translators: the error code number
                throw new Exception(sprintf(esc_html__('Something went wrong (error code %d).', 'luciditi-age-assurance'), esc_html(wp_remote_retrieve_response_code($auth_response))));
            }
        }

        return false;
    }

endif;

if (!function_exists('luciditi_api_geolocation')) :

    function luciditi_api_geolocation()
    {

        // Create a session using the stored API credentials
        $api_creds = luciditi_get_option('api', array());

        // Check if all credentials are populated
        if (!isset($api_creds['key']) || empty($api_creds['key'])) {
            throw new Exception(esc_html__('Age Assurance API credentials are not setup correctly.', 'luciditi-age-assurance'));
        }

        // Get User IP
        $user_ip = luciditi_get_user_ip();
        if (empty($user_ip) || '::1' === $user_ip) {
            throw new Exception(esc_html__('User IP could not be detected.', 'luciditi-age-assurance'));
        }

        // Prepare the request options
        $geo_options = array(
            'headers'     => array(
                'x-api-key'    => $api_creds['key'],
                'Content-Type' => 'application/json',
                'accept'       => 'application/json',
            ),
            'body'        =>  array(
                'ipAddress' => $user_ip,
            ),
            'timeout'     => 60,
            'httpversion' => '1.1',
            // 'redirection' => 5,
            // 'blocking'    => true,
            // 'sslverify'   => true,
            // 'data_format' => 'body',
        );

        // Make the geolocation request
        $geo_response = wp_remote_get(_LUCIDITI_AA_API_URL . '/geo-location', $geo_options);
        if (is_wp_error($geo_response)) {
            throw new Exception(esc_html($geo_response->get_error_message()));
        } else {

            $response      = (object) array();
            $response_body = wp_remote_retrieve_body($geo_response);
            if (!empty($response_body)) {
                $response = json_decode($response_body);
            }

            if (200 === wp_remote_retrieve_response_code($geo_response)) {
                if (!empty($response->country)) {
                    return $response;
                } else {
                    throw new Exception(esc_html__('Geolocation failed. Request has been succesfull but no valid location was returned.', 'luciditi-age-assurance'));
                }
            } elseif (isset($response->error)) {
                // translators: the error message
                throw new Exception(sprintf(esc_html__('Something went wrong with geolocation (%s).', 'luciditi-age-assurance'), $response->error));
            } else {
                // translators: the error code number
                throw new Exception(sprintf(esc_html__('Geolocation failed. Request failed with error code %d.', 'luciditi-age-assurance'), wp_remote_retrieve_response_code($auth_response)));
            }
        }

        return false;
    }

endif;

if (!function_exists('luciditi_get_template_filename')) :

    function luciditi_get_template_filename($parts, $template_name)
    {

        // Make sure the template directry is set correctly
        if (is_array($parts)) {
            $file_name_parts = $parts;
        } else {
            $file_name_parts = array($parts);
        }
        // Add filename to the array and split everything to return correct path
        $file_name_parts[] = $template_name . '.php';
        return implode('/', $file_name_parts);
    }

endif;
if (!function_exists('luciditi_locate_template')) :

    function luciditi_locate_template($template_name, $args)
    {

        // Default templates path
        $default_path = _LUCIDITI_AA_PATH . 'includes/templates/';

        // Extract array element into variables to be used inside the template
        if (!empty($args) && is_array($args)) {
            extract($args);
        }

        // Get default template.
        $template = false;
        if (file_exists(trailingslashit($default_path) . $template_name)) {
            $template = trailingslashit($default_path) . $template_name;
        }

        $located = $template;
        // Display the template if it exists
        if (file_exists($located)) {
            include $located;
        }
    }

endif;
if (!function_exists('luciditi_locate_template_html')) :

    function luciditi_locate_template_html($template_name, $args = array(), $echo = false)
    {
        // Like luciditi_locate_template, but returns the HTML instead of including the file directly.
        if (!$echo) {
            ob_start();
        }

        luciditi_locate_template($template_name, $args);

        if (!$echo) {
            return ob_get_clean();
        }
    }

endif;

if (!function_exists('luciditi_get_current_url')) :

    function luciditi_get_current_url()
    {
        // Check if HTTPS is on, then set the protocol
        $protocol = is_ssl() ? 'https://' : 'http://';

        // Use HTTP_HOST (if available) or SERVER_NAME as the host
        $host = isset($_SERVER['HTTP_HOST']) ? wp_unslash($_SERVER['HTTP_HOST']) : (isset($_SERVER['SERVER_NAME']) ? wp_unslash($_SERVER['SERVER_NAME']) : '');

        // Construct the base URL
        $base_url = $protocol . $host;

        // Get the URI if available
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        // return after constructing the full URL and sanitizing it
        return esc_url_raw($base_url . $uri);
    }

endif;

if (!function_exists('luciditi_url_to_postid')) :

    function luciditi_url_to_postid($url)
    {
        global $wpdb;

        // Remove home URL to get the path
        $home_url = home_url();
        if (strpos($url, $home_url) === 0) {
            $url = substr($url, strlen($home_url));
        }

        // Normalize and split URL
        $url = trim($url, '/');
        $url_parts = explode('/', $url);

        // Guess based on URL structure
        if (!empty($url_parts)) {
            // Check for post slug (last part of URL)
            $slug = sanitize_title(end($url_parts));

            // Query the database for the post ID by slug
            $post_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_name = %s LIMIT 1", $slug));

            if ($post_id) {
                return absint($post_id);
            }
        }

        return 0; // Fallback if no match found
    }

endif;

if (!function_exists('luciditi_get_referer')) :

    function luciditi_get_referer($mode = null)
    {

        if ($mode !== null) {
            $enabled_mode = $mode;
        } else {
            $enabled_mode = luciditi_get_option('enable_mode', '');
        }

        // Use the WordPress function to get the referer.
        $previous_page = wp_get_original_referer();

        // If no valid referer is found, default to the WooCommerce cart page.
        if ('woocommerce' === $enabled_mode && false === $previous_page) {
            $previous_page = wc_get_cart_url();
        }

        return !empty($previous_page) ? $previous_page : home_url();
    }

endif;

if (!function_exists('luciditi_get_geo_rules')) :

    function luciditi_get_geo_rules()
    {

        return array(
            'allowed_with_aa'    => esc_html__('Age Assurance Required', 'luciditi-age-assurance'),
            'allowed_without_aa' => esc_html__('Allow Access', 'luciditi-age-assurance'),
            'disallowed'         => esc_html__('Deny Access', 'luciditi-age-assurance'),
        );
    }

endif;

if (!function_exists('luciditi_get_countries')) :

    function luciditi_get_countries()
    {

        return array(
            'AF' => 'Afghanistan',
            'AL' => 'Albania',
            'DZ' => 'Algeria',
            'AS' => 'American Samoa',
            'AD' => 'Andorra',
            'AO' => 'Angola',
            'AI' => 'Anguilla',
            'AQ' => 'Antarctica',
            'AG' => 'Antigua and Barbuda',
            'AR' => 'Argentina',
            'AM' => 'Armenia',
            'AW' => 'Aruba',
            'AU' => 'Australia',
            'AT' => 'Austria',
            'AZ' => 'Azerbaijan',
            'BS' => 'Bahamas',
            'BH' => 'Bahrain',
            'BD' => 'Bangladesh',
            'BB' => 'Barbados',
            'BY' => 'Belarus',
            'BE' => 'Belgium',
            'BZ' => 'Belize',
            'BJ' => 'Benin',
            'BM' => 'Bermuda',
            'BT' => 'Bhutan',
            'BO' => 'Bolivia',
            'BA' => 'Bosnia and Herzegovina',
            'BW' => 'Botswana',
            'BV' => 'Bouvet Island',
            'BR' => 'Brazil',
            'IO' => 'British Indian Ocean Territory',
            'BN' => 'Brunei Darussalam',
            'BG' => 'Bulgaria',
            'BF' => 'Burkina Faso',
            'BI' => 'Burundi',
            'KH' => 'Cambodia',
            'CM' => 'Cameroon',
            'CA' => 'Canada',
            'CV' => 'Cape Verde',
            'KY' => 'Cayman Islands',
            'CF' => 'Central African Republic',
            'TD' => 'Chad',
            'CL' => 'Chile',
            'CN' => 'China',
            'CX' => 'Christmas Island',
            'CC' => 'Cocos (Keeling) Islands',
            'CO' => 'Colombia',
            'KM' => 'Comoros',
            'CG' => 'Congo',
            'CD' => 'Congo, the Democratic Republic of the',
            'CK' => 'Cook Islands',
            'CR' => 'Costa Rica',
            'CI' => "Cote D'Ivoire",
            'HR' => 'Croatia',
            'CU' => 'Cuba',
            'CY' => 'Cyprus',
            'CZ' => 'Czech Republic',
            'DK' => 'Denmark',
            'DJ' => 'Djibouti',
            'DM' => 'Dominica',
            'DO' => 'Dominican Republic',
            'EC' => 'Ecuador',
            'EG' => 'Egypt',
            'SV' => 'El Salvador',
            'GQ' => 'Equatorial Guinea',
            'ER' => 'Eritrea',
            'EE' => 'Estonia',
            'ET' => 'Ethiopia',
            'FK' => 'Falkland Islands (Malvinas)',
            'FO' => 'Faroe Islands',
            'FJ' => 'Fiji',
            'FI' => 'Finland',
            'FR' => 'France',
            'GF' => 'French Guiana',
            'PF' => 'French Polynesia',
            'TF' => 'French Southern Territories',
            'GA' => 'Gabon',
            'GM' => 'Gambia',
            'GE' => 'Georgia',
            'DE' => 'Germany',
            'GH' => 'Ghana',
            'GI' => 'Gibraltar',
            'GR' => 'Greece',
            'GL' => 'Greenland',
            'GD' => 'Grenada',
            'GP' => 'Guadeloupe',
            'GU' => 'Guam',
            'GT' => 'Guatemala',
            'GN' => 'Guinea',
            'GW' => 'Guinea-Bissau',
            'GY' => 'Guyana',
            'HT' => 'Haiti',
            'HM' => 'Heard Island and Mcdonald Islands',
            'VA' => 'Holy See (Vatican City State)',
            'HN' => 'Honduras',
            'HK' => 'Hong Kong',
            'HU' => 'Hungary',
            'IS' => 'Iceland',
            'IN' => 'India',
            'ID' => 'Indonesia',
            'IR' => 'Iran, Islamic Republic of',
            'IQ' => 'Iraq',
            'IE' => 'Ireland',
            'IL' => 'Israel',
            'IT' => 'Italy',
            'JM' => 'Jamaica',
            'JP' => 'Japan',
            'JO' => 'Jordan',
            'KZ' => 'Kazakhstan',
            'KE' => 'Kenya',
            'KI' => 'Kiribati',
            'KP' => "Korea, Democratic People's Republic of",
            'KR' => 'Korea, Republic of',
            'KW' => 'Kuwait',
            'KG' => 'Kyrgyzstan',
            'LA' => "Lao People's Democratic Republic",
            'LV' => 'Latvia',
            'LB' => 'Lebanon',
            'LS' => 'Lesotho',
            'LR' => 'Liberia',
            'LY' => 'Libyan Arab Jamahiriya',
            'LI' => 'Liechtenstein',
            'LT' => 'Lithuania',
            'LU' => 'Luxembourg',
            'MO' => 'Macao',
            'MK' => 'Macedonia, the Former Yugoslav Republic of',
            'MG' => 'Madagascar',
            'MW' => 'Malawi',
            'MY' => 'Malaysia',
            'MV' => 'Maldives',
            'ML' => 'Mali',
            'MT' => 'Malta',
            'MH' => 'Marshall Islands',
            'MQ' => 'Martinique',
            'MR' => 'Mauritania',
            'MU' => 'Mauritius',
            'YT' => 'Mayotte',
            'MX' => 'Mexico',
            'FM' => 'Micronesia, Federated States of',
            'MD' => 'Moldova, Republic of',
            'MC' => 'Monaco',
            'MN' => 'Mongolia',
            'MS' => 'Montserrat',
            'MA' => 'Morocco',
            'MZ' => 'Mozambique',
            'MM' => 'Myanmar',
            'NA' => 'Namibia',
            'NR' => 'Nauru',
            'NP' => 'Nepal',
            'NL' => 'Netherlands',
            'AN' => 'Netherlands Antilles',
            'NC' => 'New Caledonia',
            'NZ' => 'New Zealand',
            'NI' => 'Nicaragua',
            'NE' => 'Niger',
            'NG' => 'Nigeria',
            'NU' => 'Niue',
            'NF' => 'Norfolk Island',
            'MP' => 'Northern Mariana Islands',
            'NO' => 'Norway',
            'OM' => 'Oman',
            'PK' => 'Pakistan',
            'PW' => 'Palau',
            'PS' => 'Palestinian Territory, Occupied',
            'PA' => 'Panama',
            'PG' => 'Papua New Guinea',
            'PY' => 'Paraguay',
            'PE' => 'Peru',
            'PH' => 'Philippines',
            'PN' => 'Pitcairn',
            'PL' => 'Poland',
            'PT' => 'Portugal',
            'PR' => 'Puerto Rico',
            'QA' => 'Qatar',
            'RE' => 'Reunion',
            'RO' => 'Romania',
            'RU' => 'Russian Federation',
            'RW' => 'Rwanda',
            'SH' => 'Saint Helena',
            'KN' => 'Saint Kitts and Nevis',
            'LC' => 'Saint Lucia',
            'PM' => 'Saint Pierre and Miquelon',
            'VC' => 'Saint Vincent and the Grenadines',
            'WS' => 'Samoa',
            'SM' => 'San Marino',
            'ST' => 'Sao Tome and Principe',
            'SA' => 'Saudi Arabia',
            'SN' => 'Senegal',
            'CS' => 'Serbia and Montenegro',
            'SC' => 'Seychelles',
            'SL' => 'Sierra Leone',
            'SG' => 'Singapore',
            'SK' => 'Slovakia',
            'SI' => 'Slovenia',
            'SB' => 'Solomon Islands',
            'SO' => 'Somalia',
            'ZA' => 'South Africa',
            'GS' => 'South Georgia and the South Sandwich Islands',
            'ES' => 'Spain',
            'LK' => 'Sri Lanka',
            'SD' => 'Sudan',
            'SR' => 'Suriname',
            'SJ' => 'Svalbard and Jan Mayen',
            'SZ' => 'Swaziland',
            'SE' => 'Sweden',
            'CH' => 'Switzerland',
            'SY' => 'Syrian Arab Republic',
            'TW' => 'Taiwan, Province of China',
            'TJ' => 'Tajikistan',
            'TZ' => 'Tanzania, United Republic of',
            'TH' => 'Thailand',
            'TL' => 'Timor-Leste',
            'TG' => 'Togo',
            'TK' => 'Tokelau',
            'TO' => 'Tonga',
            'TT' => 'Trinidad and Tobago',
            'TN' => 'Tunisia',
            'TR' => 'Turkey',
            'TM' => 'Turkmenistan',
            'TC' => 'Turks and Caicos Islands',
            'TV' => 'Tuvalu',
            'UG' => 'Uganda',
            'UA' => 'Ukraine',
            'AE' => 'United Arab Emirates',
            'GB' => 'United Kingdom',
            'US' => 'United States',
            'UM' => 'United States Minor Outlying Islands',
            'UY' => 'Uruguay',
            'UZ' => 'Uzbekistan',
            'VU' => 'Vanuatu',
            'VE' => 'Venezuela',
            'VN' => 'Viet Nam',
            'VG' => 'Virgin Islands, British',
            'VI' => 'Virgin Islands, U.s.',
            'WF' => 'Wallis and Futuna',
            'EH' => 'Western Sahara',
            'YE' => 'Yemen',
            'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe',
        );
    }

endif;

if (!function_exists('luciditi_get_us_states')) :

    function luciditi_get_us_states()
    {
        return array(
            'AL' => 'Alabama',
            'AK' => 'Alaska',
            'AZ' => 'Arizona',
            'AR' => 'Arkansas',
            'CA' => 'California',
            'CO' => 'Colorado',
            'CT' => 'Connecticut',
            'DE' => 'Delaware',
            'DC' => 'District Of Columbia',
            'FL' => 'Florida',
            'GA' => 'Georgia',
            'HI' => 'Hawaii',
            'ID' => 'Idaho',
            'IL' => 'Illinois',
            'IN' => 'Indiana',
            'IA' => 'Iowa',
            'KS' => 'Kansas',
            'KY' => 'Kentucky',
            'LA' => 'Louisiana',
            'ME' => 'Maine',
            'MD' => 'Maryland',
            'MA' => 'Massachusetts',
            'MI' => 'Michigan',
            'MN' => 'Minnesota',
            'MS' => 'Mississippi',
            'MO' => 'Missouri',
            'MT' => 'Montana',
            'NE' => 'Nebraska',
            'NV' => 'Nevada',
            'NH' => 'New Hampshire',
            'NJ' => 'New Jersey',
            'NM' => 'New Mexico',
            'NY' => 'New York',
            'NC' => 'North Carolina',
            'ND' => 'North Dakota',
            'OH' => 'Ohio',
            'OK' => 'Oklahoma',
            'OR' => 'Oregon',
            'PA' => 'Pennsylvania',
            'RI' => 'Rhode Island',
            'SC' => 'South Carolina',
            'SD' => 'South Dakota',
            'TN' => 'Tennessee',
            'TX' => 'Texas',
            'UT' => 'Utah',
            'VT' => 'Vermont',
            'VA' => 'Virginia',
            'WA' => 'Washington',
            'WV' => 'West Virginia',
            'WI' => 'Wisconsin',
            'WY' => 'Wyoming',
        );
    }

endif;

if (!function_exists('luciditi_get_uk_regions')) :

    function luciditi_get_uk_regions()
    {
        // Define an associative array of UK regions
        // The keys are the region codes, and the values are the region names
        return array(
            'ENG' => 'England',
            'SCT' => 'Scotland',
            'WLS' => 'Wales',
            'NIR' => 'Northern Ireland',
        );
    }

endif;

if (!function_exists('get_mf_conditional_rules')) :

    function get_mf_conditional_rules($action, $rules, $logic = 'or', $container = 'tr')
    {

        return wp_json_encode(
            array(
                'container' => $container,
                'action'    => $action,
                'logic'     => $logic,
                'rules'     => $rules,
            )
        );
    }

endif;

if (!function_exists('luciditi_maybe_clear_user_age_meta')) :

    function luciditi_maybe_clear_user_age_meta()
    {
        // If the user is logged in, delete their age meta
        if (is_user_logged_in()) {
            delete_user_meta(get_current_user_id(), luciditi_prepare_key('verified_user'));
            delete_user_meta(get_current_user_id(), luciditi_prepare_key('verified_user_min_age'));
        }
    }

endif;

if (!function_exists('luciditi_maybe_clear_daily_cookie')) :

    function luciditi_maybe_clear_daily_cookie()
    {
        // Reset the daily cookie
        $cookiehash = (defined('COOKIEHASH')) ? COOKIEHASH : md5(home_url());
        $cookiename = 'wordpress_luciditi_d_' . $cookiehash;
        if (isset($_COOKIE[$cookiename])) {
            unset($_COOKIE[$cookiename]);
        }
    }

endif;

if (!function_exists('luciditi_update_fallback_pages_option')) :

    function luciditi_update_fallback_pages_option($page_id, $source_type, $source_id)
    {
        $fallback_pages = luciditi_get_option('fallback_pages', array());
        $key            = "{$source_type}_{$source_id}";

        // Add or update the source
        $fallback_pages[$key] = absint($page_id);
        update_option(luciditi_prepare_key('fallback_pages'), $fallback_pages);
    }

endif;

if (!function_exists('luciditi_remove_fallback_page_option')) :

    function luciditi_remove_fallback_page_option($source_type, $source_id)
    {
        $fallback_pages = luciditi_get_option('fallback_pages', array());
        $key            = "{$source_type}_{$source_id}";

        // Remove the source if it exists
        if (isset($fallback_pages[$key])) {
            unset($fallback_pages[$key]);
            update_option(luciditi_prepare_key('fallback_pages'), $fallback_pages);
        }
    }

endif;

if (!function_exists('luciditi_get_required_minimum_age')) :

    function luciditi_get_required_minimum_age($enable_mode = null, $bypass_current_page_check = false)
    {
        // Pull the current restriction mode
        $mode = '';
        if (null !== $enable_mode) {
            $mode = $enable_mode;
        } else {
            $mode = luciditi_get_option('enable_mode', '');
        }

        // Prepare defaults
        $general_settings = luciditi_get_option('general', array());
        $default_min_age  = luciditi_get('min_age', $general_settings, 18);
        $min_age          = 0;
        if ('woocommerce' === $mode) {
            if (class_exists('woocommerce')) {
                if (is_checkout() || $bypass_current_page_check) {
                    // Loop through all products in the cart
                    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                        $product_id = $cart_item['product_id'];
                        // Check if the product is age restricted and save 
                        $is_product_restricted = luciditi_wc_is_product_restricted($product_id);
                        if ($is_product_restricted) {
                            $product_min_age = get_post_meta($product_id, luciditi_prepare_key('minimum_age'), true);
                            if (!empty($product_min_age) && absint($product_min_age) > $min_age) {
                                $min_age = absint($product_min_age);
                            } elseif (absint($default_min_age) > $min_age) {
                                $min_age = absint($default_min_age);
                            }
                        } else {
                            // If product itself is not restricted, let's check parent categories
                            $is_category_restricted = luciditi_wc_is_product_category_restricted($product_id);
                            if ($is_category_restricted) {
                                $terms = wp_get_post_terms($product_id, 'product_cat');
                                foreach ($terms as $term) {
                                    $cat_min_age = get_term_meta($term->term_id, luciditi_prepare_key('minimum_age'), true);
                                    if (!empty($cat_min_age) && $cat_min_age > $min_age) {
                                        $min_age = absint($cat_min_age);
                                    } elseif (absint($default_min_age) > $min_age) {
                                        $min_age = absint($default_min_age);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } elseif ('page_based' === $mode) {
            // TODO
        }

        return !empty($min_age) ? $min_age : $default_min_age;
    }

endif;
if (!function_exists('luciditi_get_minimum_age_from_session')) :

    function luciditi_get_minimum_age_from_session($session)
    {
        $min_age = 0;
        // Check if we have a different & greater age in the user session
        if (isset($session->data) && !empty($session->data['min_age'])) {
            $min_age = $session->data['min_age'];
        } elseif (isset($session->state_data) && !empty($session->state_data['min_age'])) {
            $min_age = $session->state_data['min_age'];
        }

        // Note that the returned age is just the minimum and not a verified min age because we check both, temp and valid sessions.
        // Meaning, that we also return the `min_age` field from pending or failed sessions if they're provided.
        return absint($min_age);
    }

endif;

if (!function_exists('luciditi_get_verified_minimum_age_from_usermeta')) :

    function luciditi_get_verified_minimum_age_from_usermeta()
    {

        $min_age = 0;
        // Check if the user is logged in, and try to pull their min_age from their metadata
        if (is_user_logged_in()) {
            $previously_verified_user     = get_user_meta(get_current_user_id(), luciditi_prepare_key('verified_user'), true);
            $previously_verified_user_age = get_user_meta(get_current_user_id(), luciditi_prepare_key('verified_user_min_age'), true);
            if ('yes' === $previously_verified_user && !empty($previously_verified_user_age)) {
                $min_age = $previously_verified_user_age;
            } else {
                // If no meta is set, we will fallback to checking their session
                $session    = luciditi_session()->get_session_cookie();
                $session_id = $session[0] ?? false;
                if ($session_id) {
                    /**
                     * If the `session_id` has the character `-`, then we
                     * have a valid session stored in the database for this visitor.
                     * This indicates that this visitor completed the verification
                     * successfuly.
                     */
                    if (strpos($session_id, '-') !== false) {
                        $session = luciditi_session()->get_session($session_id);
                        if ($session && 'valid' === $session->state) {
                            $min_age = luciditi_get_minimum_age_from_session($session);
                            // Now set the `verified_user` meta to `yes`, and also save the user verified min_age as different meta
                            update_user_meta(get_current_user_id(), luciditi_prepare_key('verified_user'), 'yes');
                            update_user_meta(get_current_user_id(), luciditi_prepare_key('verified_user_min_age'), $min_age);
                        }
                    }
                }
            }
        }
        return absint($min_age);
    }

endif;

if (!function_exists('luciditi_maybe_get_page_redirect_link')) :

    function luciditi_maybe_get_page_redirect_link($page_id)
    {
        // TODO: this function is not tested, verify everything before use

        // // First, check if the page itself is restricted
        // $redirect_type = get_post_meta($page_id, luciditi_prepare_key('redirection'), true);
        // if ('page' === $redirect_type) {
        //     // Use the first found redirect link for a page
        //     return get_the_permalink(get_post_meta($page_id, luciditi_prepare_key('fallback_page'), true));
        // } elseif ('redirect' === $redirect_type) {
        //     // Use the first found redirect URL for a page
        //     return get_post_meta($page_id, luciditi_prepare_key('fallback_url'), true);
        // } elseif ('none' === $redirect_type) {
        //     // Return and empty string to indicate that we should use default behaviour, this means showing an error message instead of a redirection.
        //     return '';
        // }

        // // Then, check if any of the page's categories (if applicable) are restricted
        // $terms = wp_get_post_terms($page_id, 'category'); // Assuming pages are using the 'category' taxonomy, which may require custom code to enable
        // foreach ($terms as $term) {
        //     $redirect_type = get_term_meta($term->term_id, luciditi_prepare_key('redirection'), true);
        //     if ('page' === $redirect_type) {
        //         $page_id = get_term_meta($term->term_id, luciditi_prepare_key('fallback_page'), true);
        //         if ($page_id) {
        //             // Use the first found redirect link for a category
        //             return get_the_permalink($page_id);
        //         }
        //     } elseif ('redirect' === $redirect_type) {
        //         $url = get_term_meta($term->term_id, luciditi_prepare_key('fallback_url'), true);
        //         if ($url) {
        //             // Use the first found redirect URL for a category
        //             return $url;
        //         }
        //     } elseif ('none' === $redirect_type) {
        //         // Return and empty string to indicate that we should use default behaviour, this means showing an error message instead of a redirection.
        //         return '';
        //     }
        // }

        return false; // Return false if no restrictions or redirects are found
    }

endif;
