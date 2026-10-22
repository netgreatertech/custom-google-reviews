<?php
// SECURITY: Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CGR_Frontend {

    public function __construct() {
        add_shortcode( 'custom_google_reviews', array( $this, 'render_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
    }

    public function register_assets() {
        wp_register_style( 'swiper-css', 'https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css', array(), '10.0' );
        wp_register_script( 'swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js', array(), '10.0', true );

        $assets_url = plugins_url( '../assets', __FILE__ );
        wp_register_style( 'cgr-frontend-css', $assets_url . '/css/cgr-frontend.css', array(), time() );
        wp_register_script( 'cgr-frontend-js', $assets_url . '/js/cgr-frontend.js', array('swiper-js'), time(), true );
    }

    private function hex2rgba($color, $opacity = false) {
        $default = 'rgb(0,0,0)';
        if ( empty($color) || $color === 'transparent' ) return 'transparent';
        $color = ltrim( sanitize_hex_color($color), '#' );
        if ( strlen($color) === 6 ) $hex = array( substr($color, 0, 2), substr($color, 2, 2), substr($color, 4, 2) );
        elseif ( strlen($color) === 3 ) $hex = array( $color[0].$color[0], $color[1].$color[1], $color[2].$color[2] );
        else return $default;
        $rgb = array( hexdec( $hex[0] ), hexdec( $hex[1] ), hexdec( $hex[2] ) );
        if ( $opacity !== false ) {
            return 'rgba(' . implode( ",", $rgb ) . ',' . (abs($opacity) > 1 ? 1.0 : (float)$opacity) . ')';
        }
        return 'rgb(' . implode( ",", $rgb ) . ')';
    }

    public function render_shortcode( $atts = array() ) {
        $atts = shortcode_atts( array(
            'layout'           => get_option( 'cgr_layout', 'minimal_slider' ),
            'nav_type'         => get_option( 'cgr_nav_type', 'dots' ),
            'grid_cols'        => get_option( 'cgr_grid_columns', '3' ),
            'min_rating'       => get_option( 'cgr_min_rating', 4 ),
            'limit'            => get_option( 'cgr_review_limit', 5 ),
            'sort_order'       => get_option( 'cgr_sort_order', 'highest' ),
            'excerpt_length'   => get_option( 'cgr_excerpt_length', 160 ),
            'readmore_action'  => get_option( 'cgr_readmore_action', 'modal' ),
            'modal_bg'         => get_option( 'cgr_modal_bg', '#112837' ),
            'modal_text_color' => get_option( 'cgr_modal_text_color', '#ffffff' ),
            'modal_radius'     => get_option( 'cgr_modal_radius', '12' ),
            'speed'            => get_option( 'cgr_autoplay_speed', 6000 ),
            'show_avatars'     => get_option( 'cgr_show_avatars', 1 ),
            'show_date'        => get_option( 'cgr_show_date', 1 ),
            'show_badge'       => get_option( 'cgr_show_badge', 1 ),
            'show_google'      => get_option( 'cgr_show_google_logo', 1 ),
            'hide_blank'       => get_option( 'cgr_hide_blank_reviews', 1 ),
            'section_bg'       => get_option( 'cgr_section_bg', 'transparent' ),
            'bg_color'         => get_option( 'cgr_bg_color', '#ffffff' ),
            
            'quote_color'      => get_option( 'cgr_quote_color', '#333333' ),
            'quote_size'       => get_option( 'cgr_quote_size', '16' ),
            'quote_weight'     => get_option( 'cgr_quote_weight', '400' ),
            
            'name_color'       => get_option( 'cgr_name_color', '#333333' ),
            'name_size'        => get_option( 'cgr_name_size', '16' ),
            'name_weight'      => get_option( 'cgr_name_weight', '700' ),
            
            'star_color'       => get_option( 'cgr_star_color', '#F2B01E' ),
            'star_size'        => get_option( 'cgr_star_size', '24' ),
            
            'badge_color'      => get_option( 'cgr_badge_color', '#91C13D' ),
            'badge_size'       => get_option( 'cgr_badge_size', '11' ),
            'badge_weight'     => get_option( 'cgr_badge_weight', '600' ),
            
            'watermark_color'  => get_option( 'cgr_watermark_color', '#333333' ),
            'watermark_size'   => get_option( 'cgr_watermark_size', '12' ),
            'watermark_weight' => get_option( 'cgr_watermark_weight', '400' ),
            
            'dot_color'        => get_option( 'cgr_dot_color', '#91C13D' ),
            'readmore_color'   => get_option( 'cgr_readmore_color', '#91C13D' ),
            'padding'          => get_option( 'cgr_card_padding', 40 ),
            'card_radius'      => get_option( 'cgr_card_radius', 12 ),
        ), $atts, 'custom_google_reviews' );

        $raw_reviews = CGR_Admin::get_reviews();

        if ( ! $raw_reviews || ! is_array( $raw_reviews ) ) {
            return '<p style="text-align:center; padding: 20px;">' . esc_html__( 'Please configure Google API connection in the dashboard.', 'custom-google-reviews' ) . '</p>';
        }

        $layout           = esc_attr( $atts['layout'] );
        $nav_type         = esc_attr( $atts['nav_type'] );
        $grid_cols        = (int) $atts['grid_cols'];
        $min_rating       = (int) $atts['min_rating'];
        $review_limit     = (int) $atts['limit'];
        $sort_order       = esc_attr( $atts['sort_order'] );
        $excerpt_length   = (int) $atts['excerpt_length'];
        $readmore_action  = esc_attr( $atts['readmore_action'] );
        $modal_bg         = esc_attr( $atts['modal_bg'] );
        $modal_text_color = esc_attr( $atts['modal_text_color'] );
        $modal_radius     = (int) $atts['modal_radius'];
        
        $show_avatars     = filter_var( $atts['show_avatars'], FILTER_VALIDATE_BOOLEAN );
        $show_date        = filter_var( $atts['show_date'], FILTER_VALIDATE_BOOLEAN );
        $show_badge       = filter_var( $atts['show_badge'], FILTER_VALIDATE_BOOLEAN );
        $show_google      = filter_var( $atts['show_google'], FILTER_VALIDATE_BOOLEAN );
        $hide_blank       = filter_var( $atts['hide_blank'], FILTER_VALIDATE_BOOLEAN );

        $section_bg       = esc_attr( $atts['section_bg'] );
        $bg_color         = esc_attr( $atts['bg_color'] );
        $quote_color      = esc_attr( $atts['quote_color'] );
        $name_color       = esc_attr( $atts['name_color'] );
        $star_color       = esc_attr( $atts['star_color'] );
        $dot_color        = esc_attr( $atts['dot_color'] );
        $readmore_color   = esc_attr( $atts['readmore_color'] );
        $badge_color      = esc_attr( $atts['badge_color'] );
        $badge_bg         = esc_attr( $this->hex2rgba($badge_color, 0.15) );
        
        $quote_size       = (int) $atts['quote_size'];
        $quote_weight     = esc_attr( $atts['quote_weight'] );
        
        $name_size        = (int) $atts['name_size'];
        $name_weight      = esc_attr( $atts['name_weight'] );
        
        $star_size        = (int) $atts['star_size'];
        
        $badge_size       = (int) $atts['badge_size'];
        $badge_weight     = esc_attr( $atts['badge_weight'] );
        
        $watermark_color  = esc_attr( $atts['watermark_color'] );
        $watermark_size   = (int) $atts['watermark_size'];
        $watermark_weight = esc_attr( $atts['watermark_weight'] );

        $padding          = (int) $atts['padding'];
        $card_radius      = (int) $atts['card_radius'];
        $speed            = (int) $atts['speed'];

        $filtered_reviews = array();
        foreach ( $raw_reviews as $review ) {
            $rating = isset( $review['rating'] ) ? (int) $review['rating'] : 5;
            
            if ( $hide_blank && empty( trim( $review['text'] ) ) ) {
                continue;
            }

            if ( $rating >= $min_rating ) {
                $filtered_reviews[] = $review;
            }
        }

        if ( $sort_order === 'highest' ) { usort( $filtered_reviews, function($a, $b) { return ( (int)$b['rating'] <=> (int)$a['rating'] ); }); } 
        elseif ( $sort_order === 'newest' ) { usort( $filtered_reviews, function($a, $b) { return ( (int)$b['time'] <=> (int)$a['time'] ); }); } 
        elseif ( $sort_order === 'random' ) { shuffle( $filtered_reviews ); }

        $reviews = ( $review_limit > 0 && count( $filtered_reviews ) > $review_limit ) ? array_slice( $filtered_reviews, 0, $review_limit ) : $filtered_reviews;

        if ( empty( $reviews ) ) {
            return '<p style="text-align:center; padding: 20px;">' . esc_html__( 'No reviews match the current filtering threshold.', 'custom-google-reviews' ) . '</p>';
        }

        wp_enqueue_style( 'cgr-frontend-css' );

        if ( $layout !== 'static_grid' ) {
            wp_enqueue_style( 'swiper-css' );
            wp_enqueue_script( 'swiper-js' );
            wp_enqueue_script( 'cgr-frontend-js' );
        } else {
            wp_enqueue_script( 'cgr-frontend-js' );
        }

        ob_start();
        ?>
        <div class="cgr-pro-section-wrapper" style="
            --cgr-section-bg: <?php echo $section_bg; ?>;
            --cgr-bg-color: <?php echo $bg_color; ?>;
            --cgr-name-color: <?php echo $name_color; ?>;
            --cgr-name-size: <?php echo $name_size; ?>px;
            --cgr-name-weight: <?php echo $name_weight; ?>;
            --cgr-star-color: <?php echo $star_color; ?>;
            --cgr-star-size: <?php echo $star_size; ?>px;
            --cgr-quote-color: <?php echo $quote_color; ?>;
            --cgr-quote-size: <?php echo $quote_size; ?>px;
            --cgr-quote-weight: <?php echo $quote_weight; ?>;
            --cgr-readmore-color: <?php echo $readmore_color; ?>;
            --cgr-badge-color: <?php echo $badge_color; ?>;
            --cgr-badge-bg: <?php echo $badge_bg; ?>;
            --cgr-badge-size: <?php echo $badge_size; ?>px;
            --cgr-badge-weight: <?php echo $badge_weight; ?>;
            --cgr-dot-color: <?php echo $dot_color; ?>;
            --cgr-padding: <?php echo $padding; ?>px;
            --cgr-card-radius: <?php echo $card_radius; ?>px;
            --cgr-grid-cols: <?php echo $grid_cols; ?>;
            --cgr-watermark-color: <?php echo $watermark_color; ?>;
            --cgr-watermark-size: <?php echo $watermark_size; ?>px;
            --cgr-watermark-weight: <?php echo $watermark_weight; ?>;
        ">
            <div class="cgr-slider-wrapper">
                <div class="cgr-pro-container cgr-layout-<?php echo $layout; ?> <?php echo ($layout !== 'static_grid') ? 'swiper cgr-swiper-container' : ''; ?>"
                     data-layout="<?php echo esc_attr($layout); ?>"
                     data-speed="<?php echo esc_attr($speed); ?>"
                     data-nav="<?php echo esc_attr($nav_type); ?>"
                     data-readmore-action="<?php echo esc_attr($readmore_action); ?>"
                     data-modal-bg="<?php echo esc_attr($modal_bg); ?>"
                     data-modal-text="<?php echo esc_attr($modal_text_color); ?>"
                     data-modal-radius="<?php echo esc_attr($modal_radius); ?>px">
                     
                    <div class="<?php echo ($layout !== 'static_grid') ? 'swiper-wrapper' : ''; ?>">
                        <?php foreach ( $reviews as $index => $review ) : 
                            $unique_id = 'cgr-text-' . (int)$index . '-' . wp_rand(1000,9999);
                            $review_text = isset($review['text']) ? trim($review['text']) : '';
                            
                            $text_length = function_exists('mb_strlen') ? mb_strlen($review_text) : strlen($review_text);
                            $is_long_text = ( $excerpt_length > 0 && $text_length > $excerpt_length );
                            
                            if ( $is_long_text ) {
                                $short_slice = function_exists('mb_substr') ? mb_substr($review_text, 0, $excerpt_length) : substr($review_text, 0, $excerpt_length);
                                $short_text  = rtrim($short_slice) . '...';
                            } else {
                                $short_text  = $review_text;
                            }

                            $time_ago = isset($review['time']) ? sprintf( esc_html__('%s ago', 'custom-google-reviews'), human_time_diff( $review['time'], current_time('timestamp') ) ) : '';
                            $rating = isset($review['rating']) ? (int) $review['rating'] : 5;
                            $author_name_clean = isset($review['author_name']) ? $review['author_name'] : __( 'Anonymous', 'custom-google-reviews' );
                        ?>
                            <div class="<?php echo ($layout !== 'static_grid') ? 'swiper-slide' : 'cgr-grid-item'; ?>">
                                <div class="cgr-card-node"
                                     data-author="<?php echo esc_attr($author_name_clean); ?>"
                                     data-date="<?php echo esc_attr($time_ago); ?>"
                                     data-avatar="<?php echo ($show_avatars && !empty($review['profile_photo_url'])) ? esc_url($review['profile_photo_url']) : ''; ?>"
                                     data-rating="<?php echo esc_attr($rating); ?>">
                                    
                                    <?php if ($layout === 'minimal_slider') : ?>
                                        <div class="cgr-stars"><?php for ( $i = 0; $i < 5; $i++ ) { echo ( $i < $rating ) ? '★' : '☆'; } ?></div>
                                        
                                        <p class="cgr-quote" id="<?php echo esc_attr($unique_id); ?>" data-short-text="<?php echo esc_attr( '"' . $short_text . '"' ); ?>" data-full-text="<?php echo esc_attr( '"' . $review_text . '"' ); ?>">"<?php echo esc_html( $short_text ); ?>"</p>
                                        
                                        <?php if ( $is_long_text ) : ?>
                                            <a class="cgr-read-more-trigger" data-target="<?php echo esc_attr($unique_id); ?>" data-more="<?php esc_attr_e('Read More', 'custom-google-reviews'); ?>" data-less="<?php esc_attr_e('Read Less', 'custom-google-reviews'); ?>"><?php esc_html_e('Read More', 'custom-google-reviews'); ?></a>
                                        <?php endif; ?>

                                        <div class="cgr-profile-header cgr-profile-minimal">
                                            <?php if ( $show_avatars && !empty($review['profile_photo_url']) ) : ?>
                                                <img src="<?php echo esc_url($review['profile_photo_url']); ?>" class="cgr-avatar-img" alt="">
                                            <?php endif; ?>
                                            
                                            <div class="cgr-author-title">
                                                <?php echo esc_html( $author_name_clean ); ?>
                                                
                                                <?php if ( $show_date && !empty($time_ago) ) : ?>
                                                    <div class="cgr-review-date"><?php echo esc_html($time_ago); ?></div>
                                                <?php endif; ?>
                                                
                                                <?php if ( $show_google ) : ?>
                                                    <div class="cgr-google-watermark">Google Review</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if ( $show_badge ) : ?>
                                            <div class="cgr-verified-badge">✓ <?php esc_html_e( 'VERIFIED REVIEW', 'custom-google-reviews' ); ?></div>
                                        <?php endif; ?>
                                    
                                    <?php else : ?>
                                        <div class="cgr-profile-header cgr-profile-standard">
                                            <?php if ( $show_avatars && !empty($review['profile_photo_url']) ) : ?>
                                                <img src="<?php echo esc_url($review['profile_photo_url']); ?>" class="cgr-avatar-img" alt="">
                                            <?php endif; ?>
                                            
                                            <div class="cgr-author-title">
                                                <?php echo esc_html( $author_name_clean ); ?>
                                                
                                                <?php if ( $show_date && !empty($time_ago) ) : ?>
                                                    <div class="cgr-review-date"><?php echo esc_html($time_ago); ?></div>
                                                <?php endif; ?>
                                                
                                                <?php if ( $show_google ) : ?>
                                                    <div class="cgr-google-watermark">Google Review</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="cgr-stars cgr-stars-left"><?php for ( $i = 0; $i < 5; $i++ ) { echo ( $i < $rating ) ? '★' : '☆'; } ?></div>
                                        
                                        <p class="cgr-quote cgr-quote-left" id="<?php echo esc_attr($unique_id); ?>" data-short-text="<?php echo esc_attr( '"' . $short_text . '"' ); ?>" data-full-text="<?php echo esc_attr( '"' . $review_text . '"' ); ?>">"<?php echo esc_html( $short_text ); ?>"</p>
                                        
                                        <?php if ( $is_long_text ) : ?>
                                            <a class="cgr-read-more-trigger cgr-read-more-left" data-target="<?php echo esc_attr($unique_id); ?>" data-more="<?php esc_attr_e('Read More', 'custom-google-reviews'); ?>" data-less="<?php esc_attr_e('Read Less', 'custom-google-reviews'); ?>"><?php esc_html_e('Read More', 'custom-google-reviews'); ?></a>
                                        <?php endif; ?>
                                        
                                        <?php if ( $show_badge ) : ?>
                                            <div class="cgr-verified-badge cgr-verified-left">✓ <?php esc_html_e( 'VERIFIED REVIEW', 'custom-google-reviews' ); ?></div>
                                        <?php endif; ?>

                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ( $layout !== 'static_grid' && ($nav_type === 'dots' || $nav_type === 'both') ) : ?>
                        <div class="swiper-pagination"></div>
                    <?php endif; ?>
                </div>

                <?php if ( $layout !== 'static_grid' && ($nav_type === 'arrows' || $nav_type === 'both') ) : ?>
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-button-next"></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}