<?php
/*
Plugin Name: My Delivery Plugin
Plugin URI: https://example.com/
Description: Custom delivery shipping and tracking plugin for WooCommerce.
Version: 1.0
Author: Your Name
Author URI: https://example.com/
Text Domain: my-delivery-plugin
Domain Path: /languages/
*/

// Exit if accessed directly.
if ( !defined('ABSPATH') ) exit;


// Check if WooCommerce is active
$woocommerce_activated = in_array('woocommerce/woocommerce.php', get_option('active_plugins'));

if ( !$woocommerce_activated ) {
  function munsp_error_notice() {
      ?><div class="error notice">
        <p><?php
          echo esc_html__('You need to install WooCommerce for Delivery shipping to work.', 'munsp');
        ?></p>
      </div><?php
  }
  add_action('admin_notices', 'munsp_error_notice');
  return;
}




// Enqueue plugin styles and scripts
function my_delivery_plugin_enqueue_scripts() {
    wp_enqueue_style('my-delivery-plugin-style', plugin_dir_url(__FILE__) . 'assets/css/style.css');
    wp_enqueue_script('my-delivery-plugin-script', plugin_dir_url(__FILE__) . 'assets/js/script.js', array('jquery'), '1.0', true);
}
add_action('wp_enqueue_scripts', 'my_delivery_plugin_enqueue_scripts');

// Register custom delivery post type
function my_delivery_plugin_register_post_type() {
    $labels = array(
        'name' => 'Deliveries',
        'singular_name' => 'Delivery',
        'menu_name' => 'Deliveries',
        'add_new' => 'Add New',
        'add_new_item' => 'Add New Delivery',
        'edit' => 'Edit',
        'edit_item' => 'Edit Delivery',
        'new_item' => 'New Delivery',
        'view' => 'View',
        'view_item' => 'View Delivery',
        'search_items' => 'Search Deliveries',
        'not_found' => 'No deliveries found',
        'not_found_in_trash' => 'No deliveries found in trash',
        'parent' => 'Parent Delivery'
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'has_archive' => true,
        'menu_icon' => 'dashicons-truck',
        'supports' => array('title', 'editor', 'thumbnail'),
    );

    register_post_type('delivery', $args);
}
add_action('init', 'my_delivery_plugin_register_post_type');

// Add custom delivery meta fields
function my_delivery_plugin_add_meta_boxes() {
    add_meta_box(
        'delivery_meta_box',
        'Delivery Information',
        'my_delivery_plugin_render_meta_box',
        'delivery',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'my_delivery_plugin_add_meta_boxes');

function my_delivery_plugin_render_meta_box($post) {
    // Render your custom meta fields here
}

// Save custom delivery meta field data
function my_delivery_plugin_save_meta_data($post_id) {
    // Check if the nonce is set.
    if (!isset($_POST['my_delivery_plugin_meta_box_nonce'])) {
        return;
    }

    // Verify that the nonce is valid.
    if (!wp_verify_nonce($_POST['my_delivery_plugin_meta_box_nonce'], 'my_delivery_plugin_save_meta_data')) {
        return;
    }

    // Update delivery meta fields
    if (isset($_POST['delivery_tracking_number'])) {
        update_post_meta($post_id, 'delivery_tracking_number', sanitize_text_field($_POST['delivery_tracking_number']));
    }

    if (isset($_POST['delivery_status'])) {
        update_post_meta($post_id, 'delivery_status', sanitize_text_field($_POST['delivery_status']));
    }
}
add_action('save_post', 'my_delivery_plugin_save_meta_data');

// Create a shortcode for delivery tracking
function my_delivery_plugin_delivery_tracking_shortcode($atts) {
    $atts = shortcode_atts(array(
        'order_id' => '',
    ), $atts);

    if (empty($atts['order_id'])) {
        return '<p>Please provide an order ID.</p>';
    }

    $order_id = absint($atts['order_id']);
    $order = wc_get_order($order_id);

    if (!$order) {
        return '<p>Order not found.</p>';
    }

    $tracking_number = get_post_meta($order_id, 'delivery_tracking_number', true);
    $delivery_status = get_post_meta($order_id, 'delivery_status', true);

    $html = '<h3>Delivery Tracking</h3>';
    $html .= '<p>Order ID: ' . $order_id . '</p>';
    $html .= '<p>Tracking Number: ' . $tracking_number . '</p>';
    $html .= '<p>Delivery Status: ' . $delivery_status . '</p>';

    return $html;
}
add_shortcode('delivery_tracking', 'my_delivery_plugin_delivery_tracking_shortcode');
