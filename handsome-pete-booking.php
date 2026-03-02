<?php
/**
 * Plugin Name: Handsome Pete Booking System
 * Plugin URI: https://handsomepete.com
 * Description: Custom ride booking system for Handsome Pete transport service with distance-based pricing, Google Maps integration, and Stripe payments.
 * Version: 1.5.1
 * Author: Handsome Pete
 * Author URI: https://handsomepete.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: handsome-pete-booking
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('HP_BOOKING_VERSION', '1.5.9');
define('HP_BOOKING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HP_BOOKING_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HP_BOOKING_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class Handsome_Pete_Booking {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Load plugin
        add_action('plugins_loaded', array($this, 'load_plugin'));
        
        // Load text domain
        add_action('init', array($this, 'load_textdomain'));

        // Public payment result page (shown to customer after QR-code Stripe Checkout)
        add_action('template_redirect', array($this, 'handle_payment_result_page'));
    }

    /**
     * Show a simple "Payment received" or "Payment cancelled" page
     * when the customer is redirected back from Stripe Checkout.
     */
    public function handle_payment_result_page() {
        if (isset($_GET['hp_payment_complete'])) {
            status_header(200);
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
               . '<title>Payment Complete</title>'
               . '<style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f5f6f7;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;}'
               . '.c{text-align:center;max-width:400px}.i{width:72px;height:72px;background:#00a32a;color:#fff;border-radius:50%;font-size:36px;display:flex;align-items:center;justify-content:center;margin:0 auto 20px}'
               . 'h1{font-size:1.5rem;margin-bottom:8px;color:#1d2327}p{color:#646970;font-size:15px;line-height:1.5}</style></head>'
               . '<body><div class="c"><div class="i">&#10003;</div><h1>Payment received!</h1><p>Thank you for your payment. You can close this page now.</p></div></body></html>';
            exit;
        }
        if (isset($_GET['hp_payment_cancelled'])) {
            status_header(200);
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
               . '<title>Payment Cancelled</title>'
               . '<style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f5f6f7;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;}'
               . '.c{text-align:center;max-width:400px}.i{width:72px;height:72px;background:#d63638;color:#fff;border-radius:50%;font-size:36px;display:flex;align-items:center;justify-content:center;margin:0 auto 20px}'
               . 'h1{font-size:1.5rem;margin-bottom:8px;color:#1d2327}p{color:#646970;font-size:15px;line-height:1.5}</style></head>'
               . '<body><div class="c"><div class="i">&times;</div><h1>Payment cancelled</h1><p>No charge was made. Please ask your driver if you need to try again.</p></div></body></html>';
            exit;
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        require_once HP_BOOKING_PLUGIN_DIR . 'includes/class-database.php';
        HP_Booking_Database::create_tables();
        HP_Booking_Database::insert_default_data();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up if needed
        flush_rewrite_rules();
    }
    
    /**
     * Load plugin files
     */
    public function load_plugin() {
        // Load core classes
        $this->load_dependencies();
        HP_Booking_Database::maybe_migrate();

        // Initialize components
        if (is_admin()) {
            $this->load_admin();
        }
        
        $this->load_public();
    }
    
    /**
     * Load dependencies
     */
    private function load_dependencies() {
        require_once HP_BOOKING_PLUGIN_DIR . 'includes/class-database.php';
        require_once HP_BOOKING_PLUGIN_DIR . 'includes/class-email-template.php';
        require_once HP_BOOKING_PLUGIN_DIR . 'includes/class-pdf-receipt.php';
        require_once HP_BOOKING_PLUGIN_DIR . 'includes/class-api-endpoints.php';
        require_once HP_BOOKING_PLUGIN_DIR . 'includes/class-google-maps.php';
        require_once HP_BOOKING_PLUGIN_DIR . 'includes/class-stripe-integration.php';
        require_once HP_BOOKING_PLUGIN_DIR . 'includes/class-twilio-integration.php';
        require_once HP_BOOKING_PLUGIN_DIR . 'includes/class-booking-validator.php';
        require_once HP_BOOKING_PLUGIN_DIR . 'includes/class-availability-manager.php';
        require_once HP_BOOKING_PLUGIN_DIR . 'includes/class-maintenance-popup.php';
        require_once HP_BOOKING_PLUGIN_DIR . 'includes/class-operating-hours-gate.php';

        // Initialize core classes
        HP_Booking_Database::get_instance();
        HP_Booking_API_Endpoints::get_instance();
        HP_Booking_Google_Maps::get_instance();
        HP_Booking_Stripe_Integration::get_instance();
        HP_Booking_Twilio_Integration::get_instance();
        HP_Booking_Validator::get_instance();
        HP_Booking_Availability_Manager::get_instance();
        HP_Booking_Maintenance_Popup::get_instance();
        HP_Booking_Operating_Hours_Gate::get_instance();
    }
    
    /**
     * Load admin components
     */
    private function load_admin() {
        require_once HP_BOOKING_PLUGIN_DIR . 'includes/class-admin-dashboard.php';
        require_once HP_BOOKING_PLUGIN_DIR . 'admin/class-admin-menu.php';
        
        HP_Booking_Admin_Dashboard::get_instance();
        HP_Booking_Admin_Menu::get_instance();
    }
    
    /**
     * Load public components
     */
    private function load_public() {
        require_once HP_BOOKING_PLUGIN_DIR . 'public/shortcodes/booking-form.php';
        
        HP_Booking_Shortcode::get_instance();
    }
    
    /**
     * Load text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'handsome-pete-booking',
            false,
            dirname(HP_BOOKING_PLUGIN_BASENAME) . '/languages'
        );
    }
}

/**
 * Initialize plugin
 */
function hp_booking_init() {
    return Handsome_Pete_Booking::get_instance();
}

// Start the plugin
hp_booking_init();
