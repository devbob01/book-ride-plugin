<?php
/**
 * Booking form shortcode
 */

if (!defined('ABSPATH')) {
    exit;
}

class HP_Booking_Shortcode {
    
    private static $instance = null;
    private static $shortcode_rendered = false;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_shortcode('hp_booking_form', array($this, 'render_booking_form'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'ensure_scripts_loaded'));
        add_action('wp_head', array($this, 'add_hpbooking_definition'), 1);
    }
    
    /**
     * Add hpBooking definition in head - ensures it's available before any scripts
     */
    public function add_hpbooking_definition() {
        // Only add if shortcode might be used (check post content or if already rendered)
        global $post;
        $has_shortcode = false;
        if ($post && isset($post->post_content)) {
            $has_shortcode = has_shortcode($post->post_content, 'hp_booking_form');
        }
        
        if ($has_shortcode || self::$shortcode_rendered) {
            $stripe_key = self::get_active_stripe_publishable_key();
            ?>
            <script type="text/javascript">
            // Define hpBooking in head - must be available before booking-app.js loads
            if (typeof window.hpBooking === 'undefined') {
                window.hpBooking = {
                    apiUrl: '<?php echo esc_js(rest_url('hp-booking/v1/')); ?>',
                    nonce: '<?php echo wp_create_nonce('wp_rest'); ?>',
                    pluginUrl: '<?php echo esc_js(HP_BOOKING_PLUGIN_URL); ?>',
                    stripeKey: '<?php echo esc_js($stripe_key); ?>'
                };
            }
            </script>
            <?php
        }
    }
    
    /**
     * Get the active Stripe publishable key based on test/live mode
     */
    private static function get_active_stripe_publishable_key() {
        $db = HP_Booking_Database::get_instance();
        $test_mode = $db->get_setting('stripe_test_mode', '1');
        $is_test = ($test_mode === '1' || $test_mode === 'on');
        if ($is_test) {
            return trim($db->get_setting('stripe_publishable_key_test', ''));
        }
        return trim($db->get_setting('stripe_publishable_key', ''));
    }
    
    /**
     * Render booking form shortcode
     */
    public function render_booking_form($atts) {
        // Mark that shortcode was rendered
        self::$shortcode_rendered = true;
        
        // Enqueue scripts
        $this->do_enqueue_scripts();
        
        $db = HP_Booking_Database::get_instance();
        $stripe_key = self::get_active_stripe_publishable_key();
        $google_maps_key = $db->get_setting('google_maps_api_key');
        
        ob_start();
        ?>
        <script type="text/javascript">
        // Define hpBooking immediately - must execute before any other scripts
        window.hpBooking = window.hpBooking || {
            apiUrl: '<?php echo esc_js(rest_url('hp-booking/v1/')); ?>',
            nonce: '<?php echo wp_create_nonce('wp_rest'); ?>',
            pluginUrl: '<?php echo esc_js(HP_BOOKING_PLUGIN_URL); ?>',
            stripeKey: '<?php echo esc_js($stripe_key); ?>'
        };
        // Always update stripeKey even if hpBooking already exists
        window.hpBooking.stripeKey = '<?php echo esc_js($stripe_key); ?>';
        </script>
        <div id="hp-booking-app" data-stripe-key="<?php echo esc_attr($stripe_key); ?>" data-google-maps-key="<?php echo esc_attr($google_maps_key); ?>">
            <div id="hp-booking-loading">Loading booking form...</div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        global $post;
        
        // Check if shortcode exists in current post/page
        $has_shortcode = false;
        if ($post && isset($post->post_content)) {
            $has_shortcode = has_shortcode($post->post_content, 'hp_booking_form');
        }
        
        // If shortcode was rendered, we need to enqueue
        if (self::$shortcode_rendered || $has_shortcode) {
            $this->do_enqueue_scripts();
        }
    }
    
    /**
     * Ensure scripts are loaded if shortcode was rendered
     */
    public function ensure_scripts_loaded() {
        if (self::$shortcode_rendered) {
            $this->do_enqueue_scripts();
        }
    }
    
    /**
     * Actually enqueue the scripts
     */
    private function do_enqueue_scripts() {
        // Prevent double enqueuing
        static $enqueued = false;
        if ($enqueued) {
            return;
        }
        $enqueued = true;
        
        // Google Maps
        $db = HP_Booking_Database::get_instance();
        $google_maps_key = $db->get_setting('google_maps_api_key');
        if ($google_maps_key) {
            wp_enqueue_script(
                'google-maps',
                'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($google_maps_key) . '&libraries=places',
                array(),
                null,
                true
            );
        }
        
        // Stripe.js - Only load if Stripe key is configured and user selects "Pay Now"
        // We'll load it dynamically when needed to avoid errors
        // Don't load it here - load it on-demand in the booking app
        
        // canvas-confetti – lightweight celebration animation for booking confirmation
        wp_enqueue_script(
            'canvas-confetti',
            'https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js',
            array(),
            null,
            true // footer
        );

        // Flatpickr library (Core + Confirm Date Plugin)
        wp_enqueue_style(
            'flatpickr-css',
            'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
            array(),
            '4.6.13'
        );
        wp_enqueue_style(
            'flatpickr-confirm-css',
            'https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/confirmDate/confirmDate.css',
            array('flatpickr-css'),
            '4.6.13'
        );
        wp_enqueue_script(
            'flatpickr-js',
            'https://cdn.jsdelivr.net/npm/flatpickr',
            array(),
            '4.6.13',
            true
        );
        wp_enqueue_script(
            'flatpickr-confirm-js',
            'https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/confirmDate/confirmDate.js',
            array('flatpickr-js'),
            '4.6.13',
            true
        );

        // Booking app script (vanilla JS version - no React compilation needed)
        // Load in footer (true) but ensure hpBooking is defined BEFORE it loads
        wp_enqueue_script(
            'hp-booking-app',
            HP_BOOKING_PLUGIN_URL . 'public/assets/booking-app.js',
            array(), // Remove jquery dependency - we don't use it
            HP_BOOKING_VERSION,
            true // Load in footer
        );
        
        wp_enqueue_style(
            'hp-booking-style',
            HP_BOOKING_PLUGIN_URL . 'public/assets/styles.css',
            array(),
            HP_BOOKING_VERSION
        );
        
        // Use wp_add_inline_script to explicitly set window.hpBooking BEFORE the main script
        // This is a backup in case the inline script in shortcode doesn't run first
        $stripe_key_for_inline = self::get_active_stripe_publishable_key();
        $inline_script = sprintf(
            "if (typeof window.hpBooking === 'undefined') { window.hpBooking = %s; } else if (!window.hpBooking.stripeKey) { window.hpBooking.stripeKey = %s; }",
            wp_json_encode(array(
                'apiUrl' => rest_url('hp-booking/v1/'),
                'nonce' => wp_create_nonce('wp_rest'),
                'pluginUrl' => HP_BOOKING_PLUGIN_URL,
                'stripeKey' => $stripe_key_for_inline
            )),
            wp_json_encode($stripe_key_for_inline)
        );
        wp_add_inline_script('hp-booking-app', $inline_script, 'before');
    }
}
