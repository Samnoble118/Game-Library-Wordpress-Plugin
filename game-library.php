<?php
/**
 * Plugin Name: Game Library Plugin
 * Description: This plugin allows customers to save games to their personal library.
 * Version: 1.0
 * Author: Sam Noble
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Enqueue scripts
function glm_enqueue_scripts() {
    wp_enqueue_script('glm-script', plugin_dir_url(__FILE__) . 'js/game-library.js', array('jquery'), null, true);
    wp_localize_script('glm-script', 'glm_ajax_obj', array('ajax_url' => admin_url('admin-ajax.php')));
}
add_action('wp_enqueue_scripts', 'glm_enqueue_scripts');

// Add "Add to Library" button on product pages
function glm_add_to_library_button() {
    if (is_product()) {
        global $product;
        $product_id = $product->get_id();
        $user_id = get_current_user_id();
        $user_library = get_user_meta($user_id, 'game_library', true);

        $owned = is_array($user_library) && in_array($product_id, $user_library);

        if ($owned) {
            echo '<button class="glm-remove-library" data-product-id="' . esc_attr($product_id) . '">Remove from Library</button>';
        } else {
            echo '<button class="glm-add-library" data-product-id="' . esc_attr($product_id) . '">Add to Library</button>';
        }
    }
}
add_action('woocommerce_after_add_to_cart_button', 'glm_add_to_library_button');

// Handle AJAX requests to add/remove games from the library
function glm_toggle_game_library() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'You must be logged in to add games to your library']);
    }

    $product_id = intval($_POST['product_id']);
    $user_id = get_current_user_id();
    $user_library = get_user_meta($user_id, 'game_library', true);

    if (!is_array($user_library)) {
        $user_library = [];
    }

    if (in_array($product_id, $user_library)) {
        $user_library = array_diff($user_library, [$product_id]);
        $status = 'removed';
    } else {
        $user_library[] = $product_id;
        $status = 'added';
    }

    update_user_meta($user_id, 'game_library', $user_library);
    wp_send_json_success(['message' => 'Game ' . $status . ' from library', 'status' => $status]);
}
add_action('wp_ajax_glm_toggle_game_library', 'glm_toggle_game_library');
add_action('wp_ajax_nopriv_glm_toggle_game_library', 'glm_toggle_game_library');

// Shortcode to display user's game library
function glm_display_game_library() {
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to view your game library.</p>';
    }

    $user_id = get_current_user_id();
    $user_library = get_user_meta($user_id, 'game_library', true);

    if (!is_array($user_library) || empty($user_library)) {
        return '<p>Your game library is empty.</p>';
    }

    $output = '<div class="glm-game-library">';
    foreach ($user_library as $product_id) {
        $product = wc_get_product($product_id);
        if ($product) {
            $image = $product->get_image();
            $output .= '<div class="glm-game-item">';
            $output .= '<a href="' . esc_url(get_permalink($product_id)) . '">' . $image . '<br>' . esc_html($product->get_name()) . '</a>';
            $output .= '<button class="glm-remove-library" data-product-id="' . esc_attr($product_id) . '">Remove</button>';
            $output .= '</div>';
        }
    }
    $output .= '</div>';
    return $output;
}
add_shortcode('game_library', 'glm_display_game_library');

// Ensure the JS directory exists and create the JavaScript file
$js_dir = plugin_dir_path(__FILE__) . 'js/';
if (!file_exists($js_dir)) {
    mkdir($js_dir, 0755, true);
}

file_put_contents($js_dir . 'game-library.js', "
    jQuery(document).ready(function($) {
        $('.glm-add-library, .glm-remove-library').click(function() {
            var button = $(this);
            var productId = button.data('product-id');

            $.post(glm_ajax_obj.ajax_url, {
                action: 'glm_toggle_game_library',
                product_id: productId
            }, function(response) {
                if (response.success) {
                    if (response.data.status === 'added') {
                        button.text('Remove from Library').removeClass('glm-add-library').addClass('glm-remove-library');
                    } else {
                        button.text('Add to Library').removeClass('glm-remove-library').addClass('glm-add-library');
                        button.closest('.glm-game-item').fadeOut();
                    }
                }
            });
        });
    });
");
