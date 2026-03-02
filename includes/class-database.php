<?php
/**
 * Database management class
 * Handles table creation, data insertion, and database operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class HP_Booking_Database {
    
    private static $instance = null;
    private $wpdb;
    private $table_prefix;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = $wpdb->prefix . 'hp_';
    }
    
    /**
     * Create all database tables
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'hp_';
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Bookings table
        $sql_bookings = "CREATE TABLE IF NOT EXISTS {$prefix}bookings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_reference VARCHAR(20) NOT NULL,
            customer_name VARCHAR(100) NOT NULL,
            customer_email VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(20) NOT NULL,
            pickup_address TEXT NOT NULL,
            pickup_lat DECIMAL(10, 8) NOT NULL,
            pickup_lng DECIMAL(11, 8) NOT NULL,
            destination_address TEXT NOT NULL,
            destination_lat DECIMAL(10, 8) NOT NULL,
            destination_lng DECIMAL(11, 8) NOT NULL,
            distance_km DECIMAL(8, 2) NOT NULL,
            estimated_duration_minutes INT NOT NULL,
            price DECIMAL(10, 2) NOT NULL,
            pickup_datetime DATETIME NOT NULL,
            end_datetime DATETIME NOT NULL,
            payment_status ENUM('pending', 'paid_online', 'paid_tap', 'paid_cash', 'cancelled', 'refunded') DEFAULT 'pending',
            payment_method ENUM('online', 'tap_to_pay', 'none') DEFAULT 'none',
            stripe_payment_intent_id VARCHAR(255) NULL,
            stripe_terminal_payment_id VARCHAR(255) NULL,
            booking_status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY booking_reference (booking_reference),
            KEY idx_pickup_datetime (pickup_datetime),
            KEY idx_end_datetime (end_datetime),
            KEY idx_booking_status (booking_status),
            KEY idx_payment_status (payment_status)
        ) $charset_collate;";
        
        dbDelta($sql_bookings);
        
        // Availability blocks table
        $sql_blocks = "CREATE TABLE IF NOT EXISTS {$prefix}availability_blocks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            block_type ENUM('unavailable', 'maintenance', 'personal') NOT NULL,
            start_datetime DATETIME NOT NULL,
            end_datetime DATETIME NOT NULL,
            reason VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_datetime_range (start_datetime, end_datetime)
        ) $charset_collate;";
        
        dbDelta($sql_blocks);
        
        // Availability extensions: unblocked times outside default 08:00-20:00
        $sql_extensions = "CREATE TABLE IF NOT EXISTS {$prefix}availability_extensions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_date (date)
        ) $charset_collate;";
        dbDelta($sql_extensions);
        
        // Service areas table
        $sql_areas = "CREATE TABLE IF NOT EXISTS {$prefix}service_areas (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            city_name VARCHAR(100) NOT NULL,
            bounds_northeast_lat DECIMAL(10, 8) NOT NULL,
            bounds_northeast_lng DECIMAL(11, 8) NOT NULL,
            bounds_southwest_lat DECIMAL(10, 8) NOT NULL,
            bounds_southwest_lng DECIMAL(11, 8) NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        dbDelta($sql_areas);
        
        // Settings table
        $sql_settings = "CREATE TABLE IF NOT EXISTS {$prefix}settings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";
        
        dbDelta($sql_settings);
    }
    
    /**
     * Insert default data
     */
    public static function insert_default_data() {
        global $wpdb;
        $prefix = $wpdb->prefix . 'hp_';
        
        // Insert service area bounds for Grafton, Cobourg, Port Hope, Brighton
        $service_areas = array(
            array(
                'city_name' => 'Grafton',
                'bounds_northeast_lat' => 43.990000,
                'bounds_northeast_lng' => -77.900000,
                'bounds_southwest_lat' => 43.960000,
                'bounds_southwest_lng' => -77.950000,
            ),
            array(
                'city_name' => 'Cobourg',
                'bounds_northeast_lat' => 43.970000,
                'bounds_northeast_lng' => -78.120000,
                'bounds_southwest_lat' => 43.930000,
                'bounds_southwest_lng' => -78.190000,
            ),
            array(
                'city_name' => 'Port Hope',
                'bounds_northeast_lat' => 43.960000,
                'bounds_northeast_lng' => -78.280000,
                'bounds_southwest_lat' => 43.920000,
                'bounds_southwest_lng' => -78.340000,
            ),
            array(
                'city_name' => 'Brighton',
                'bounds_northeast_lat' => 44.030000,
                'bounds_northeast_lng' => -77.730000,
                'bounds_southwest_lat' => 44.000000,
                'bounds_southwest_lng' => -77.780000,
            ),
        );
        
        foreach ($service_areas as $area) {
            $wpdb->replace(
                $prefix . 'service_areas',
                $area,
                array('%s', '%f', '%f', '%f', '%f')
            );
        }
        
        // Insert default settings
        $default_settings = array(
            'price_per_km' => '1.75',
            'buffer_minutes' => '30',
            'minimum_notice_hours' => '2',
            'time_slot_interval' => '15',
            'working_hours_start' => '08:00',
            'working_hours_end' => '22:00',
        );
        
        foreach ($default_settings as $key => $value) {
            $wpdb->replace(
                $prefix . 'settings',
                array(
                    'setting_key' => $key,
                    'setting_value' => $value
                ),
                array('%s', '%s')
            );
        }
    }
    
    /**
     * Run migrations when plugin version increases.
     */
    public static function maybe_migrate() {
        global $wpdb;
        $current = get_option('hp_booking_db_version', '0');
        $prefix = $wpdb->prefix . 'hp_';
        
        // 1.5.9: default working_hours_end 20:00 -> 22:00 (10 PM)
        if (version_compare($current, '1.5.9', '<')) {
            $db = self::get_instance();
            $end = trim((string) $db->get_setting('working_hours_end', ''));
            if ($end === '20:00' || $end === '') {
                $db->update_setting('working_hours_end', '22:00');
            }
            update_option('hp_booking_db_version', '1.5.9');
            $current = '1.5.9';
        }

        // 1.5.8: availability_extensions table for 24h support
        if (version_compare($current, '1.5.8', '<')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            $sql = "CREATE TABLE IF NOT EXISTS {$prefix}availability_extensions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                date DATE NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_date (date)
            ) " . $wpdb->get_charset_collate() . ";";
            dbDelta($sql);
            update_option('hp_booking_db_version', HP_BOOKING_VERSION);
            $current = HP_BOOKING_VERSION;
        }
        
        if (version_compare($current, '1.4.0', '>=')) {
            return;
        }
        $table = $wpdb->prefix . 'hp_' . 'bookings';
        $col = $wpdb->get_row("SHOW COLUMNS FROM {$table} LIKE 'payment_status'");
        if (!$col) {
            return;
        }
        if (strpos($col->Type, 'paid_cash') !== false) {
            update_option('hp_booking_db_version', HP_BOOKING_VERSION);
            return;
        }
        $wpdb->query("ALTER TABLE {$table} MODIFY COLUMN payment_status ENUM('pending', 'paid_online', 'paid_tap', 'paid_cash', 'cancelled', 'refunded') DEFAULT 'pending'");
        update_option('hp_booking_db_version', HP_BOOKING_VERSION);
    }

    /**
     * Get table name
     */
    public function get_table($table) {
        return $this->table_prefix . $table;
    }
    
    /**
     * Get setting value
     */
    public function get_setting($key, $default = '') {
        $table = $this->get_table('settings');
        $result = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT setting_value FROM {$table} WHERE setting_key = %s",
            $key
        ));
        return $result !== null ? $result : $default;
    }
    
    /**
     * Update setting value
     */
    public function update_setting($key, $value) {
        $table = $this->get_table('settings');
        return $this->wpdb->replace(
            $table,
            array(
                'setting_key' => $key,
                'setting_value' => $value
            ),
            array('%s', '%s')
        );
    }
    
    /**
     * Generate unique booking reference
     */
    public function generate_booking_reference() {
        $year = date('Y');
        $table = $this->get_table('bookings');
        
        // Get last booking number for this year
        $last_ref = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT booking_reference FROM {$table} 
            WHERE booking_reference LIKE %s 
            ORDER BY id DESC LIMIT 1",
            'HP-' . $year . '-%'
        ));
        
        if ($last_ref) {
            // Extract number and increment
            $parts = explode('-', $last_ref);
            $number = intval($parts[2]) + 1;
        } else {
            $number = 1;
        }
        
        return 'HP-' . $year . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
