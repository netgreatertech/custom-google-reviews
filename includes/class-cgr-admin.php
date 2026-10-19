<?php
// SECURITY: Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CGR_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_init', array( $this, 'handle_manual_sync' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        
        add_action( 'init', array( $this, 'init_cron' ) );
        add_action( 'cgr_background_sync_hook', array( __CLASS__, 'background_routine' ) );
    }

    public function init_cron() {
        if ( ! wp_next_scheduled( 'cgr_background_sync_hook' ) ) {
            wp_schedule_event( time(), 'hourly', 'cgr_background_sync_hook' );
        }
    }

    public static function background_routine() {
        $last_sync_time = (int) get_option( 'cgr_last_sync_time', 0 );
        $sync_freq      = (int) get_option( 'cgr_sync_freq', 86400 );
        
        if ( current_time( 'timestamp' ) - $last_sync_time >= $sync_freq ) {
            self::fetch_and_store_reviews();
        }
    }

    public function add_admin_menu() {
        add_menu_page( 
            __( 'Google Reviews', 'custom-google-reviews' ), 
            __( 'Google Reviews', 'custom-google-reviews' ), 
            'manage_options', 
            'custom_google_reviews', 
            array( $this, 'settings_page' ), 
            'dashicons-star-filled', 
            25 
        );
    }

    public function enqueue_admin_assets( $hook ) {
        if ( $hook !== 'toplevel_page_custom_google_reviews' ) {
            return;
        }
        wp_enqueue_style( 'swiper-css', 'https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css', array(), '10.0' );
        wp_enqueue_script( 'swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js', array(), '10.0', true );
        
        $assets_url = plugins_url( '../assets', __FILE__ );
        wp_enqueue_style( 'cgr-admin-css', $assets_url . '/css/cgr-admin.css', array(), time() );
        wp_enqueue_script( 'cgr-admin-js', $assets_url . '/js/cgr-admin.js', array(), time(), true );
    }

    public function register_settings() {
        $settings = array(
            'cgr_api_key' => '', 'cgr_place_id' => '', 'cgr_sync_freq' => 86400, 'cgr_layout' => 'minimal_slider',
            'cgr_nav_type' => 'dots', 'cgr_grid_columns' => '3', 'cgr_min_rating' => 4, 'cgr_review_limit' => 5,
            'cgr_autoplay_speed' => 6000, 'cgr_sort_order' => 'highest', 'cgr_excerpt_length' => 160,
            'cgr_show_avatars' => 1, 'cgr_show_date' => 1, 'cgr_show_badge' => 1, 'cgr_show_google_logo' => 1,
            'cgr_section_bg' => 'transparent', 'cgr_bg_color' => '#ffffff', 'cgr_quote_color' => '#333333',
            'cgr_name_color' => '#333333', 'cgr_star_color' => '#F2B01E', 'cgr_dot_color' => '#91C13D',
            'cgr_readmore_color' => '#91C13D', 'cgr_badge_color' => '#91C13D', 
            
            // New Weight and Watermark Settings
            'cgr_quote_size' => '16', 'cgr_quote_weight' => '400',
            'cgr_name_size' => '16', 'cgr_name_weight' => '700',
            'cgr_star_size' => '24', 
            'cgr_badge_size' => '11', 'cgr_badge_weight' => '600',
            'cgr_watermark_color' => '#333333', 'cgr_watermark_size' => '12', 'cgr_watermark_weight' => '400',
            
            'cgr_card_padding' => '40', 'cgr_card_radius' => '12'
        );
        foreach ($settings as $key => $default) {
            register_setting( 'cgr_settings', $key, array('default' => $default) );
        }
    }

    public function handle_manual_sync() {
        if ( isset( $_POST['cgr_manual_sync'] ) && check_admin_referer( 'cgr_manual_sync_nonce', 'cgr_sync_nonce' ) ) {
            if ( current_user_can( 'manage_options' ) ) {
                if ( self::fetch_and_store_reviews() ) {
                    add_settings_error( 'cgr_messages', 'cgr_synced', __( 'Reviews successfully synced! Only reviews matching your minimum star rating were saved.', 'custom-google-reviews' ), 'updated' );
                }
            }
        }
    }

    public static function fetch_and_store_reviews() {
        global $wpdb;
        $api_key  = get_option( 'cgr_api_key' );
        $place_id = get_option( 'cgr_place_id' );
        $min_rating = (int) get_option( 'cgr_min_rating', 4 ); 

        update_option( 'cgr_last_sync_time', current_time( 'timestamp' ) );

        if ( empty( $api_key ) || empty( $place_id ) ) {
            add_settings_error( 'cgr_messages', 'cgr_sync_fail', __( 'Sync failed. Please check your API Key and Place ID.', 'custom-google-reviews' ), 'error' );
            return false;
        }

        $url = sprintf( 'https://places.googleapis.com/v1/places/%s', urlencode( $place_id ) );
        $response = wp_remote_get( $url, array( 
            'timeout' => 5, 
            'headers' => array( 'X-Goog-Api-Key' => $api_key, 'X-Goog-FieldMask' => 'reviews' ) 
        ) );

        if ( is_wp_error( $response ) ) {
            add_settings_error( 'cgr_messages', 'cgr_sync_fail', 'WP Error: ' . $response->get_error_message(), 'error' );
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset( $data['error'] ) ) {
            add_settings_error( 'cgr_messages', 'cgr_sync_fail', 'Google API Error: ' . esc_html( $data['error']['message'] ?? 'Unknown' ), 'error' );
            return false;
        }

        if ( isset( $data['reviews'] ) && is_array( $data['reviews'] ) ) {
            $table_name = $wpdb->prefix . 'cgr_reviews';
            foreach ( $data['reviews'] as $item ) {
                $rating = isset( $item['rating'] ) ? (int) $item['rating'] : 5;
                if ( $rating < $min_rating ) continue; 

                $author_name       = isset( $item['authorAttribution']['displayName'] ) ? sanitize_text_field( $item['authorAttribution']['displayName'] ) : __( 'Anonymous', 'custom-google-reviews' );
                $profile_photo_url = isset( $item['authorAttribution']['photoUri'] ) ? esc_url_raw( $item['authorAttribution']['photoUri'] ) : '';
                $review_text       = isset( $item['text']['text'] ) ? sanitize_textarea_field( $item['text']['text'] ) : '';
                $review_time       = isset( $item['publishTime'] ) ? strtotime( $item['publishTime'] ) : current_time('timestamp');

                $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE author_name = %s AND review_time = %d", $author_name, $review_time ) );
                if ( ! $exists ) {
                    $wpdb->insert( $table_name, array( 'author_name' => $author_name, 'profile_photo_url' => $profile_photo_url, 'rating' => $rating, 'review_text' => $review_text, 'review_time' => $review_time ), array( '%s', '%s', '%d', '%s', '%d' ) );
                }
            }
            return true;
        }
        add_settings_error( 'cgr_messages', 'cgr_sync_fail', __( 'No reviews found for this Place ID.', 'custom-google-reviews' ), 'error' );
        return false;
    }

    public static function get_reviews() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cgr_reviews';
        $results = $wpdb->get_results( 
            "SELECT author_name, profile_photo_url, rating, review_text as text, review_time as time FROM $table_name", 
            ARRAY_A 
        );
        return ( ! empty( $results ) && is_array( $results ) ) ? $results : false;
    }

    public function settings_page() {
        global $wpdb;
        settings_errors( 'cgr_messages' );
        
        $table_name     = $wpdb->prefix . 'cgr_reviews';
        $stored_reviews = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY review_time DESC", ARRAY_A );
        
        $layout         = get_option('cgr_layout', 'minimal_slider');
        $min_rating     = (int) get_option('cgr_min_rating', 4);
        $sort_order     = get_option('cgr_sort_order', 'highest');
        $review_limit   = (int) get_option('cgr_review_limit', 5);

        $filtered_reviews = array();
        if ( ! empty($stored_reviews) ) {
            foreach ( $stored_reviews as $rev ) { if ( (int) $rev['rating'] >= $min_rating ) { $filtered_reviews[] = $rev; } }
            if ( $sort_order === 'highest' ) { usort( $filtered_reviews, function($a, $b) { return ( (int)$b['rating'] <=> (int)$a['rating'] ); }); } 
            elseif ( $sort_order === 'newest' ) { usort( $filtered_reviews, function($a, $b) { return ( (int)$b['review_time'] <=> (int)$a['time'] ); }); } 
            elseif ( $sort_order === 'random' ) { shuffle( $filtered_reviews ); }
        }
        $preview_data = array_slice( $filtered_reviews, 0, $review_limit );

        ?>
        <div class="wrap cgr-admin-wrap">
            
            <div class="cgr-dashboard-header">
                <h1><?php esc_html_e( 'Google Reviews Pro Dashboard', 'custom-google-reviews' ); ?></h1>
                <a href="https://paypal.me/netgreater?locale.x=en_GB&country.x=IN" target="_blank" class="cgr-btn-donate">
                    <span class="dashicons dashicons-heart"></span> <?php esc_html_e( 'Support Development', 'custom-google-reviews' ); ?>
                </a>
            </div>

            <!-- Sync Control Panel -->
            <div class="cgr-panel cgr-panel-sync">
                <div class="cgr-panel-content">
                    <h3><span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Custom Table Sync Control', 'custom-google-reviews' ); ?></h3>
                    <p>
                        <?php 
                        $last_sync = get_option( 'cgr_last_sync_time' );
                        if ( $last_sync ) {
                            printf( esc_html__( 'Last synced: %s ago. Total filtered reviews accumulated in database: %d', 'custom-google-reviews' ), human_time_diff( $last_sync, current_time('timestamp') ), count($stored_reviews) );
                        } else {
                            esc_html_e( 'Never synced yet. Click the button to fetch reviews.', 'custom-google-reviews' );
                        }
                        ?>
                    </p>
                    <small><?php esc_html_e('Note: Automatic syncing is handled by a background Cron job so it never slows down your frontend load times.', 'custom-google-reviews'); ?></small>
                </div>
                <div class="cgr-panel-action">
                    <form method="post" style="margin: 0;">
                        <?php wp_nonce_field( 'cgr_manual_sync_nonce', 'cgr_sync_nonce' ); ?>
                        <button type="submit" name="cgr_manual_sync" class="cgr-btn cgr-btn-primary">
                            <?php esc_attr_e( 'Sync Reviews Now', 'custom-google-reviews' ); ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- DB Table Preview -->
            <div class="cgr-panel">
                <h3 class="cgr-panel-title"><span class="dashicons dashicons-database"></span> <?php esc_html_e( 'All Stored Reviews in Database Table', 'custom-google-reviews' ); ?></h3>
                <?php if ( empty( $stored_reviews ) ) : ?>
                    <p class="cgr-empty-state"><?php esc_html_e( 'No reviews found in custom table. Please enter your API credentials below and click "Sync Reviews Now".', 'custom-google-reviews' ); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped cgr-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;"><?php esc_html_e( 'Avatar', 'custom-google-reviews' ); ?></th>
                                <th style="width: 160px;"><?php esc_html_e( 'Author', 'custom-google-reviews' ); ?></th>
                                <th style="width: 90px;"><?php esc_html_e( 'Rating', 'custom-google-reviews' ); ?></th>
                                <th><?php esc_html_e( 'Review Text', 'custom-google-reviews' ); ?></th>
                                <th style="width: 130px;"><?php esc_html_e( 'Time', 'custom-google-reviews' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( array_slice($stored_reviews, 0, 10) as $rev ) : ?>
                                <tr>
                                    <td>
                                        <?php if ( !empty($rev['profile_photo_url']) ) : ?>
                                            <img src="<?php echo esc_url($rev['profile_photo_url']); ?>" class="cgr-table-avatar" alt="">
                                        <?php else : ?>
                                            <div class="cgr-table-avatar-fallback">?</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo esc_html( $rev['author_name'] ); ?></strong></td>
                                    <td class="cgr-table-stars">
                                        <?php $rating = (int) $rev['rating']; for($i=0; $i<5; $i++) { echo ($i < $rating) ? '★' : '☆'; } ?>
                                    </td>
                                    <td><?php echo esc_html( wp_trim_words( $rev['review_text'], 25 ) ); ?></td>
                                    <td class="cgr-table-time"><?php echo esc_html( human_time_diff( $rev['review_time'], current_time('timestamp') ) . ' ago' ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Settings Form -->
            <form method="post" action="options.php" class="cgr-settings-form">
                <?php settings_fields( 'cgr_settings' ); ?>

                <!-- SECTION 1 -->
                <div class="cgr-panel">
                    <h3 class="cgr-panel-title"><?php esc_html_e( '1. Google Account Connection', 'custom-google-reviews' ); ?></h3>
                    <table class="form-table cgr-form-table">
                        <tr>
                            <th scope="row"><label><?php esc_html_e( 'Google Places API Key', 'custom-google-reviews' ); ?></label></th>
                            <td><input type="text" name="cgr_api_key" id="cgr_api_key" value="<?php echo esc_attr( get_option('cgr_api_key') ); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php esc_html_e( 'Google Place ID', 'custom-google-reviews' ); ?></label></th>
                            <td><input type="text" name="cgr_place_id" id="cgr_place_id" value="<?php echo esc_attr( get_option('cgr_place_id') ); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php esc_html_e( 'Automatic Sync Frequency', 'custom-google-reviews' ); ?></label></th>
                            <td>
                                <select name="cgr_sync_freq" id="cgr_sync_freq">
                                    <option value="43200" <?php selected( get_option('cgr_sync_freq'), 43200 ); ?>><?php esc_html_e( 'Every 12 Hours', 'custom-google-reviews' ); ?></option>
                                    <option value="86400" <?php selected( get_option('cgr_sync_freq', 86400), 86400 ); ?>><?php esc_html_e( 'Every 24 Hours', 'custom-google-reviews' ); ?></option>
                                    <option value="604800" <?php selected( get_option('cgr_sync_freq'), 604800 ); ?>><?php esc_html_e( 'Every 7 Days', 'custom-google-reviews' ); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- SECTION 2 -->
                <div class="cgr-panel">
                    <h3 class="cgr-panel-title"><?php esc_html_e( '2. Choose Your Layout Style', 'custom-google-reviews' ); ?></h3>
                    <div class="cgr-layout-grid">
                        <label class="cgr-layout-card <?php echo $layout === 'minimal_slider' ? 'active' : ''; ?>">
                            <input type="radio" name="cgr_layout" value="minimal_slider" <?php checked($layout, 'minimal_slider'); ?> />
                            <span class="dashicons dashicons-slides"></span>
                            <strong><?php esc_html_e( 'Minimal Text Carousel', 'custom-google-reviews' ); ?></strong>
                        </label>
                        <label class="cgr-layout-card <?php echo $layout === 'card_slider' ? 'active' : ''; ?>">
                            <input type="radio" name="cgr_layout" value="card_slider" <?php checked($layout, 'card_slider'); ?> />
                            <span class="dashicons dashicons-welcome-widgets-menus"></span>
                            <strong><?php esc_html_e( 'Boxed Card Carousel', 'custom-google-reviews' ); ?></strong>
                        </label>
                        <label class="cgr-layout-card <?php echo $layout === 'static_grid' ? 'active' : ''; ?>">
                            <input type="radio" name="cgr_layout" value="static_grid" <?php checked($layout, 'static_grid'); ?> />
                            <span class="dashicons dashicons-dashboard"></span>
                            <strong><?php esc_html_e( 'Grid / Columns (No Slider)', 'custom-google-reviews' ); ?></strong>
                        </label>
                    </div>
                </div>

                <!-- SECTION 3 -->
                <div class="cgr-panel">
                    <h3 class="cgr-panel-title"><?php esc_html_e( '3. Behavior & Display Rules', 'custom-google-reviews' ); ?></h3>
                    <table class="form-table cgr-form-table">
                        <tr class="cgr-row cgr-all">
                            <th scope="row"><?php esc_html_e( 'Sort Reviews By', 'custom-google-reviews' ); ?></th>
                            <td>
                                <select name="cgr_sort_order" id="cgr_sort_order">
                                    <option value="highest" <?php selected( get_option('cgr_sort_order', 'highest'), 'highest' ); ?>><?php esc_html_e( 'Highest Rating First', 'custom-google-reviews' ); ?></option>
                                    <option value="newest" <?php selected( get_option('cgr_sort_order'), 'newest' ); ?>><?php esc_html_e( 'Newest Reviews First', 'custom-google-reviews' ); ?></option>
                                    <option value="random" <?php selected( get_option('cgr_sort_order'), 'random' ); ?>><?php esc_html_e( 'Random / Shuffle', 'custom-google-reviews' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr class="cgr-row cgr-all">
                            <th scope="row"><?php esc_html_e( 'Minimum Star Rating to Show', 'custom-google-reviews' ); ?></th>
                            <td>
                                <select name="cgr_min_rating" id="cgr_min_rating">
                                    <option value="5" <?php selected( get_option('cgr_min_rating'), 5 ); ?>><?php esc_html_e( '5 Stars Only', 'custom-google-reviews' ); ?></option>
                                    <option value="4" <?php selected( get_option('cgr_min_rating', 4), 4 ); ?>><?php esc_html_e( '4 Stars & Up', 'custom-google-reviews' ); ?></option>
                                    <option value="1" <?php selected( get_option('cgr_min_rating'), 1 ); ?>><?php esc_html_e( 'Show All Reviews', 'custom-google-reviews' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr class="cgr-row cgr-all">
                            <th scope="row"><?php esc_html_e( 'Maximum Number of Reviews', 'custom-google-reviews' ); ?></th>
                            <td><input type="number" name="cgr_review_limit" id="cgr_review_limit" value="<?php echo esc_attr(get_option('cgr_review_limit', 5)); ?>" min="1" max="50" class="cgr-input-small" /> reviews</td>
                        </tr>
                        <tr class="cgr-row cgr-all">
                            <th scope="row"><?php esc_html_e( '"Read More" Character Cutoff', 'custom-google-reviews' ); ?></th>
                            <td><input type="number" name="cgr_excerpt_length" id="cgr_excerpt_length" value="<?php echo esc_attr(get_option('cgr_excerpt_length', 160)); ?>" class="cgr-input-small" /> characters</td>
                        </tr>
                        <tr class="cgr-row cgr-all">
                            <th scope="row"><?php esc_html_e( 'Choose What to Display', 'custom-google-reviews' ); ?></th>
                            <td>
                                <fieldset class="cgr-checkbox-group">
                                    <label><input type="checkbox" name="cgr_show_avatars" id="cgr_show_avatars" value="1" <?php checked(1, get_option('cgr_show_avatars', 1)); ?> /> <?php esc_html_e( 'Show Reviewer Profile Photos', 'custom-google-reviews' ); ?></label>
                                    <label><input type="checkbox" name="cgr_show_date" id="cgr_show_date" value="1" <?php checked(1, get_option('cgr_show_date', 1)); ?> /> <?php esc_html_e( 'Show Review Date', 'custom-google-reviews' ); ?></label>
                                    <label><input type="checkbox" name="cgr_show_badge" id="cgr_show_badge" value="1" <?php checked(1, get_option('cgr_show_badge', 1)); ?> /> <?php esc_html_e( 'Show Verified Review Badge', 'custom-google-reviews' ); ?></label>
                                    <label><input type="checkbox" name="cgr_show_google_logo" id="cgr_show_google_logo" value="1" <?php checked(1, get_option('cgr_show_google_logo', 1)); ?> /> <?php esc_html_e( 'Show Google Review', 'custom-google-reviews' ); ?></label>
                                </fieldset>
                            </td>
                        </tr>
                        <tr class="cgr-row cgr-minimal_slider cgr-card_slider">
                            <th scope="row"><?php esc_html_e( 'Slider Navigation Buttons', 'custom-google-reviews' ); ?></th>
                            <td>
                                <select name="cgr_nav_type" id="cgr_nav_type">
                                    <option value="dots" <?php selected( get_option('cgr_nav_type', 'dots'), 'dots' ); ?>><?php esc_html_e( 'Dots Only', 'custom-google-reviews' ); ?></option>
                                    <option value="arrows" <?php selected( get_option('cgr_nav_type'), 'arrows' ); ?>><?php esc_html_e( 'Arrows Only', 'custom-google-reviews' ); ?></option>
                                    <option value="both" <?php selected( get_option('cgr_nav_type'), 'both' ); ?>><?php esc_html_e( 'Both Dots & Arrows', 'custom-google-reviews' ); ?></option>
                                    <option value="none" <?php selected( get_option('cgr_nav_type'), 'none' ); ?>><?php esc_html_e( 'None (Autoplay Only)', 'custom-google-reviews' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr class="cgr-row cgr-static_grid">
                            <th scope="row"><?php esc_html_e( 'Columns per row', 'custom-google-reviews' ); ?></th>
                            <td>
                                <select name="cgr_grid_columns" id="cgr_grid_columns">
                                    <option value="2" <?php selected( get_option('cgr_grid_columns'), '2' ); ?>><?php esc_html_e( '2 Columns', 'custom-google-reviews' ); ?></option>
                                    <option value="3" <?php selected( get_option('cgr_grid_columns', '3'), '3' ); ?>><?php esc_html_e( '3 Columns', 'custom-google-reviews' ); ?></option>
                                    <option value="4" <?php selected( get_option('cgr_grid_columns'), '4' ); ?>><?php esc_html_e( '4 Columns', 'custom-google-reviews' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr class="cgr-row cgr-minimal_slider cgr-card_slider">
                            <th scope="row"><?php esc_html_e( 'Slider Autoplay Speed', 'custom-google-reviews' ); ?></th>
                            <td><input type="number" name="cgr_autoplay_speed" id="cgr_autoplay_speed" value="<?php echo esc_attr(get_option('cgr_autoplay_speed', 6000)); ?>" class="cgr-input-small" /> ms</td>
                        </tr>
                    </table>
                </div>

                <!-- SECTION 4 -->
                <div class="cgr-panel">
                    <h3 class="cgr-panel-title"><?php esc_html_e( '4. Colors & Typography Settings', 'custom-google-reviews' ); ?></h3>
                    <table class="form-table cgr-form-table">
                        <tr class="cgr-row cgr-all">
                            <th scope="row"><?php esc_html_e( 'Section Background Color', 'custom-google-reviews' ); ?></th>
                            <td><input type="text" name="cgr_section_bg" id="cgr_section_bg" value="<?php echo esc_attr(get_option('cgr_section_bg', 'transparent')); ?>" /></td>
                        </tr>
                        <tr class="cgr-row cgr-card_slider cgr-static_grid">
                            <th scope="row"><?php esc_html_e( 'Card Background Color', 'custom-google-reviews' ); ?></th>
                            <td><input type="text" name="cgr_bg_color" id="cgr_bg_color" value="<?php echo esc_attr(get_option('cgr_bg_color', '#ffffff')); ?>" /></td>
                        </tr>
                        <tr class="cgr-row cgr-all">
                            <th scope="row"><?php esc_html_e( 'Star Rating', 'custom-google-reviews' ); ?></th>
                            <td>
                                <label>Color: <input type="color" name="cgr_star_color" id="cgr_star_color" value="<?php echo esc_attr(get_option('cgr_star_color', '#F2B01E')); ?>" /></label>
                                <label>Size: <input type="number" name="cgr_star_size" id="cgr_star_size" value="<?php echo esc_attr(get_option('cgr_star_size', 24)); ?>" class="cgr-input-small" /> px</label>
                            </td>
                        </tr>
                        <tr class="cgr-row cgr-all">
                            <th scope="row"><?php esc_html_e( 'Review Text', 'custom-google-reviews' ); ?></th>
                            <td>
                                <label>Color: <input type="color" name="cgr_quote_color" id="cgr_quote_color" value="<?php echo esc_attr(get_option('cgr_quote_color', '#333333')); ?>" /></label>
                                <label>Size: <input type="number" name="cgr_quote_size" id="cgr_quote_size" value="<?php echo esc_attr(get_option('cgr_quote_size', 16)); ?>" class="cgr-input-small" /> px</label>
                                <label>Weight: 
                                    <select name="cgr_quote_weight" id="cgr_quote_weight">
                                        <option value="300" <?php selected( get_option('cgr_quote_weight'), '300' ); ?>>Light (300)</option>
                                        <option value="400" <?php selected( get_option('cgr_quote_weight', '400'), '400' ); ?>>Normal (400)</option>
                                        <option value="500" <?php selected( get_option('cgr_quote_weight'), '500' ); ?>>Medium (500)</option>
                                        <option value="600" <?php selected( get_option('cgr_quote_weight'), '600' ); ?>>Semi-Bold (600)</option>
                                        <option value="700" <?php selected( get_option('cgr_quote_weight'), '700' ); ?>>Bold (700)</option>
                                    </select>
                                </label>
                            </td>
                        </tr>
                        <tr class="cgr-row cgr-all">
                            <th scope="row"><?php esc_html_e( 'Customer Name', 'custom-google-reviews' ); ?></th>
                            <td>
                                <label>Color: <input type="color" name="cgr_name_color" id="cgr_name_color" value="<?php echo esc_attr(get_option('cgr_name_color', '#333333')); ?>" /></label>
                                <label>Size: <input type="number" name="cgr_name_size" id="cgr_name_size" value="<?php echo esc_attr(get_option('cgr_name_size', 16)); ?>" class="cgr-input-small" /> px</label>
                                <label>Weight: 
                                    <select name="cgr_name_weight" id="cgr_name_weight">
                                        <option value="300" <?php selected( get_option('cgr_name_weight'), '300' ); ?>>Light (300)</option>
                                        <option value="400" <?php selected( get_option('cgr_name_weight'), '400' ); ?>>Normal (400)</option>
                                        <option value="500" <?php selected( get_option('cgr_name_weight'), '500' ); ?>>Medium (500)</option>
                                        <option value="600" <?php selected( get_option('cgr_name_weight'), '600' ); ?>>Semi-Bold (600)</option>
                                        <option value="700" <?php selected( get_option('cgr_name_weight', '700'), '700' ); ?>>Bold (700)</option>
                                    </select>
                                </label>
                            </td>
                        </tr>
                        <tr class="cgr-row cgr-all">
                            <th scope="row"><?php esc_html_e( 'Verified Badge', 'custom-google-reviews' ); ?></th>
                            <td>
                                <label>Color: <input type="color" name="cgr_badge_color" id="cgr_badge_color" value="<?php echo esc_attr(get_option('cgr_badge_color', '#91C13D')); ?>" /></label>
                                <label>Size: <input type="number" name="cgr_badge_size" id="cgr_badge_size" value="<?php echo esc_attr(get_option('cgr_badge_size', 11)); ?>" class="cgr-input-small" /> px</label>
                                <label>Weight: 
                                    <select name="cgr_badge_weight" id="cgr_badge_weight">
                                        <option value="300" <?php selected( get_option('cgr_badge_weight'), '300' ); ?>>Light (300)</option>
                                        <option value="400" <?php selected( get_option('cgr_badge_weight'), '400' ); ?>>Normal (400)</option>
                                        <option value="500" <?php selected( get_option('cgr_badge_weight'), '500' ); ?>>Medium (500)</option>
                                        <option value="600" <?php selected( get_option('cgr_badge_weight', '600'), '600' ); ?>>Semi-Bold (600)</option>
                                        <option value="700" <?php selected( get_option('cgr_badge_weight'), '700' ); ?>>Bold (700)</option>
                                    </select>
                                </label>
                            </td>
                        </tr>
                        <tr class="cgr-row cgr-all">
                            <th scope="row"><?php esc_html_e( 'Google Review Text', 'custom-google-reviews' ); ?></th>
                            <td>
                                <label>Color: <input type="color" name="cgr_watermark_color" id="cgr_watermark_color" value="<?php echo esc_attr(get_option('cgr_watermark_color', '#333333')); ?>" /></label>
                                <label>Size: <input type="number" name="cgr_watermark_size" id="cgr_watermark_size" value="<?php echo esc_attr(get_option('cgr_watermark_size', 12)); ?>" class="cgr-input-small" /> px</label>
                                <label>Weight: 
                                    <select name="cgr_watermark_weight" id="cgr_watermark_weight">
                                        <option value="300" <?php selected( get_option('cgr_watermark_weight'), '300' ); ?>>Light (300)</option>
                                        <option value="400" <?php selected( get_option('cgr_watermark_weight', '400'), '400' ); ?>>Normal (400)</option>
                                        <option value="500" <?php selected( get_option('cgr_watermark_weight'), '500' ); ?>>Medium (500)</option>
                                        <option value="600" <?php selected( get_option('cgr_watermark_weight'), '600' ); ?>>Semi-Bold (600)</option>
                                        <option value="700" <?php selected( get_option('cgr_watermark_weight'), '700' ); ?>>Bold (700)</option>
                                    </select>
                                </label>
                            </td>
                        </tr>
                        <tr class="cgr-row cgr-minimal_slider cgr-card_slider">
                            <th scope="row"><?php esc_html_e( 'Slider Arrows & Dots Color', 'custom-google-reviews' ); ?></th>
                            <td><input type="color" name="cgr_dot_color" id="cgr_dot_color" value="<?php echo esc_attr(get_option('cgr_dot_color', '#91C13D')); ?>" /></td>
                        </tr>
                        <tr class="cgr-row cgr-all">
                            <th scope="row"><?php esc_html_e( '"Read More" Link Color', 'custom-google-reviews' ); ?></th>
                            <td><input type="color" name="cgr_readmore_color" id="cgr_readmore_color" value="<?php echo esc_attr(get_option('cgr_readmore_color', '#91C13D')); ?>" /></td>
                        </tr>
                        <tr class="cgr-row cgr-card_slider cgr-static_grid">
                            <th scope="row"><?php esc_html_e( 'Card Padding (px)', 'custom-google-reviews' ); ?></th>
                            <td><input type="number" name="cgr_card_padding" id="cgr_card_padding" value="<?php echo esc_attr(get_option('cgr_card_padding', 40)); ?>" class="cgr-input-small" /> px</td>
                        </tr>
                        <tr class="cgr-row cgr-card_slider cgr-static_grid">
                            <th scope="row"><?php esc_html_e( 'Card Corner Radius (px)', 'custom-google-reviews' ); ?></th>
                            <td><input type="number" name="cgr_card_radius" id="cgr_card_radius" value="<?php echo esc_attr(get_option('cgr_card_radius', 12)); ?>" class="cgr-input-small" /> px</td>
                        </tr>
                    </table>
                    
                    <div class="cgr-submit-wrap">
                        <?php submit_button(__( 'Save Configurations Matrix', 'custom-google-reviews' ), 'primary', 'submit', false); ?>
                    </div>
                </div>
            </form>
            
            <!-- Real-Time Live Preview Engine -->
            <div class="cgr-panel cgr-preview-panel">
                <div class="cgr-preview-header">
                    <h3><span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Real-Time Live Engine Preview', 'custom-google-reviews' ); ?></h3>
                    <span>(Showing exactly how your filtered frontend will look. Save settings to refresh data.)</span>
                </div>
                
                <div class="cgr-slider-wrapper">
                    <div id="cgr-preview-canvas" class="cgr-pro-container">
                        <div id="cgr-preview-layout-wrapper">
                            <?php 
                            if (empty($preview_data)) {
                                echo '<p class="cgr-preview-empty">No reviews match your current filter settings.</p>';
                            } else {
                                $index = 1;
                                foreach($preview_data as $rev) : ?>
                                    <div class="cgr-preview-node-item" id="preview-item-<?php echo $index; ?>">
                                        <div class="cgr-card-node" id="preview-card-<?php echo $index; ?>">
                                            <div class="cgr-author-profile">
                                                <?php if(!empty($rev['profile_photo_url'])) : ?>
                                                    <img src="<?php echo esc_url($rev['profile_photo_url']); ?>" class="cgr-preview-avatar" alt="">
                                                <?php else: ?>
                                                    <div class="cgr-preview-avatar cgr-avatar-fallback"><?php echo esc_html(substr($rev['author_name'],0,2)); ?></div>
                                                <?php endif; ?>
                                                
                                                <div class="cgr-author-title">
                                                    <span class="cgr-preview-author-name"><?php echo esc_html($rev['author_name']); ?></span>
                                                    <div class="cgr-preview-date"><?php echo esc_html(human_time_diff($rev['review_time'], current_time('timestamp'))) . ' ago'; ?></div>
                                                </div>
                                            </div>
                                            
                                            <div class="cgr-stars"><?php for($i=0; $i<5; $i++){ echo ($i < (int)$rev['rating']) ? '★' : '☆'; } ?></div>
                                            
                                            <p class="cgr-quote">"<?php echo esc_html(wp_trim_words($rev['review_text'], 25)); ?>"</p>
                                            <a class="cgr-read-more-trigger"><?php esc_html_e( 'Read More', 'custom-google-reviews' ); ?></a>
                                            
                                            <div class="cgr-preview-google-logo">Google Review</div>
                                            <div class="cgr-verified-badge">✓ <?php esc_html_e( 'VERIFIED REVIEW', 'custom-google-reviews' ); ?></div>
                                        </div>
                                    </div>
                                <?php $index++; endforeach; 
                            } ?>
                        </div>
                        <div id="preview-dots-container" class="cgr-preview-dots">
                            <span class="preview-dot"></span><span class="preview-dot"></span><span class="preview-dot"></span>
                        </div>
                    </div>
                    <div id="preview-arrow-left" class="cgr-preview-arrow cgr-preview-arrow-left">&lt;</div>
                    <div id="preview-arrow-right" class="cgr-preview-arrow cgr-preview-arrow-right">&gt;</div>
                </div>
            </div>
        </div>
        <?php
    }
}