<?php

/**
 * The Template for displaying the age assurance "disallowed" landing page
 *
 * This template can be overridden by copying it to yourtheme/body/disallowed.php.
 *
 */

if (!defined('ABSPATH')) {
    die;
}
?>
<form class="card-container">
    <div class="luciditi-box-content card">
        <!-- By default we'll show the Luciditi brand svg here, if the user has defined a logo image we'll use that instead -->
        <img src="<?php echo esc_url_raw($logo); ?>" class="brand" title="Luciditi" />

        <h1 class="luciditi-box-title"><?php esc_html_e('Access Forbidden', 'luciditi-age-assurance'); ?></h1>

        <p class="luciditi-box-desc"><?php echo wp_kses_post(wpautop($message)); ?></p>

        <?php
        // If we are in WooCommerce mode, we need to allow the user to go back/cancel.
        // This to allow them to delete products or change their cart.
        if ('woocommerce' === $mode) {
        ?>
            <div class="luciditi-box-buttons">
                <a href="<?php echo esc_url(luciditi_get_referer()); ?>" class="button luciditi-wc-return">
                    <span><?php echo esc_html__('OK', 'luciditi-age-assurance'); ?></span>
                </a>
            </div>

            <a href="<?php echo esc_url(luciditi_get_referer()); ?>" class="luciditi-wc-cancel-av">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="white" viewBox="0 0 24 24">
                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" />
                </svg>
            </a>
        <?php } ?>

        <!-- A spinner that is displayed by default and hidden with JS after making necessary checks ( check previously completed verfications..etc ) -->
        <div id="luciditi-loader" <?php echo isset($_GET['resetagecheck']) ? ' class="active"' : ''; ?>><i class="luciditi-spinner"><i></i><i></i><i></i></i></div>

    </div>
    <img src="<?php echo esc_url_raw(_LUCIDITI_AA_URL . '/includes/assets/img/powered-by-luciditi.svg'); ?>" class="powered-by-luciditi" />
</form>