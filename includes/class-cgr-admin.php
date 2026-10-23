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
        add_action( 'admin_init', array( $this, 'handle_manual_review' ) );
        add_action( 'admin_init', array( $this, 'handle_delete_review' ) );
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
            'cgr_api_provider'      => 'google_new', 
            'cgr_api_key'           => '', 
            'cgr_place_id'          => '', 
            'cgr_outscraper_key'    => '',
            'cgr_apify_token'       => '',
            'cgr_maps_url'          => '',
            
            'cgr_sync_freq'         => 86400, 
            'cgr_layout'            => 'minimal_slider',
            'cgr_nav_type'          => 'dots', 
            'cgr_grid_columns'      => '3', 
            'cgr_min_rating'        => 4, 
            'cgr_review_limit'      => 5,
            'cgr_min_char_length'   => 0,
            'cgr_autoplay_speed'    => 6000, 
            'cgr_sort_order'        => 'highest', 
            'cgr_excerpt_length'    => 160,
            
            'cgr_readmore_position' => 'below',
            'cgr_readmore_action'   => 'modal',
            'cgr_modal_bg'          => '#112837',
            'cgr_modal_text_color'  => '#ffffff',
            'cgr_modal_radius'      => '12',

            'cgr_show_avatars'      => 1, 
            'cgr_show_date'         => 1, 
            'cgr_show_badge'        => 1, 
            'cgr_show_google_logo'  => 1,
            'cgr_hide_blank_reviews'=> 1,
            
            'cgr_section_bg'        => 'transparent', 
            'cgr_bg_color'          => '#ffffff', 
            
            'cgr_quote_color'       => '#333333',
            'cgr_quote_size'        => '18', 
            'cgr_quote_weight'      => '300',
            
            'cgr_name_color'        => '#333333', 
            'cgr_name_size'         => '16', 
            'cgr_name_weight'       => '700',
            
            'cgr_star_color'        => '#F2B01E', 
            'cgr_star_size'         => '24', 
            
            'cgr_badge_color'       => '#91C13D', 
            'cgr_badge_size'        => '11', 
            'cgr_badge_weight'      => '600',
            
            'cgr_watermark_color'   => '#333333', 
            'cgr_watermark_size'    => '18', 
            'cgr_watermark_weight'  => '300',

            'cgr_dot_color'         => '#91C13D',
            'cgr_arrow_size'        => 20,
            'cgr_dot_size'          => 8,
            'cgr_readmore_color'    => '#91C13D', 
            'cgr_readmore_size'     => '14',
            'cgr_readmore_weight'   => '600',
            
            'cgr_card_padding'      => '40', 
            'cgr_card_radius'       => '12'
        );
        foreach ($settings as $key => $default) {
            register_setting( 'cgr_settings', $key, array('default' => $default) );
        }
    }

    public function handle_manual_sync() {
        if ( isset( $_POST['cgr_manual_sync'] ) && check_admin_referer( 'cgr_manual_sync_nonce', 'cgr_sync_nonce' ) ) {
            if ( current_user_can( 'manage_options' ) ) {
                if ( self::fetch_and_store_reviews() ) {
                    add_settings_error( 'cgr_messages', 'cgr_synced', __( 'Reviews successfully synced!', 'custom-google-reviews' ), 'updated' );
                }
            }
        }
    }

    public function handle_manual_review() {
        if ( isset( $_POST['cgr_add_manual_review'] ) && check_admin_referer( 'cgr_manual_review_nonce', 'cgr_review_nonce' ) ) {
            if ( current_user_can( 'manage_options' ) ) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'cgr_reviews';
                
                $author = !empty($_POST['manual_author']) ? sanitize_text_field($_POST['manual_author']) : 'Anonymous';
                $rating = !empty($_POST['manual_rating']) ? (int)$_POST['manual_rating'] : 5;
                $text   = !empty($_POST['manual_text']) ? sanitize_textarea_field($_POST['manual_text']) : '';
                $photo  = !empty($_POST['manual_photo']) ? esc_url_raw($_POST['manual_photo']) : '';
                $time   = current_time('timestamp');

                $wpdb->insert( $table_name, array(
                    'author_name'       => $author,
                    'profile_photo_url' => $photo,
                    'rating'            => $rating,
                    'review_text'       => $text,
                    'review_time'       => $time
                ), array( '%s', '%s', '%d', '%s', '%d' ) );

                add_settings_error( 'cgr_messages', 'cgr_manual_success', __( 'Manual review added successfully!', 'custom-google-reviews' ), 'updated' );
            }
        }
    }

    public function handle_delete_review() {
        if ( isset( $_POST['cgr_delete_id'] ) && check_admin_referer( 'cgr_delete_review_nonce', 'cgr_del_nonce' ) ) {
            if ( current_user_can( 'manage_options' ) ) {
                global $wpdb;
                $wpdb->delete( $wpdb->prefix . 'cgr_reviews', array( 'id' => intval( $_POST['cgr_delete_id'] ) ) );
                add_settings_error( 'cgr_messages', 'cgr_deleted', __( 'Review deleted successfully.', 'custom-google-reviews' ), 'updated' );
            }
        }
    }

    public static function fetch_and_store_reviews() {
        global $wpdb;
        $provider   = get_option( 'cgr_api_provider', 'google_new' );
        $min_rating = (int) get_option( 'cgr_min_rating', 4 ); 
        $table_name = $wpdb->prefix . 'cgr_reviews';

        update_option( 'cgr_last_sync_time', current_time( 'timestamp' ) );
        $new_reviews_data = array();

        // 1. GOOGLE PLACES API (LEGACY)
        if ( $provider === 'google_legacy' ) {
            $api_key  = get_option( 'cgr_api_key' );
            $place_id = get_option( 'cgr_place_id' );
            if ( empty( $api_key ) || empty( $place_id ) ) { return false; }
            $url = sprintf( 'https://maps.googleapis.com/maps/api/place/details/json?place_id=%s&fields=reviews&reviews_sort=newest&key=%s', urlencode( $place_id ), urlencode( $api_key ) );
            $response = wp_remote_get( $url, array( 'timeout' => 10 ) );
            if ( is_wp_error( $response ) ) return false;
            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $data['result']['reviews'] ) && is_array( $data['result']['reviews'] ) ) {
                foreach ( $data['result']['reviews'] as $item ) {
                    $new_reviews_data[] = array(
                        'author_name'       => isset( $item['author_name'] ) ? sanitize_text_field( $item['author_name'] ) : 'Anonymous',
                        'profile_photo_url' => isset( $item['profile_photo_url'] ) ? esc_url_raw( $item['profile_photo_url'] ) : '',
                        'rating'            => isset( $item['rating'] ) ? (int) $item['rating'] : 5,
                        'review_text'       => isset( $item['text'] ) ? sanitize_textarea_field( $item['text'] ) : '',
                        'review_time'       => isset( $item['time'] ) ? (int) $item['time'] : current_time('timestamp'),
                    );
                }
            }
        } 
        // 2. GOOGLE PLACES API (NEW)
        elseif ( $provider === 'google_new' ) {
            $api_key  = get_option( 'cgr_api_key' );
            $place_id = get_option( 'cgr_place_id' );
            if ( empty( $api_key ) || empty( $place_id ) ) return false;
            $url = sprintf( 'https://places.googleapis.com/v1/places/%s', urlencode( $place_id ) );
            $response = wp_remote_get( $url, array( 'timeout' => 10, 'headers' => array( 'X-Goog-Api-Key' => $api_key, 'X-Goog-FieldMask' => 'reviews' ) ) );
            if ( is_wp_error( $response ) ) return false;
            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $data['reviews'] ) && is_array( $data['reviews'] ) ) {
                foreach ( $data['reviews'] as $item ) {
                    $new_reviews_data[] = array(
                        'author_name'       => isset( $item['authorAttribution']['displayName'] ) ? sanitize_text_field( $item['authorAttribution']['displayName'] ) : 'Anonymous',
                        'profile_photo_url' => isset( $item['authorAttribution']['photoUri'] ) ? esc_url_raw( $item['authorAttribution']['photoUri'] ) : '',
                        'rating'            => isset( $item['rating'] ) ? (int) $item['rating'] : 5,
                        'review_text'       => isset( $item['text']['text'] ) ? sanitize_textarea_field( $item['text']['text'] ) : '',
                        'review_time'       => isset( $item['publishTime'] ) ? strtotime( $item['publishTime'] ) : current_time('timestamp'),
                    );
                }
            }
        } 
        // 3. OUTSCRAPER API
        elseif ( $provider === 'outscraper' ) {
            $api_key  = get_option( 'cgr_outscraper_key' );
            $maps_url = get_option( 'cgr_maps_url' );
            if ( empty( $api_key ) || empty( $maps_url ) ) return false;
            $url = 'https://api.app.outscraper.com/google-maps-reviews?query=' . urlencode($maps_url) . '&reviewsLimit=10&sort=newest';
            $response = wp_remote_get( $url, array( 'headers' => array('X-API-KEY' => $api_key), 'timeout' => 45 ) );
            if ( is_wp_error( $response ) ) return false;
            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $data['data'][0]['reviews_data'] ) && is_array( $data['data'][0]['reviews_data'] ) ) {
                foreach ( $data['data'][0]['reviews_data'] as $item ) {
                    $new_reviews_data[] = array(
                        'author_name'       => isset( $item['author_title'] ) ? sanitize_text_field( $item['author_title'] ) : 'Anonymous',
                        'profile_photo_url' => isset( $item['author_image'] ) ? esc_url_raw( $item['author_image'] ) : '',
                        'rating'            => isset( $item['review_rating'] ) ? (int) $item['review_rating'] : 5,
                        'review_text'       => isset( $item['review_text'] ) ? sanitize_textarea_field( $item['review_text'] ) : '',
                        'review_time'       => isset( $item['review_timestamp'] ) ? (int) $item['review_timestamp'] : current_time('timestamp'),
                    );
                }
            }
        } 
        // 4. APIFY SCRAPER API
        elseif ( $provider === 'apify' ) {
            $token    = get_option( 'cgr_apify_token' );
            $maps_url = get_option( 'cgr_maps_url' );
            if ( empty( $token ) || empty( $maps_url ) ) return false;
            $url = 'https://api.apify.com/v2/acts/compass~crawler-google-places/run-sync-get-dataset-items?token=' . urlencode($token);
            $payload = json_encode( array( 'searchStringsArray' => array( $maps_url ), 'maxReviews' => 10 ) );
            $response = wp_remote_post( $url, array( 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => $payload, 'timeout' => 60 ) );
            if ( is_wp_error( $response ) ) return false;
            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( is_array( $data ) && !empty( $data ) && isset( $data[0]['reviews'] ) ) {
                foreach ( $data[0]['reviews'] as $item ) {
                    $new_reviews_data[] = array(
                        'author_name'       => isset( $item['name'] ) ? sanitize_text_field( $item['name'] ) : 'Anonymous',
                        'profile_photo_url' => isset( $item['avatarUrl'] ) ? esc_url_raw( $item['avatarUrl'] ) : '',
                        'rating'            => isset( $item['stars'] ) ? (int) $item['stars'] : 5,
                        'review_text'       => isset( $item['text'] ) ? sanitize_textarea_field( $item['text'] ) : '',
                        'review_time'       => isset( $item['publishedAtDate'] ) ? strtotime( $item['publishedAtDate'] ) : current_time('timestamp'),
                    );
                }
            }
        }

        if ( !empty( $new_reviews_data ) ) {
            foreach ( $new_reviews_data as $rev ) {
                if ( $rev['rating'] < $min_rating ) continue;
                $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE author_name = %s AND review_time = %d", $rev['author_name'], $rev['review_time'] ) );
                if ( ! $exists ) { $wpdb->insert( $table_name, $rev, array( '%s', '%s', '%d', '%s', '%d' ) ); }
            }
            return true;
        }
        return false;
    }

    public static function get_reviews() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cgr_reviews';
        $results = $wpdb->get_results( "SELECT author_name, profile_photo_url, rating, review_text as text, review_time as time FROM $table_name", ARRAY_A );
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
        $excerpt_length = (int) get_option('cgr_excerpt_length', 160);
        $min_chars      = (int) get_option('cgr_min_char_length', 0);
        $hide_blank     = filter_var(get_option('cgr_hide_blank_reviews', 1), FILTER_VALIDATE_BOOLEAN);

        $display_stored_reviews = $stored_reviews; // Backend table shows all

        $filtered_reviews = array();
        if ( ! empty($stored_reviews) ) {
            foreach ( $stored_reviews as $rev ) { 
                $len = function_exists('mb_strlen') ? mb_strlen($rev['review_text']) : strlen($rev['review_text']);
                if ( $hide_blank && empty( trim( $rev['review_text'] ) ) ) continue;
                if ( $len < $min_chars ) continue;
                if ( (int) $rev['rating'] >= $min_rating ) { $filtered_reviews[] = $rev; } 
            }
            if ( $sort_order === 'highest' ) { usort( $filtered_reviews, function($a, $b) { return ( (int)$b['rating'] <=> (int)$a['rating'] ); }); } 
            elseif ( $sort_order === 'newest' ) { usort( $filtered_reviews, function($a, $b) { return ( (int)$b['review_time'] <=> (int)$a['review_time'] ); }); } 
            elseif ( $sort_order === 'random' ) { shuffle( $filtered_reviews ); }
        }
        $preview_data = array_slice( $filtered_reviews, 0, $review_limit );

        ?>
        <div class="wrap cgr-admin-wrap">
            
            <div class="cgr-dashboard-header">
                <h1><?php esc_html_e( 'Google Reviews Pro', 'custom-google-reviews' ); ?></h1>
                <a href="https://paypal.me/netgreater" target="_blank" class="cgr-btn-donate">
                    <span class="dashicons dashicons-heart"></span> <?php esc_html_e( 'Support Development', 'custom-google-reviews' ); ?>
                </a>
            </div>

            <!-- Sync Panel -->
            <div class="cgr-panel cgr-panel-sync">
                <div class="cgr-panel-content">
                    <h3><span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Database Synchronization', 'custom-google-reviews' ); ?></h3>
                    <p>
                        <?php 
                        $last_sync = get_option( 'cgr_last_sync_time' );
                        if ( $last_sync ) {
                            printf( esc_html__( 'Last synced: %s ago. Total reviews in database: %d', 'custom-google-reviews' ), human_time_diff( $last_sync, current_time('timestamp') ), count($stored_reviews) );
                        } else {
                            esc_html_e( 'Never synced yet. Click the button to fetch reviews.', 'custom-google-reviews' );
                        }
                        ?>
                    </p>
                </div>
                <div class="cgr-panel-action">
                    <form method="post" style="margin: 0;">
                        <?php wp_nonce_field( 'cgr_manual_sync_nonce', 'cgr_sync_nonce' ); ?>
                        <button type="submit" name="cgr_manual_sync" class="cgr-btn cgr-btn-primary"><?php esc_attr_e( 'Sync Reviews Now', 'custom-google-reviews' ); ?></button>
                    </form>
                </div>
            </div>

            <form method="post" action="options.php" class="cgr-settings-form">
                <?php settings_fields( 'cgr_settings' ); ?>

                <!-- SECTION 1 -->
                <div class="cgr-panel">
                    <h3 class="cgr-panel-title">1. Data Source Connection</h3>
                    <div class="cgr-form-group">
                        <label class="cgr-label">Select API Provider</label>
                        <select name="cgr_api_provider" id="cgr_api_provider" class="cgr-input">
                            <option value="google_new" <?php selected( get_option('cgr_api_provider', 'google_new'), 'google_new' ); ?>>Google Places API (New)</option>
                            <option value="google_legacy" <?php selected( get_option('cgr_api_provider'), 'google_legacy' ); ?>>Google Places API (Legacy)</option>
                            <option value="outscraper" <?php selected( get_option('cgr_api_provider'), 'outscraper' ); ?>>Outscraper API</option>
                            <option value="apify" <?php selected( get_option('cgr_api_provider'), 'apify' ); ?>>Apify Scraper API</option>
                        </select>
                    </div>

                    <!-- GOOGLE FIELDS -->
                    <div class="cgr-form-group provider-field provider-google_new provider-google_legacy" style="display:none;">
                        <label class="cgr-label">Google API Key</label>
                        <div style="width: 100%; max-width: 400px;">
                            <div style="position: relative; display: block; width: 100%;">
                                <input type="password" name="cgr_api_key" id="cgr_api_key" value="<?php echo esc_attr( get_option('cgr_api_key') ); ?>" class="cgr-input" style="width: 100%; padding-right: 35px;" />
                                <span class="dashicons dashicons-visibility cgr-toggle-password" data-target="cgr_api_key"></span>
                            </div>
                            <p style="margin: 5px 0 0 0; font-size: 12px; color: #646970;">Requires Places API enabled. <a href="https://console.cloud.google.com/" target="_blank">Get your Google API key</a>.</p>
                        </div>
                    </div>
                    <div class="cgr-form-group provider-field provider-google_new provider-google_legacy" style="display:none;">
                        <label class="cgr-label">Google Place ID</label>
                        <div style="width: 100%; max-width: 400px;">
                            <input type="text" name="cgr_place_id" id="cgr_place_id" value="<?php echo esc_attr( get_option('cgr_place_id') ); ?>" class="cgr-input" style="width: 100%;" />
                            <p style="margin: 5px 0 0 0; font-size: 12px; color: #646970;"><a href="https://developers.google.com/maps/documentation/places/web-service/place-id" target="_blank">Find your Google Place ID</a>.</p>
                        </div>
                    </div>

                    <!-- OUTSCRAPER FIELDS -->
                    <div class="cgr-form-group provider-field provider-outscraper" style="display:none;">
                        <label class="cgr-label">Outscraper API Key</label>
                        <div style="width: 100%; max-width: 400px;">
                            <div style="position: relative; display: block; width: 100%;">
                                <input type="password" name="cgr_outscraper_key" id="cgr_outscraper_key" value="<?php echo esc_attr( get_option('cgr_outscraper_key') ); ?>" class="cgr-input" style="width: 100%; padding-right: 35px;" />
                                <span class="dashicons dashicons-visibility cgr-toggle-password" data-target="cgr_outscraper_key"></span>
                            </div>
                            <p style="margin: 5px 0 0 0; font-size: 12px; color: #646970;"><a href="https://app.outscraper.com/profile" target="_blank">Get your Outscraper API Key here</a>.</p>
                        </div>
                    </div>

                    <!-- APIFY FIELDS -->
                    <div class="cgr-form-group provider-field provider-apify" style="display:none;">
                        <label class="cgr-label">Apify API Token</label>
                        <div style="width: 100%; max-width: 400px;">
                            <div style="position: relative; display: block; width: 100%;">
                                <input type="password" name="cgr_apify_token" id="cgr_apify_token" value="<?php echo esc_attr( get_option('cgr_apify_token') ); ?>" class="cgr-input" style="width: 100%; padding-right: 35px;" />
                                <span class="dashicons dashicons-visibility cgr-toggle-password" data-target="cgr_apify_token"></span>
                            </div>
                            <p style="margin: 5px 0 0 0; font-size: 12px; color: #646970;">Found in Settings → API & Integrations. <a href="https://console.apify.com/account/integrations" target="_blank">Get your Token here</a>.</p>
                        </div>
                    </div>

                    <!-- SHARED SCRAPER FIELD -->
                    <div class="cgr-form-group provider-field provider-outscraper provider-apify" style="display:none;">
                        <label class="cgr-label">Google Maps Profile URL</label>
                        <div style="width: 100%; max-width: 400px;">
                            <input type="url" name="cgr_maps_url" id="cgr_maps_url" value="<?php echo esc_attr( get_option('cgr_maps_url') ); ?>" class="cgr-input" style="width: 100%;" />
                            <p style="margin: 5px 0 0 0; font-size: 12px; color: #646970;">Paste the full URL to the business location on Google Maps.</p>
                        </div>
                    </div>

                    <div class="cgr-form-group">
                        <label class="cgr-label">Sync Frequency</label>
                        <select name="cgr_sync_freq" id="cgr_sync_freq" class="cgr-input">
                            <option value="43200" <?php selected( get_option('cgr_sync_freq'), 43200 ); ?>>12 Hours</option>
                            <option value="86400" <?php selected( get_option('cgr_sync_freq', 86400), 86400 ); ?>>24 Hours</option>
                            <option value="604800" <?php selected( get_option('cgr_sync_freq'), 604800 ); ?>>7 Days</option>
                            <option value="1296000" <?php selected( get_option('cgr_sync_freq'), 1296000 ); ?>>15 Days</option>
                            <option value="2592000" <?php selected( get_option('cgr_sync_freq'), 2592000 ); ?>>30 Days (1 Month)</option>
                        </select>
                    </div>
                </div>

                <!-- SECTION 2 -->
                <div class="cgr-panel">
                    <h3 class="cgr-panel-title">2. Choose Your Layout</h3>
                    <div class="cgr-layout-grid">
                        <label class="cgr-layout-card <?php echo $layout === 'minimal_slider' ? 'active' : ''; ?>">
                            <input type="radio" name="cgr_layout" value="minimal_slider" <?php checked($layout, 'minimal_slider'); ?> />
                            <span class="dashicons dashicons-slides"></span>
                            <strong>Minimal Text Carousel</strong>
                        </label>
                        <label class="cgr-layout-card <?php echo $layout === 'card_slider' ? 'active' : ''; ?>">
                            <input type="radio" name="cgr_layout" value="card_slider" <?php checked($layout, 'card_slider'); ?> />
                            <span class="dashicons dashicons-welcome-widgets-menus"></span>
                            <strong>Boxed Card Carousel</strong>
                        </label>
                        <label class="cgr-layout-card <?php echo $layout === 'static_grid' ? 'active' : ''; ?>">
                            <input type="radio" name="cgr_layout" value="static_grid" <?php checked($layout, 'static_grid'); ?> />
                            <span class="dashicons dashicons-dashboard"></span>
                            <strong>Grid / Columns</strong>
                        </label>
                    </div>
                </div>

                <!-- SECTION 3 -->
                <div class="cgr-panel">
                    <h3 class="cgr-panel-title">3. Behavior & Display Rules</h3>
                    
                    <div class="cgr-form-group cgr-row cgr-all">
                        <label class="cgr-label">Sort Reviews By</label>
                        <select name="cgr_sort_order" id="cgr_sort_order" class="cgr-input">
                            <option value="highest" <?php selected( get_option('cgr_sort_order', 'highest'), 'highest' ); ?>>Highest Rating First</option>
                            <option value="newest" <?php selected( get_option('cgr_sort_order'), 'newest' ); ?>>Newest First</option>
                        </select>
                    </div>
                    <div class="cgr-form-group cgr-row cgr-all">
                        <label class="cgr-label">Minimum Star Rating</label>
                        <select name="cgr_min_rating" id="cgr_min_rating" class="cgr-input">
                            <option value="5" <?php selected( get_option('cgr_min_rating'), 5 ); ?>>5 Stars Only</option>
                            <option value="4" <?php selected( get_option('cgr_min_rating', 4), 4 ); ?>>4 Stars & Up</option>
                            <option value="1" <?php selected( get_option('cgr_min_rating'), 1 ); ?>>Show All</option>
                        </select>
                    </div>
                    <div class="cgr-form-group cgr-row cgr-all">
                        <label class="cgr-label">Max Reviews</label>
                        <input type="number" name="cgr_review_limit" id="cgr_review_limit" value="<?php echo esc_attr(get_option('cgr_review_limit', 5)); ?>" class="cgr-input cgr-input-small" />
                    </div>
                    <div class="cgr-form-group cgr-row cgr-all">
                        <label class="cgr-label">Min Character Length</label>
                        <input type="number" name="cgr_min_char_length" id="cgr_min_char_length" value="<?php echo esc_attr(get_option('cgr_min_char_length', 0)); ?>" class="cgr-input cgr-input-small" />
                    </div>
                    <div class="cgr-form-group cgr-row cgr-all">
                        <label class="cgr-label">Read More Cutoff Limit</label>
                        <input type="number" name="cgr_excerpt_length" id="cgr_excerpt_length" value="<?php echo esc_attr(get_option('cgr_excerpt_length', 160)); ?>" class="cgr-input cgr-input-small" />
                    </div>

                    <!-- Read More Ordering -->
                    <div class="cgr-form-group cgr-row cgr-all">
                        <label class="cgr-label">"Read More" Button Position</label>
                        <select name="cgr_readmore_position" id="cgr_readmore_position" class="cgr-input">
                            <option value="below" <?php selected( get_option('cgr_readmore_position', 'below'), 'below' ); ?>>Below Review (Standard)</option>
                            <option value="inline" <?php selected( get_option('cgr_readmore_position'), 'inline' ); ?>>Inline - Inside Quotes</option>
                        </select>
                    </div>
                    <div class="cgr-form-group cgr-row cgr-all">
                        <label class="cgr-label">"Read More" Click Behavior</label>
                        <select name="cgr_readmore_action" id="cgr_readmore_action" class="cgr-input">
                            <option value="modal" <?php selected( get_option('cgr_readmore_action', 'modal'), 'modal' ); ?>>Open Popup Modal (Best UX - No Stretching)</option>
                            <option value="scroll" <?php selected( get_option('cgr_readmore_action'), 'scroll' ); ?>>Scrollable Box inside Card</option>
                            <option value="inline" <?php selected( get_option('cgr_readmore_action'), 'inline' ); ?>>Expand Inline (Stretches Section)</option>
                        </select>
                    </div>
                    
                    <div class="cgr-form-group cgr-row cgr-all" id="cgr-modal-style-row">
                        <label class="cgr-label">Popup Modal Styling</label>
                        <div class="cgr-inline-controls">
                            <label>Background: <input type="color" name="cgr_modal_bg" id="cgr_modal_bg" value="<?php echo esc_attr(get_option('cgr_modal_bg', '#112837')); ?>" class="cgr-color-picker" /></label>
                            <label>Text: <input type="color" name="cgr_modal_text_color" id="cgr_modal_text_color" value="<?php echo esc_attr(get_option('cgr_modal_text_color', '#ffffff')); ?>" class="cgr-color-picker" /></label>
                            <label>Radius: <input type="number" name="cgr_modal_radius" id="cgr_modal_radius" value="<?php echo esc_attr(get_option('cgr_modal_radius', 12)); ?>" class="cgr-input cgr-input-small" /> px</label>
                        </div>
                    </div>

                    <div class="cgr-form-group cgr-row cgr-all">
                        <label class="cgr-label">Display Elements</label>
                        <fieldset class="cgr-checkbox-group">
                            <input type="hidden" name="cgr_hide_blank_reviews" value="0" />
                            <label><input type="checkbox" name="cgr_hide_blank_reviews" id="cgr_hide_blank_reviews" value="1" <?php checked(1, get_option('cgr_hide_blank_reviews', 1)); ?> /> Hide rating-only reviews (reviews that contain no written text).</label>
                            
                            <input type="hidden" name="cgr_show_avatars" value="0" />
                            <label><input type="checkbox" name="cgr_show_avatars" id="cgr_show_avatars" value="1" <?php checked(1, get_option('cgr_show_avatars', 1)); ?> /> Show Reviewer Profile Photos</label>
                            
                            <input type="hidden" name="cgr_show_date" value="0" />
                            <label><input type="checkbox" name="cgr_show_date" id="cgr_show_date" value="1" <?php checked(1, get_option('cgr_show_date', 1)); ?> /> Show Review Date</label>
                            
                            <input type="hidden" name="cgr_show_badge" value="0" />
                            <label><input type="checkbox" name="cgr_show_badge" id="cgr_show_badge" value="1" <?php checked(1, get_option('cgr_show_badge', 1)); ?> /> Show Verified Review Badge</label>
                            
                            <input type="hidden" name="cgr_show_google_logo" value="0" />
                            <label><input type="checkbox" name="cgr_show_google_logo" id="cgr_show_google_logo" value="1" <?php checked(1, get_option('cgr_show_google_logo', 1)); ?> /> Show "Google Review" Text</label>
                        </fieldset>
                    </div>

                    <div class="cgr-form-group cgr-row cgr-minimal_slider cgr-card_slider">
                        <label class="cgr-label">Slider Navigation</label>
                        <select name="cgr_nav_type" id="cgr_nav_type" class="cgr-input">
                            <option value="dots" <?php selected( get_option('cgr_nav_type', 'dots'), 'dots' ); ?>>Dots Only</option>
                            <option value="arrows" <?php selected( get_option('cgr_nav_type'), 'arrows' ); ?>>Arrows Only</option>
                            <option value="both" <?php selected( get_option('cgr_nav_type'), 'both' ); ?>>Both Dots & Arrows</option>
                        </select>
                    </div>

                    <div class="cgr-form-group cgr-row cgr-static_grid">
                        <label class="cgr-label">Grid Columns</label>
                        <select name="cgr_grid_columns" id="cgr_grid_columns" class="cgr-input">
                            <option value="2" <?php selected( get_option('cgr_grid_columns'), '2' ); ?>>2 Columns</option>
                            <option value="3" <?php selected( get_option('cgr_grid_columns', '3'), '3' ); ?>>3 Columns</option>
                            <option value="4" <?php selected( get_option('cgr_grid_columns'), '4' ); ?>>4 Columns</option>
                        </select>
                    </div>
                </div>

                <!-- SECTION 4 -->
                <div class="cgr-panel">
                    <h3 class="cgr-panel-title">4. Colors & Typography</h3>
                    
                    <div class="cgr-form-group cgr-row cgr-all">
                        <label class="cgr-label">Backgrounds</label>
                        <div class="cgr-inline-controls">
                            <label>Section BG: <input type="text" name="cgr_section_bg" id="cgr_section_bg" value="<?php echo esc_attr(get_option('cgr_section_bg', 'transparent')); ?>" class="cgr-input cgr-input-medium" /></label>
                            <label class="cgr-row cgr-card_slider cgr-static_grid">Card BG: <input type="color" name="cgr_bg_color" id="cgr_bg_color" value="<?php echo esc_attr(get_option('cgr_bg_color', '#ffffff')); ?>" class="cgr-color-picker" /></label>
                        </div>
                    </div>

                    <div class="cgr-form-group cgr-row cgr-all">
                        <label class="cgr-label">Star Rating</label>
                        <div class="cgr-inline-controls">
                            <label>Color: <input type="color" name="cgr_star_color" id="cgr_star_color" value="<?php echo esc_attr(get_option('cgr_star_color', '#F2B01E')); ?>" class="cgr-color-picker" /></label>
                            <label>Size: <input type="number" name="cgr_star_size" id="cgr_star_size" value="<?php echo esc_attr(get_option('cgr_star_size', 24)); ?>" class="cgr-input cgr-input-small" /> px</label>
                        </div>
                    </div>
                    
                    <div class="cgr-form-group cgr-row cgr-all">
                        <label class="cgr-label">Review Text</label>
                        <div class="cgr-inline-controls">
                            <label>Color: <input type="color" name="cgr_quote_color" id="cgr_quote_color" value="<?php echo esc_attr(get_option('cgr_quote_color', '#333333')); ?>" class="cgr-color-picker" /></label>
                            <label>Size: <input type="number" name="cgr_quote_size" id="cgr_quote_size" value="<?php echo esc_attr(get_option('cgr_quote_size', 18)); ?>" class="cgr-input cgr-input-small" /> px</label>
                            <label>Weight: 
                                <select name="cgr_quote_weight" id="cgr_quote_weight" class="cgr-input cgr-select-medium">
                                    <option value="300" <?php selected( get_option('cgr_quote_weight', '300'), '300' ); ?>>Light (300)</option>
                                    <option value="400" <?php selected( get_option('cgr_quote_weight'), '400' ); ?>>Normal (400)</option>
                                    <option value="500" <?php selected( get_option('cgr_quote_weight'), '500' ); ?>>Medium (500)</option>
                                    <option value="600" <?php selected( get_option('cgr_quote_weight'), '600' ); ?>>Semi-Bold (600)</option>
                                    <option value="700" <?php selected( get_option('cgr_quote_weight'), '700' ); ?>>Bold (700)</option>
                                </select>
                            </label>
                        </div>
                    </div>

                    <div class="cgr-form-group cgr-row cgr-all">
                        <label class="cgr-label">Customer Name</label>
                        <div class="cgr-inline-controls">
                            <label>Color: <input type="color" name="cgr_name_color" id="cgr_name_color" value="<?php echo esc_attr(get_option('cgr_name_color', '#333333')); ?>" class="cgr-color-picker" /></label>
                            <label>Size: <input type="number" name="cgr_name_size" id="cgr_name_size" value="<?php echo esc_attr(get_option('cgr_name_size', 16)); ?>" class="cgr-input cgr-input-small" /> px</label>
                            <label>Weight: 
                                <select name="cgr_name_weight" id="cgr_name_weight" class="cgr-input cgr-select-medium">
                                    <option value="300" <?php selected( get_option('cgr_name_weight'), '300' ); ?>>Light (300)</option>
                                    <option value="400" <?php selected( get_option('cgr_name_weight'), '400' ); ?>>Normal (400)</option>
                                    <option value="500" <?php selected( get_option('cgr_name_weight'), '500' ); ?>>Medium (500)</option>
                                    <option value="600" <?php selected( get_option('cgr_name_weight'), '600' ); ?>>Semi-Bold (600)</option>
                                    <option value="700" <?php selected( get_option('cgr_name_weight', '700'), '700' ); ?>>Bold (700)</option>
                                </select>
                            </label>
                        </div>
                    </div>

                    <div class="cgr-form-group cgr-row cgr-all">
                        <label class="cgr-label">Verified Badge</label>
                        <div class="cgr-inline-controls">
                            <label>Color: <input type="color" name="cgr_badge_color" id="cgr_badge_color" value="<?php echo esc_attr(get_option('cgr_badge_color', '#91C13D')); ?>" class="cgr-color-picker" /></label>
                            <label>Size: <input type="number" name="cgr_badge_size" id="cgr_badge_size" value="<?php echo esc_attr(get_option('cgr_badge_size', 11)); ?>" class="cgr-input cgr-input-small" /> px</label>
                            <label>Weight: 
                                <select name="cgr_badge_weight" id="cgr_badge_weight" class="cgr-input cgr-select-medium">
                                    <option value="300" <?php selected( get_option('cgr_badge_weight'), '300' ); ?>>Light (300)</option>
                                    <option value="400" <?php selected( get_option('cgr_badge_weight'), '400' ); ?>>Normal (400)</option>
                                    <option value="500" <?php selected( get_option('cgr_badge_weight'), '500' ); ?>>Medium (500)</option>
                                    <option value="600" <?php selected( get_option('cgr_badge_weight', '600'), '600' ); ?>>Semi-Bold (600)</option>
                                    <option value="700" <?php selected( get_option('cgr_badge_weight'), '700' ); ?>>Bold (700)</option>
                                </select>
                            </label>
                        </div>
                    </div>

                    <div class="cgr-form-group cgr-row cgr-all">
                        <label class="cgr-label">Google Review Text</label>
                        <div class="cgr-inline-controls">
                            <label>Color: <input type="color" name="cgr_watermark_color" id="cgr_watermark_color" value="<?php echo esc_attr(get_option('cgr_watermark_color', '#333333')); ?>" class="cgr-color-picker" /></label>
                            <label>Size: <input type="number" name="cgr_watermark_size" id="cgr_watermark_size" value="<?php echo esc_attr(get_option('cgr_watermark_size', 18)); ?>" class="cgr-input cgr-input-small" /> px</label>
                            <label>Weight: 
                                <select name="cgr_watermark_weight" id="cgr_watermark_weight" class="cgr-input cgr-select-medium">
                                    <option value="300" <?php selected( get_option('cgr_watermark_weight', '300'), '300' ); ?>>Light (300)</option>
                                    <option value="400" <?php selected( get_option('cgr_watermark_weight'), '400' ); ?>>Normal (400)</option>
                                    <option value="500" <?php selected( get_option('cgr_watermark_weight'), '500' ); ?>>Medium (500)</option>
                                    <option value="600" <?php selected( get_option('cgr_watermark_weight'), '600' ); ?>>Semi-Bold (600)</option>
                                    <option value="700" <?php selected( get_option('cgr_watermark_weight'), '700' ); ?>>Bold (700)</option>
                                </select>
                            </label>
                        </div>
                    </div>
                    
                    <div class="cgr-form-group cgr-row cgr-all">
                        <label class="cgr-label">"Read More" Link</label>
                        <div class="cgr-inline-controls">
                            <label>Color: <input type="color" name="cgr_readmore_color" id="cgr_readmore_color" value="<?php echo esc_attr(get_option('cgr_readmore_color', '#91C13D')); ?>" class="cgr-color-picker" /></label>
                            <label>Size: <input type="number" name="cgr_readmore_size" id="cgr_readmore_size" value="<?php echo esc_attr(get_option('cgr_readmore_size', 14)); ?>" class="cgr-input cgr-input-small" /> px</label>
                            <label>Weight: 
                                <select name="cgr_readmore_weight" id="cgr_readmore_weight" class="cgr-input cgr-select-medium">
                                    <option value="300" <?php selected( get_option('cgr_readmore_weight'), '300' ); ?>>Light (300)</option>
                                    <option value="400" <?php selected( get_option('cgr_readmore_weight'), '400' ); ?>>Normal (400)</option>
                                    <option value="500" <?php selected( get_option('cgr_readmore_weight'), '500' ); ?>>Medium (500)</option>
                                    <option value="600" <?php selected( get_option('cgr_readmore_weight', '600'), '600' ); ?>>Semi-Bold (600)</option>
                                    <option value="700" <?php selected( get_option('cgr_readmore_weight'), '700' ); ?>>Bold (700)</option>
                                </select>
                            </label>
                        </div>
                    </div>

                    <div class="cgr-form-group cgr-row cgr-minimal_slider cgr-card_slider">
                        <label class="cgr-label">Slider Controls</label>
                        <div class="cgr-inline-controls">
                            <label>Color: <input type="color" name="cgr_dot_color" id="cgr_dot_color" value="<?php echo esc_attr(get_option('cgr_dot_color', '#91C13D')); ?>" class="cgr-color-picker" /></label>
                            <label>Arrow: <input type="number" name="cgr_arrow_size" id="cgr_arrow_size" value="<?php echo esc_attr(get_option('cgr_arrow_size', 20)); ?>" class="cgr-input cgr-input-small" /> px</label>
                            <label>Dot: <input type="number" name="cgr_dot_size" id="cgr_dot_size" value="<?php echo esc_attr(get_option('cgr_dot_size', 8)); ?>" class="cgr-input cgr-input-small" /> px</label>
                        </div>
                    </div>

                    <div class="cgr-submit-wrap">
                        <?php submit_button(__( 'Save Settings', 'custom-google-reviews' ), 'primary', 'submit', false); ?>
                    </div>
                </div>
            </form>
            
            <!-- Real-Time Live Preview -->
            <div class="cgr-panel cgr-preview-panel" id="preview-section-wrapper">
                <div class="cgr-preview-header">
                    <h3><span class="dashicons dashicons-visibility"></span> Live Engine Preview</h3>
                </div>
                
                <div class="cgr-slider-wrapper">
                    <div id="cgr-preview-canvas" class="cgr-pro-container">
                        <div id="cgr-preview-layout-wrapper">
                            <?php 
                            if (empty($preview_data)) {
                                echo '<p class="cgr-preview-empty">No reviews match your current filter settings.</p>';
                            } else {
                                $index = 1;
                                foreach($preview_data as $rev) : 
                                    $raw_text = trim($rev['review_text']);
                                    $text_len = function_exists('mb_strlen') ? mb_strlen($raw_text) : strlen($raw_text);
                                    if ($excerpt_length > 0 && $text_len > $excerpt_length) {
                                        $short_slice = function_exists('mb_substr') ? mb_substr($raw_text, 0, $excerpt_length) : substr($raw_text, 0, $excerpt_length);
                                        $preview_quote = rtrim($short_slice);
                                    } else {
                                        $preview_quote = $raw_text;
                                    }
                                ?>
                                    <div class="cgr-preview-node-item" id="preview-item-<?php echo $index; ?>">
                                        <div class="cgr-card-node" id="preview-card-<?php echo $index; ?>">
                                            <div class="cgr-stars"><?php for($i=0; $i<5; $i++){ echo ($i < (int)$rev['rating']) ? '★' : '☆'; } ?></div>
                                            
                                            <p class="cgr-quote" data-raw-text="<?php echo esc_attr($raw_text); ?>" data-short-text="<?php echo esc_attr($preview_quote); ?>">"<?php echo esc_html($preview_quote); ?>"</p>
                                            
                                            <div class="cgr-author-profile">
                                                <?php if(!empty($rev['profile_photo_url'])) : ?>
                                                    <img src="<?php echo esc_url($rev['profile_photo_url']); ?>" class="cgr-preview-avatar" alt="">
                                                <?php else: ?>
                                                    <div class="cgr-preview-avatar cgr-avatar-fallback"><?php echo esc_html(substr($rev['author_name'],0,2)); ?></div>
                                                <?php endif; ?>
                                                
                                                <div class="cgr-author-title">
                                                    <span class="cgr-preview-author-name"><?php echo esc_html($rev['author_name']); ?></span>
                                                    <div class="cgr-preview-date"><?php echo esc_html(human_time_diff($rev['review_time'], current_time('timestamp'))) . ' ago'; ?></div>
                                                    <div class="cgr-preview-google-logo">Google Review</div>
                                                </div>
                                            </div>
                                            <div class="cgr-verified-badge">✓ VERIFIED REVIEW</div>
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

            <!-- DB Table Preview With Pagination -->
            <div class="cgr-panel">
                <h3 class="cgr-panel-title"><span class="dashicons dashicons-database"></span> All Stored Reviews</h3>
                <?php if ( empty( $display_stored_reviews ) ) : ?>
                    <p class="cgr-empty-state">No reviews found in your database.</p>
                <?php else : 
                    $total_reviews = count( $display_stored_reviews );
                    $per_page      = 10;
                    $total_pages   = ceil( $total_reviews / $per_page );
                    $current_page  = isset( $_GET['cgr_paged'] ) ? max( 1, intval( $_GET['cgr_paged'] ) ) : 1;
                    $offset        = ( $current_page - 1 ) * $per_page;
                    $paged_reviews = array_slice( $display_stored_reviews, $offset, $per_page );
                ?>
                    <table class="wp-list-table widefat fixed striped cgr-table">
                        <thead>
                            <tr>
                                <th style="width: 60px;">Avatar</th>
                                <th style="width: 180px;">Author</th>
                                <th style="width: 90px;">Rating</th>
                                <th>Review Text</th>
                                <th style="width: 100px;">Time</th>
                                <th style="width: 80px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $paged_reviews as $rev ) : ?>
                                <tr>
                                    <td>
                                        <?php if ( !empty($rev['profile_photo_url']) ) : ?>
                                            <img src="<?php echo esc_url($rev['profile_photo_url']); ?>" class="cgr-table-avatar" alt="">
                                        <?php else : ?>
                                            <div class="cgr-table-avatar cgr-avatar-fallback">?</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo esc_html( $rev['author_name'] ); ?></strong></td>
                                    <td class="cgr-table-stars">
                                        <?php $rating = (int) $rev['rating']; for($i=0; $i<5; $i++) { echo ($i < $rating) ? '★' : '☆'; } ?>
                                    </td>
                                    <td><?php echo esc_html( wp_trim_words( $rev['review_text'], 25 ) ); ?></td>
                                    <td class="cgr-table-time"><?php echo esc_html( human_time_diff( $rev['review_time'], current_time('timestamp') ) . ' ago' ); ?></td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('Are you sure you want to delete this review?');">
                                            <?php wp_nonce_field( 'cgr_delete_review_nonce', 'cgr_del_nonce' ); ?>
                                            <input type="hidden" name="cgr_delete_id" value="<?php echo esc_attr( $rev['id'] ); ?>">
                                            <button type="submit" class="button button-link-delete" style="color: #d63638; border: 1px solid #d63638; border-radius: 3px; padding: 3px 8px; text-decoration: none;">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="cgr-pagination" style="margin-top: 15px; display: flex; justify-content: space-between;">
                        <span class="displaying-num">
                            <?php printf( esc_html__( 'Showing %d to %d of %d reviews', 'custom-google-reviews' ), $offset + 1, min( $offset + $per_page, $total_reviews ), $total_reviews ); ?>
                        </span>
                        <div class="pagination-links">
                            <?php if ( $current_page > 1 ) : ?>
                                <a href="<?php echo esc_url( add_query_arg( 'cgr_paged', $current_page - 1 ) ); ?>" class="button">&laquo; Previous</a>
                            <?php endif; ?>
                            <span class="paging-input" style="margin: 0 10px;"><?php printf( 'Page %d of %d', $current_page, $total_pages ); ?></span>
                            <?php if ( $current_page < $total_pages ) : ?>
                                <a href="<?php echo esc_url( add_query_arg( 'cgr_paged', $current_page + 1 ) ); ?>" class="button">Next &raquo;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                document.querySelectorAll('.cgr-toggle-password').forEach(function(icon) {
                    icon.addEventListener('click', function() {
                        const input = document.getElementById(this.getAttribute('data-target'));
                        if (input.type === 'password') { input.type = 'text'; this.classList.replace('dashicons-visibility', 'dashicons-hidden'); } 
                        else { input.type = 'password'; this.classList.replace('dashicons-hidden', 'dashicons-visibility'); }
                    });
                });
                
                const providerSelect = document.getElementById('cgr_api_provider');
                const fields = document.querySelectorAll('.provider-field');

                function updateProviderFields() {
                    const selected = providerSelect.value;
                    fields.forEach(field => {
                        if (field.classList.contains('provider-' + selected)) {
                            field.style.display = 'flex';
                        } else {
                            field.style.display = 'none';
                        }
                    });
                }
                if(providerSelect) {
                    providerSelect.addEventListener('change', updateProviderFields);
                    updateProviderFields();
                }
            });
        </script>
        <?php
    }
}