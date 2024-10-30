<?php

/**
 * The Template for displaying the age assurance landing page footer
 *
 * This template can be overridden by copying it to yourtheme/components/footer.php.
 *
 */

if (!defined('ABSPATH')) {
    die;
}

?>
<?php if (!empty($geolocation_results) && 'yes' === luciditi_get_option('geolocation_enabled', 'no')) : ?>
    <span class="luciditi-muted-notice" data-key="<?php echo sanitize_key($geolocation_results['type']); ?>">
        <?php
        $user_ip   = luciditi_get_user_ip();
        $user_ip   = !empty($user_ip) ? $user_ip : '::1';
        $country   = !empty($geolocation_results['country']) ? $geolocation_results['country'] : 'N/A';
        $extra_msg = 'location_not_detected' === $geolocation_results['type'] ? ' - location not detected.' : '';
        // print the details
        printf('IP %s (%s)%s', esc_attr($user_ip), esc_attr($country), esc_attr($extra_msg));
        // Print a hidden message if `msg` is available.
        if (!empty($geolocation_results['msg'])) {
            echo '<i style="display:none;">' . esc_attr($geolocation_results['msg']) . '</i>';
        }
        ?>
    </span>
<?php endif; ?>
</div><!-- #luciditi-box -->
</div><!-- #luciditi-age-assurance -->
<div id="luciditi-age-assurance-modal" class="luciditi-modal" style="display:none;">
    <div class="luciditi-modal-open">
        <div id="luciditi_modal_inner">
            <a class="close-reveal-modal close"></a>
            <div id="luciditi-verification"></div>
        </div>
    </div>
    <div class="luciditi-modal-bg close-reveal-modal" style="display: block;" data-hook="modal-close"></div>
</div><!-- #luciditi-age-assurance-modal -->

<?php
// Print footer scripts
print_footer_scripts();
?>
</body>