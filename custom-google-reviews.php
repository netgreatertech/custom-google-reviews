<?php
/**
 * Plugin Name: Custom Google Reviews Pro
 * Plugin URI: https://www.netgreater.com
 * Description: Premium Google Reviews plugin with custom database table storage, manual sync, and native Block Editor integration.
 * Version: 2.3
 * Author: Net Greater Technologies, LLP
 * Author URI: https://www.netgreater.com
 * Text Domain: custom-google-reviews
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// SECURITY: Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CGR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Create a dedicated custom database table upon plugin activation
register_activation_hook( __FILE__, 'cgr_create_database_table' );
function cgr_create_database_table() {
    global $wpdb;
    $table_name      = $wpdb->prefix . 'cgr_reviews';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        author_name varchar(255) NOT NULL,
        profile_photo_url text NOT NULL,
        rating tinyint(1) NOT NULL,
        review_text longtext NOT NULL,
        review_time bigint(20) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

// Require core files
require_once CGR_PLUGIN_DIR . 'includes/class-cgr-admin.php';
require_once CGR_PLUGIN_DIR . 'includes/class-cgr-frontend.php';

function cgr_init_plugin() {
    // Load translation files from the /languages folder
    load_plugin_textdomain( 'custom-google-reviews', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

    // Initialize Admin only when inside wp-admin
    if ( is_admin() ) {
        new CGR_Admin();
    }
    
    // Always initialize frontend for shortcodes
    new CGR_Frontend();
}
add_action( 'plugins_loaded', 'cgr_init_plugin' );

// Register Gutenberg Block Securely
function cgr_register_blocks() {
    if ( ! function_exists( 'register_block_type' ) ) return;

    wp_register_script(
        'cgr-block-js',
        plugins_url( 'includes/block.js', __FILE__ ),
        array( 'wp-blocks', 'wp-element', 'wp-server-side-render' ),
        '2.3',
        true
    );

    register_block_type( 'cgr/google-reviews', array(
        'api_version'     => 2,
        'editor_script'   => 'cgr-block-js',
        'render_callback' => 'cgr_render_block_callback'
    ) );
}
add_action( 'init', 'cgr_register_blocks' );

// Block Render Callback
function cgr_render_block_callback() {
    if ( ! class_exists( 'CGR_Frontend' ) ) {
        require_once CGR_PLUGIN_DIR . 'includes/class-cgr-frontend.php';
    }
    $frontend = new CGR_Frontend();
    return $frontend->render_shortcode();
}