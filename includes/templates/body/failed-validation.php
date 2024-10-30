<?php

/**
 * The Template for displaying the age assurance "failed validation" landing page
 *
 * This template can be overridden by copying it to yourtheme/body/first-time.php.
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

        <h1 class="luciditi-box-title"><?php esc_html_e('Age Verification', 'luciditi-age-assurance'); ?></h1>
        <p class="luciditi-box-desc"><?php echo wp_kses_post(wpautop($message)); ?></p>
        <div class="luciditi-box-buttons">
            <button type="submit" class="luciditi-init-button">
                <span><?php echo esc_attr($button_text); ?></span>
                <i class="luciditi-spinner"><i></i><i></i><i></i></i>
            </button>
            <?php if ($self_declare_enabled) : ?>
                <a href="<?php echo esc_url($self_declare_redirect); ?>" class="luciditi-self-declare<?php echo '#' === $self_declare_redirect ? ' luciditi-self-declare-default' : ''; ?>">
                    <?php echo esc_attr($self_declare_button_text); ?>
                </a>
            <?php endif; ?>
        </div>

        <!-- A spinner that is displayed by default and hidden with JS after making necessary checks ( check previously completed verfications..etc ) -->
        <div id="luciditi-loader" <?php echo isset($_GET['resetagecheck']) ? ' class="active"' : ''; ?>><i class="luciditi-spinner"><i></i><i></i><i></i></i></div>

        <?php if ('woocommerce' === $mode) { ?>
            <!-- Allow the user to return to the previous page ( usually cart page ) -->
            <a href="<?php echo esc_url(luciditi_get_referer()); ?>" class="luciditi-wc-cancel-av">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="white" viewBox="0 0 24 24">
                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" />
                </svg>
            </a>
        <?php } ?>
    </div>
    <img src="<?php echo esc_url_raw(_LUCIDITI_AA_URL . '/includes/assets/img/powered-by-luciditi.svg'); ?>" class="powered-by-luciditi" />
</form>