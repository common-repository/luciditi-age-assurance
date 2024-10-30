<?php

/**
 * The Template for displaying the age assurance landing page header
 *
 * This template can be overridden by copying it to yourtheme/components/header.php.
 *
 */

if (!defined('ABSPATH')) {
    die;
}
?>
<!doctype html>
<html <?php language_attributes(); ?> style="--luciditi-pcolor:<?php echo esc_attr($primary_color); ?>; --luciditi-scolor:<?php echo esc_attr($secondary_color); ?>; --luciditi-tcolor:<?php echo esc_attr($text_color); ?>;">

<head>
    <meta charset="<?php bloginfo('charset'); ?>">

    <!-- Force IE to use the latest rendering engine available -->
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <!-- Mobile Meta -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <!-- Luciditi SDK meta -->
    <meta name="luciditi-startup-token" content="">
    <meta name="luciditi-launch-immediately" content="false">
    <meta name="luciditi-close-window-on-finish" content="false" />

    <link rel="profile" href="https://gmpg.org/xfn/11">

    <?php
    // Render the title tag
    echo '<title>' . esc_html(wp_get_document_title()) . '</title>' . "\n";

    // Print out plugin styles
    wp_print_styles(_LUCIDITI_AA_PREFIX . '-styles');

    // Enqueue our plugin scripts
    wp_enqueue_script(_LUCIDITI_AA_PREFIX . '-scripts');

    // Remove all previously registered scripts.
    // We are doing this to make sure only the plugin scripts
    // are loaded to prevent loading unnecessary JS files sent
    // by WordPress, the theme and other plugins.
    global $wp_scripts;
    $deps = array();
    if (isset($wp_scripts->registered[_LUCIDITI_AA_PREFIX . '-scripts'])) {
        $deps = $wp_scripts->registered[_LUCIDITI_AA_PREFIX . '-scripts']->deps;
    }

    foreach ($wp_scripts->registered as $handle => $val) {
        if (_LUCIDITI_AA_PREFIX . '-scripts' !== $handle) {
            // Make sure it's not a dependnecy before removing it
            $is_dep = false;
            foreach ($deps as $dep) {
                if (strpos($handle, $dep) === 0) {
                    $is_dep = true;
                    break;
                }
            }
            // Move to next handle if this is a dependency
            if ($is_dep) {
                continue;
            }
            wp_deregister_script($handle);
        }
    }
    // Enqueue the plugin scripts, then instruct wp to print out the head scripts
    print_head_scripts();

    // Load the customizer CSS
    wp_custom_css_cb();
    // Load the site icon
    wp_site_icon();

    ?>

</head>

<body class="luciditi-body">
    <div id="luciditi-age-assurance">
        <div id="luciditi-box" data-type="<?php echo esc_attr($type); ?>" data-id="<?php echo esc_attr($session_id); ?>" data-min-age="<?php echo absint($min_age); ?>">