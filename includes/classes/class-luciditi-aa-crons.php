<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Luciditi_AA_Crons
{

    /**
     * The key name of our daily cron.
     *
     */
    public static $cron_daily_hook = _LUCIDITI_AA_PREFIX . '_daily_tasks';

    public function __construct()
    {

        /**
         * Hooks actions into the created cron jobs
         *
         */
        add_action(_LUCIDITI_AA_PREFIX . '_daily_tasks', array($this, 'clear_temp_sessions'));
    }


    public static function setup_daily_cron()
    {

        if (!wp_next_scheduled(self::$cron_daily_hook)) {
            $first_run = time();
            wp_schedule_event($first_run, 'daily', self::$cron_daily_hook);
        }
    }

    public static function clear_all()
    {
        if (wp_next_scheduled(self::$cron_daily_hook)) {
            wp_clear_scheduled_hook(self::$cron_daily_hook);
        }
    }

    /**
     * Clear temporary sessions
     *
     * @since    1.0.0
     */
    public function clear_temp_sessions()
    {

            // Clear temporary sessions here
    }
}

new Luciditi_AA_Crons();
