<?php
/**
 * Admin menu class
 * Creates admin menu pages
 */

if (!defined('ABSPATH')) {
    exit;
}

class HP_Booking_Admin_Menu {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            'Handsome Pete Booking',
            'Bookings',
            'manage_options',
            'hp-booking',
            array($this, 'render_dashboard'),
            'dashicons-calendar-alt',
            30
        );
        
        // Quick Start (shown first time)
        add_submenu_page(
            'hp-booking',
            'Quick Start',
            'Quick Start',
            'manage_options',
            'hp-booking-quick-start',
            array($this, 'render_quick_start')
        );
        
        // Dashboard submenu
        add_submenu_page(
            'hp-booking',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'hp-booking',
            array($this, 'render_dashboard')
        );
        
        // Bookings list
        add_submenu_page(
            'hp-booking',
            'All Bookings',
            'All Bookings',
            'manage_options',
            'hp-booking-list',
            array($this, 'render_bookings_list')
        );
        
        // Availability
        add_submenu_page(
            'hp-booking',
            'Availability',
            'Availability',
            'manage_options',
            'hp-booking-availability',
            array($this, 'render_availability')
        );

        // Maintenance Popup
        add_submenu_page(
            'hp-booking',
            'Maintenance Popup',
            'Maintenance Popup',
            'manage_options',
            'hp-booking-maintenance-popup',
            array($this, 'render_maintenance_popup')
        );

        // Settings
        add_submenu_page(
            'hp-booking',
            'Settings',
            'Settings',
            'manage_options',
            'hp-booking-settings',
            array($this, 'render_settings')
        );
        
        // Help/Documentation
        add_submenu_page(
            'hp-booking',
            'Help & Documentation',
            'Help',
            'manage_options',
            'hp-booking-help',
            array($this, 'render_help')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        $settings = array(
            'price_per_km',
            'pricing_tier2_km',
            'pricing_tier2_rate',
            'pricing_tier3_km',
            'pricing_tier3_rate',
            'buffer_minutes',
            'minimum_notice_hours',
            'time_slot_interval',
            'stripe_test_mode',
            'stripe_publishable_key',
            'stripe_secret_key',
            'stripe_webhook_secret',
            'stripe_publishable_key_test',
            'stripe_secret_key_test',
            'stripe_webhook_secret_test',
            'twilio_account_sid',
            'twilio_auth_token',
            'twilio_phone_number',
            'google_maps_api_key',
            'driver_phone_number',
            'driver_email'
        );
        
        foreach ($settings as $setting) {
            register_setting('hp_booking_settings', $setting);
        }
    }
    
    /**
     * Render quick start page
     */
    public function render_quick_start() {
        include HP_BOOKING_PLUGIN_DIR . 'admin/admin-quick-start.php';
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        include HP_BOOKING_PLUGIN_DIR . 'admin/admin-dashboard.php';
    }
    
    /**
     * Render bookings list page
     */
    public function render_bookings_list() {
        include HP_BOOKING_PLUGIN_DIR . 'admin/admin-bookings-list.php';
    }
    
    /**
     * Render availability page
     */
    public function render_availability() {
        include HP_BOOKING_PLUGIN_DIR . 'admin/admin-availability.php';
    }

    /**
     * Render maintenance popup settings page
     */
    public function render_maintenance_popup() {
        include HP_BOOKING_PLUGIN_DIR . 'admin/admin-maintenance-popup.php';
    }

    /**
     * Render settings page
     */
    public function render_settings() {
        include HP_BOOKING_PLUGIN_DIR . 'admin/admin-settings.php';
    }
    
    /**
     * Render help/documentation page
     */
    public function render_help() {
        include HP_BOOKING_PLUGIN_DIR . 'admin/admin-help.php';
    }
}
