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
            'cgr_api_provider'   => 'google_new', 
            'cgr_api_key'        => '', 
            'cgr_place_id'       => '', 
            'cgr_outscraper_key' => '',
            'cgr_apify_token'    => '',
            'cgr_maps_url'       => '',
            
            'cgr_sync_freq' => 86400, 'cgr_layout' => 'minimal_slider',
            'cgr_nav_type' => 'dots', 'cgr_grid_columns' => '3', 'cgr_min_rating' => 4, 'cgr_review_limit' => 5,
            'cgr_autoplay_speed' => 6000, 'cgr_sort_order' => 'highest', 'cgr_excerpt_length' => 160,
            'cgr_show_avatars' => 1, 'cgr_show_date' => 1, 'cgr_show_badge' => 1, 'cgr_show_google_logo' => 1,
            'cgr_hide_blank_reviews' => 1,
            
            'cgr_section_bg' => 'transparent', 'cgr_bg_color' => '#ffffff', 'cgr_quote_color' => '#333333',
            'cgr_name_color' => '#333333', 'cgr_star_color' => '#F2B01E', 'cgr_dot_color' => '#91C13D',
            'cgr_readmore_color' => '#91C13D', 'cgr_badge_color' => '#91C13D', 
            
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
                    'author_name' => $author,
                    'profile_photo_url' => $photo,
                    'rating' => $rating,
                    'review_text' => $text,
                    'review_time' => $time
                ), array( '%s', '%s', '%d', '%s', '%d' ) );

                add_settings_error( 'cgr_messages', 'cgr_manual_success', __( 'Manual review added successfully to your database!', 'custom-google-reviews' ), 'updated' );
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

            if ( empty( $api_key ) || empty( $place_id ) ) {
                add_settings_error( 'cgr_messages', 'cgr_sync_fail', 'Sync failed. Please check your Google API Key and Place ID.', 'error' );
                return false;
            }

            $url = sprintf( 'https://maps.googleapis.com/maps/api/place/details/json?place_id=%s&fields=reviews&reviews_sort=newest&key=%s', urlencode( $place_id ), urlencode( $api_key ) );
            $response = wp_remote_get( $url, array( 'timeout' => 10 ) );
            $data = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( isset( $data['error_message'] ) ) {
                add_settings_error( 'cgr_messages', 'cgr_sync_fail', 'Google Legacy API Error: ' . esc_html( $data['error_message'] ), 'error' );
                return false;
            }

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
            } else {
                add_settings_error( 'cgr_messages', 'cgr_sync_fail', 'Google Legacy API: No reviews found for this Place ID.', 'error' );
                return false;
            }
        }
        
        // 2. GOOGLE PLACES API (NEW)
        elseif ( $provider === 'google_new' ) {
            $api_key  = get_option( 'cgr_api_key' );
            $place_id = get_option( 'cgr_place_id' );

            if ( empty( $api_key ) || empty( $place_id ) ) {
                add_settings_error( 'cgr_messages', 'cgr_sync_fail', 'Sync failed. Please check your Google API Key and Place ID.', 'error' );
                return false;
            }

            $url = sprintf( 'https://places.googleapis.com/v1/places/%s', urlencode( $place_id ) );
            $response = wp_remote_get( $url, array( 
                'timeout' => 10, 
                'headers' => array( 'X-Goog-Api-Key' => $api_key, 'X-Goog-FieldMask' => 'reviews' ) 
            ) );

            $data = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( isset( $data['error'] ) ) {
                add_settings_error( 'cgr_messages', 'cgr_sync_fail', 'Google Places API (New) Error: ' . esc_html( $data['error']['message'] ?? 'Unknown' ), 'error' );
                return false;
            }

            if ( isset( $data['reviews'] ) && is_array( $data['reviews'] ) ) {
                foreach ( $data['reviews'] as $item ) {
                    $new_reviews_data[] = array(
                        'author_name'       => isset( $item['authorAttribution']['displayName'] ) ? sanitize_text_field( $item['authorAttribution']['displayName'] ) : __( 'Anonymous', 'custom-google-reviews' ),
                        'profile_photo_url' => isset( $item['authorAttribution']['photoUri'] ) ? esc_url_raw( $item['authorAttribution']['photoUri'] ) : '',
                        'rating'            => isset( $item['rating'] ) ? (int) $item['rating'] : 5,
                        'review_text'       => isset( $item['text']['text'] ) ? sanitize_textarea_field( $item['text']['text'] ) : '',
                        'review_time'       => isset( $item['publishTime'] ) ? strtotime( $item['publishTime'] ) : current_time('timestamp'),
                    );
                }
            } else {
                add_settings_error( 'cgr_messages', 'cgr_sync_fail', 'Google Places API (New): No reviews found for this Place ID.', 'error' );
                return false;
            }
        }

        // 3. OUTSCRAPER API
        elseif ( $provider === 'outscraper' ) {
            $api_key  = get_option( 'cgr_outscraper_key' );
            $maps_url = get_option( 'cgr_maps_url' );

            if ( empty( $api_key ) || empty( $maps_url ) ) {
                add_settings_error( 'cgr_messages', 'cgr_sync_fail', 'Sync failed. Please check your Outscraper Key and Maps URL.', 'error' );
                return false;
            }

            $url = 'https://api.app.outscraper.com/google-maps-reviews?query=' . urlencode($maps_url) . '&reviewsLimit=10&sort=newest';
            $response = wp_remote_get( $url, array( 'headers' => array('X-API-KEY' => $api_key), 'timeout' => 45 ) );
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
            } else {
                add_settings_error( 'cgr_messages', 'cgr_sync_fail', 'Outscraper API: No reviews returned. Check your quota or URL.', 'error' );
                return false;
            }
        }

        // 4. APIFY SCRAPER API
        elseif ( $provider === 'apify' ) {
            $token    = get_option( 'cgr_apify_token' );
            $maps_url = get_option( 'cgr_maps_url' );

            if ( empty( $token ) || empty( $maps_url ) ) {
                add_settings_error( 'cgr_messages', 'cgr_sync_fail', 'Sync failed. Please check your Apify Token and Maps URL.', 'error' );
                return false;
            }

            $url = 'https://api.apify.com/v2/acts/compass~crawler-google-places/run-sync-get-dataset-items?token=' . urlencode($token);
            $payload = json_encode( array( 'searchStringsArray' => array( $maps_url ), 'maxReviews' => 10 ) );
            
            $response = wp_remote_post( $url, array(
                'headers' => array( 'Content-Type' => 'application/json' ),
                'body'    => $payload,
                'timeout' => 60 
            ));
            
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
            } else {
                add_settings_error( 'cgr_messages', 'cgr_sync_fail', 'Apify API: Actor failed to return reviews. It may have timed out.', 'error' );
                return false;
            }
        }

        // INSERT INTO DATABASE
        if ( !empty( $new_reviews_data ) ) {
            $inserted = 0;
            foreach ( $new_reviews_data as $rev ) {
                if ( $rev['rating'] < $min_rating ) continue;

                $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE author_name = %s AND review_time = %d", $rev['author_name'], $rev['review_time'] ) );
                if ( ! $exists ) {
                    $wpdb->insert( $table_name, $rev, array( '%s', '%s', '%d', '%s', '%d' ) );
                    $inserted++;
                }
            }
            return true;
        }

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
        $hide_blank     = filter_var(get_option('cgr_hide_blank_reviews', 1), FILTER_VALIDATE_BOOLEAN);

        // Pre-filter database items for the admin table based on the 'hide blank' option
        $display_stored_reviews = array();
        if ( ! empty($stored_reviews) ) {
            foreach($stored_reviews as $rev) {
                if ( $hide_blank && empty( trim( $rev['review_text'] ) ) ) {
                    continue;
                }
                $display_stored_reviews[] = $rev;
            }
        }

        // Build the slider preview array
        $filtered_reviews = array();
        if ( ! empty($display_stored_reviews) ) {
            foreach ( $display_stored_reviews as $rev ) { 
                if ( (int) $rev['rating'] >= $min_rating ) { 
                    $filtered_reviews[] = $rev; 
                } 
            }
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
                            printf( esc_html__( 'Last synced: %s ago. Total reviews in database: %d', 'custom-google-reviews' ), human_time_diff( $last_sync, current_time('timestamp') ), count($stored_reviews) );
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

            <!-- Settings Form -->
            <form method="post" action="options.php" class="cgr-settings-form">
                <?php settings_fields( 'cgr_settings' ); ?>

                <!-- SECTION 1 -->
                <div class="cgr-panel">
                    <h3 class="cgr-panel-title"><?php esc_html_e( '1. Data Source Connection', 'custom-google-reviews' ); ?></h3>
                    <table class="form-table cgr-form-table">
                        <tr>
                            <th scope="row"><label><?php esc_html_e( 'Select API Provider', 'custom-google-reviews' ); ?></label></th>
                            <td>
                                <select name="cgr_api_provider" id="cgr_api_provider">
                                    <option value="google_new" <?php selected( get_option('cgr_api_provider', 'google_new'), 'google_new' ); ?>>Google Places API (New)</option>
                                    <option value="google_legacy" <?php selected( get_option('cgr_api_provider'), 'google_legacy' ); ?>>Google Places API (Legacy)</option>
                                    <option value="outscraper" <?php selected( get_option('cgr_api_provider'), 'outscraper' ); ?>>Outscraper API</option>
                                    <option value="apify" <?php selected( get_option('cgr_api_provider'), 'apify' ); ?>>Apify Scraper API</option>
                                </select>
                            </td>
                        </tr>

                        <!-- GOOGLE FIELDS -->
                        <tr class="provider-field provider-google_new provider-google_legacy">
                            <th scope="row"><label><?php esc_html_e( 'Google API Key', 'custom-google-reviews' ); ?></label></th>
                            <td>
                                <div style="position: relative; display: inline-block;">
                                    <input type="password" name="cgr_api_key" id="cgr_api_key" value="<?php echo esc_attr( get_option('cgr_api_key') ); ?>" class="regular-text" style="padding-right: 30px;" />
                                    <span class="dashicons dashicons-visibility cgr-toggle-password" data-target="cgr_api_key" style="position: absolute; right: 8px; top: 6px; cursor: pointer; color: #888;"></span>
                                </div>
                                <p class="description">Requires the selected Places API version enabled in Google Cloud.</p>
                            </td>
                        </tr>
                        <tr class="provider-field provider-google_new provider-google_legacy">
                            <th scope="row"><label><?php esc_html_e( 'Google Place ID', 'custom-google-reviews' ); ?></label></th>
                            <td><input type="text" name="cgr_place_id" id="cgr_place_id" value="<?php echo esc_attr( get_option('cgr_place_id') ); ?>" class="regular-text" /></td>
                        </tr>

                        <!-- OUTSCRAPER FIELDS -->
                        <tr class="provider-field provider-outscraper" style="display:none;">
                            <th scope="row"><label><?php esc_html_e( 'Outscraper API Key', 'custom-google-reviews' ); ?></label></th>
                            <td>
                                <div style="position: relative; display: inline-block;">
                                    <input type="password" name="cgr_outscraper_key" id="cgr_outscraper_key" value="<?php echo esc_attr( get_option('cgr_outscraper_key') ); ?>" class="regular-text" style="padding-right: 30px;" />
                                    <span class="dashicons dashicons-visibility cgr-toggle-password" data-target="cgr_outscraper_key" style="position: absolute; right: 8px; top: 6px; cursor: pointer; color: #888;"></span>
                                </div>
                                <p class="description">Get this from your Outscraper account dashboard.</p>
                            </td>
                        </tr>

                        <!-- APIFY FIELDS -->
                        <tr class="provider-field provider-apify" style="display:none;">
                            <th scope="row"><label><?php esc_html_e( 'Apify API Token', 'custom-google-reviews' ); ?></label></th>
                            <td>
                                <div style="position: relative; display: inline-block;">
                                    <input type="password" name="cgr_apify_token" id="cgr_apify_token" value="<?php echo esc_attr( get_option('cgr_apify_token') ); ?>" class="regular-text" style="padding-right: 30px;" />
                                    <span class="dashicons dashicons-visibility cgr-toggle-password" data-target="cgr_apify_token" style="position: absolute; right: 8px; top: 6px; cursor: pointer; color: #888;"></span>
                                </div>
                                <p class="description">Found in Apify Console → Settings → API & Integrations.</p>
                            </td>
                        </tr>

                        <!-- SHARED SCRAPER FIELD -->
                        <tr class="provider-field provider-outscraper provider-apify" style="display:none;">
                            <th scope="row"><label><?php esc_html_e( 'Google Maps Profile URL', 'custom-google-reviews' ); ?></label></th>
                            <td>
                                <input type="url" name="cgr_maps_url" id="cgr_maps_url" value="<?php echo esc_attr( get_option('cgr_maps_url') ); ?>" class="regular-text" />
                                <p class="description">Paste the full URL to the business on Google Maps.</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label><?php esc_html_e( 'Automatic Sync Frequency', 'custom-google-reviews' ); ?></label></th>
                            <td>
                                <select name="cgr_sync_freq" id="cgr_sync_freq">
                                    <option value="43200" <?php selected( get_option('cgr_sync_freq'), 43200 ); ?>><?php esc_html_e( 'Every 12 Hours', 'custom-google-reviews' ); ?></option>
                                    <option value="86400" <?php selected( get_option('cgr_sync_freq', 86400), 86400 ); ?>><?php esc_html_e( 'Every 24 Hours', 'custom-google-reviews' ); ?></option>
                                    <option value="604800" <?php selected( get_option('cgr_sync_freq'), 604800 ); ?>><?php esc_html_e( 'Every 7 Days', 'custom-google-reviews' ); ?></option>
                                    <option value="1296000" <?php selected( get_option('cgr_sync_freq'), 1296000 ); ?>><?php esc_html_e( 'Every 15 Days', 'custom-google-reviews' ); ?></option>
                                    <option value="2592000" <?php selected( get_option('cgr_sync_freq'), 2592000 ); ?>><?php esc_html_e( 'Every 30 Days (1 Month)', 'custom-google-reviews' ); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            const providerSelect = document.getElementById('cgr_api_provider');
                            const fields = document.querySelectorAll('.provider-field');
                            
                            function updateProviderFields() {
                                const selected = providerSelect.value;
                                fields.forEach(field => {
                                    if (field.classList.contains('provider-' + selected)) {
                                        field.style.display = 'table-row';
                                    } else {
                                        field.style.display = 'none';
                                    }
                                });
                            }
                            providerSelect.addEventListener('change', updateProviderFields);
                            updateProviderFields();

                            // Password toggle logic
                            document.querySelectorAll('.cgr-toggle-password').forEach(function(icon) {
                                icon.addEventListener('click', function() {
                                    const targetId = this.getAttribute('data-target');
                                    const input = document.getElementById(targetId);
                                    if (input.type === 'password') {
                                        input.type = 'text';
                                        this.classList.remove('dashicons-visibility');
                                        this.classList.add('dashicons-hidden');
                                    } else {
                                        input.type = 'password';
                                        this.classList.remove('dashicons-hidden');
                                        this.classList.add('dashicons-visibility');
                                    }
                                });
                            });
                        });
                    </script>
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
                            <th scope="row"><?php esc_html_e( 'Hide Blank Reviews', 'custom-google-reviews' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="cgr_hide_blank_reviews" id="cgr_hide_blank_reviews" value="1" <?php checked(1, get_option('cgr_hide_blank_reviews', 1)); ?> />
                                    <?php esc_html_e( 'Hide rating-only reviews (reviews that contain no written text).', 'custom-google-reviews' ); ?>
                                </label>
                            </td>
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

            <!-- Manual Review Creation Panel -->
            <div class="cgr-panel">
                <h3 class="cgr-panel-title"><span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Add Manual Review', 'custom-google-reviews' ); ?></h3>
                <form method="post" style="max-width: 600px;">
                    <?php wp_nonce_field( 'cgr_manual_review_nonce', 'cgr_review_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label><?php esc_html_e( 'Author Name', 'custom-google-reviews' ); ?></label></th>
                            <td><input type="text" name="manual_author" class="regular-text" required /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php esc_html_e( 'Rating (1-5)', 'custom-google-reviews' ); ?></label></th>
                            <td><input type="number" name="manual_rating" min="1" max="5" value="5" class="cgr-input-small" required /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php esc_html_e( 'Review Text', 'custom-google-reviews' ); ?></label></th>
                            <td><textarea name="manual_text" rows="3" class="large-text"></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php esc_html_e( 'Profile Photo URL', 'custom-google-reviews' ); ?></label></th>
                            <td>
                                <input type="url" name="manual_photo" class="regular-text" placeholder="https://..." />
                                <p class="description">Optional. Provide a direct image link.</p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit" style="margin-bottom: 0;">
                        <button type="submit" name="cgr_add_manual_review" class="cgr-btn cgr-btn-primary">
                            <?php esc_attr_e( 'Add Review to Database', 'custom-google-reviews' ); ?>
                        </button>
                    </p>
                </form>
            </div>

            <!-- DB Table Preview With Pagination -->
            <div class="cgr-panel">
                <h3 class="cgr-panel-title"><span class="dashicons dashicons-database"></span> <?php esc_html_e( 'All Stored Reviews in Database Table', 'custom-google-reviews' ); ?></h3>
                <?php if ( empty( $display_stored_reviews ) ) : ?>
                    <p class="cgr-empty-state"><?php esc_html_e( 'No reviews match your current display settings. Please sync reviews or adjust filters.', 'custom-google-reviews' ); ?></p>
                <?php else : 
                    $total_reviews = count( $display_stored_reviews );
                    $per_page      = 5;
                    $total_pages   = ceil( $total_reviews / $per_page );
                    $current_page  = isset( $_GET['cgr_paged'] ) ? max( 1, intval( $_GET['cgr_paged'] ) ) : 1;
                    $offset        = ( $current_page - 1 ) * $per_page;
                    $paged_reviews = array_slice( $display_stored_reviews, $offset, $per_page );
                ?>
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
                            <?php foreach ( $paged_reviews as $rev ) : ?>
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
                    
                    <div class="cgr-pagination" style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center;">
                        <span class="displaying-num" style="color: #646970;">
                            <?php printf( esc_html__( 'Showing %d to %d of %d reviews', 'custom-google-reviews' ), $offset + 1, min( $offset + $per_page, $total_reviews ), $total_reviews ); ?>
                        </span>
                        <div class="pagination-links">
                            <?php if ( $current_page > 1 ) : ?>
                                <a href="<?php echo esc_url( add_query_arg( 'cgr_paged', $current_page - 1 ) ); ?>" class="button">&laquo; <?php esc_html_e( 'Previous', 'custom-google-reviews' ); ?></a>
                            <?php else : ?>
                                <span class="button disabled" style="opacity: 0.5; pointer-events: none;">&laquo; <?php esc_html_e( 'Previous', 'custom-google-reviews' ); ?></span>
                            <?php endif; ?>
                            
                            <span class="paging-input" style="margin: 0 10px;">
                                <?php printf( esc_html__( 'Page %d of %d', 'custom-google-reviews' ), $current_page, $total_pages ); ?>
                            </span>

                            <?php if ( $current_page < $total_pages ) : ?>
                                <a href="<?php echo esc_url( add_query_arg( 'cgr_paged', $current_page + 1 ) ); ?>" class="button"><?php esc_html_e( 'Next', 'custom-google-reviews' ); ?> &raquo;</a>
                            <?php else : ?>
                                <span class="button disabled" style="opacity: 0.5; pointer-events: none;"><?php esc_html_e( 'Next', 'custom-google-reviews' ); ?> &raquo;</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>
        <?php
    }
}