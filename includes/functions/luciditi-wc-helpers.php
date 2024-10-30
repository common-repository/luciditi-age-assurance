<?php

if (!function_exists('luciditi_wc_cart_has_restricted_product')) :

    function luciditi_wc_cart_has_restricted_product()
    {
        // Start with the assumption that the cart is not restricted
        $cart_is_restricted = false;

        // Loop through all products in the cart
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];

            // Check if the product is age restricted
            if (luciditi_wc_is_product_restricted($product_id) || luciditi_wc_is_product_category_restricted($product_id)) {
                $cart_is_restricted = true;
                break; // Exit the loop if a restricted product is found
            }
        }

        return $cart_is_restricted;
    }

endif;

if (!function_exists('luciditi_wc_is_product_restricted')) :

    function luciditi_wc_is_product_restricted($product_id)
    {
        // Check if the age restriction is enabled for this product
        $is_product_restricted = 'yes' === get_post_meta($product_id, luciditi_prepare_key('enabled'), true);

        return $is_product_restricted;
    }

endif;

if (!function_exists('luciditi_wc_is_product_category_restricted')) :

    function luciditi_wc_is_product_category_restricted($product_id)
    {
        // Get product categories
        $terms = wp_get_post_terms($product_id, 'product_cat');
        foreach ($terms as $term) {
            $is_category_restricted = 'yes' === get_term_meta($term->term_id, luciditi_prepare_key('enabled'), true);
            if ($is_category_restricted) {
                return true;
            }
        }
        return false;
    }

endif;

if (!function_exists('luciditi_wc_maybe_get_product_redirect_link')) :

    function luciditi_wc_maybe_get_product_redirect_link()
    {
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            if (luciditi_wc_is_product_restricted($product_id)) {
                $redirect_type = get_post_meta($product_id, luciditi_prepare_key('redirection'), true);
                if ('page' === $redirect_type) {
                    // Use the first found redirect link
                    return get_the_permalink(get_post_meta($product_id, luciditi_prepare_key('fallback_page'), true));
                } elseif ('redirect' === $redirect_type) {
                    // Use the first found redirect link
                    return get_post_meta($product_id, luciditi_prepare_key('fallback_url'), true);
                } elseif ('none' === $redirect_type) {
                    // Return and empty string to indicate that we should use default behaviour, this means showing an error message instead of a redirection.
                    return '';
                }
            } elseif (luciditi_wc_is_product_category_restricted($product_id)) {
                $terms = wp_get_post_terms($product_id, 'product_cat');
                foreach ($terms as $term) {
                    $redirect_type = get_term_meta($term->term_id, luciditi_prepare_key('redirection'), true);
                    if ('page' === $redirect_type) {
                        $page_id = get_term_meta($term->term_id, luciditi_prepare_key('fallback_page'), true);
                        if ($page_id) {
                            // Use the first found redirect link
                            return get_the_permalink($page_id);
                        }
                    } elseif ('redirect' === $redirect_type) {
                        $url = get_term_meta($term->term_id, luciditi_prepare_key('fallback_url'), true);
                        if ($url) {
                            // Use the first found redirect link
                            return $url;
                        }
                    } elseif ('none' === $redirect_type) {
                        // Return and empty string to indicate that we should use default behaviour, this means showing an error message instead of a redirection.
                        return '';
                    }
                }
            }
        }
        return false;
    }

endif;


if (!function_exists('luciditi_wc_get_product_required_min_age')) :

    function luciditi_wc_get_product_required_min_age($product_id)
    {

        // Prepare defaults
        $general_settings = luciditi_get_option('general', array());
        $min_age          = luciditi_get('min_age', $general_settings, 18);

        // Find min age
        $is_product_restricted = luciditi_wc_is_product_restricted($product_id);
        if ($is_product_restricted || luciditi_wc_is_product_category_restricted($product_id)) {
            if ($is_product_restricted) {
                $product_min_age = get_post_meta($product_id, luciditi_prepare_key('minimum_age'), true);
                if (!empty($product_min_age)) {
                    $min_age = absint($product_min_age);
                }
            } else {
                $is_category_restricted = luciditi_wc_is_product_category_restricted($product_id);
                if ($is_category_restricted) {
                    // If product itself is not restricted, let's check parent categories
                    $terms = wp_get_post_terms($product_id, 'product_cat');
                    foreach ($terms as $term) {
                        $cat_min_age = get_term_meta($term->term_id, luciditi_prepare_key('minimum_age'), true);
                        if (!empty($cat_min_age)) {
                            $min_age = absint($cat_min_age);
                        }
                    }
                }
            }
        }

        return absint($min_age);
    }

endif;

if (!function_exists('luciditi_wc_get_restricted_products_ids')) :

    function luciditi_wc_get_restricted_products_ids($user_verified_age = 0)
    {
        $restricted_products_ids = array();
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            if (luciditi_wc_is_product_restricted($product_id) || luciditi_wc_is_product_category_restricted($product_id)) {
                // If we have a the user age, we should filter products by age and only include the ones equal or greater than the provided age
                if (absint($user_verified_age) > 0) {
                    if (luciditi_wc_get_product_required_min_age($product_id) > absint($user_verified_age)) {
                        $restricted_products_ids[] = $product_id;
                    }
                } else {
                    $restricted_products_ids[] = $product_id;
                }
            }
        }

        return $restricted_products_ids;
    }

endif;

if (!function_exists('luciditi_wc_get_restricted_products_list')) :

    function luciditi_wc_get_restricted_products_list($user_verified_age = 0)
    {
        $restricted_products_ids  = luciditi_wc_get_restricted_products_ids($user_verified_age);
        $restricted_products_list = '<ul class="luciditi-box-wc-products">';
        foreach ($restricted_products_ids as $id) {
            $product                   = wc_get_product($id);
            $restricted_products_list .= '<li>' . $product->get_name() . '</li>';
        }
        $restricted_products_list .= '</ul>';

        return $restricted_products_list;
    }

endif;
