<?php
/**
 * Booking validator class
 * Handles validation of addresses, service areas, and booking data
 */

if (!defined('ABSPATH')) {
    exit;
}

class HP_Booking_Validator {
    
    private static $instance = null;
    private $db;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->db = HP_Booking_Database::get_instance();
    }
    
    /**
     * Validate if coordinates are within service area
     */
    public function is_in_service_area($lat, $lng) {
        global $wpdb;
        $table = $this->db->get_table('service_areas');
        
        $areas = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE is_active = 1"
        );
        
        foreach ($areas as $area) {
            if ($lat >= $area->bounds_southwest_lat &&
                $lat <= $area->bounds_northeast_lat &&
                $lng >= $area->bounds_southwest_lng &&
                $lng <= $area->bounds_northeast_lng) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Validate email address
     */
    public function validate_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate phone number (basic validation)
     */
    public function validate_phone($phone) {
        // Remove all non-digit characters
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);
        // Check if it's at least 10 digits
        return strlen($cleaned) >= 10;
    }
    
    /**
     * Validate booking data
     */
    public function validate_booking_data($data) {
        $errors = array();
        
        // Required fields
        if (empty($data['customer_name'])) {
            $errors[] = 'Customer name is required';
        }
        
        if (empty($data['customer_email'])) {
            $errors[] = 'Customer email is required';
        } elseif (!$this->validate_email($data['customer_email'])) {
            $errors[] = 'Invalid email address';
        }
        
        if (empty($data['customer_phone'])) {
            $errors[] = 'Customer phone is required';
        } elseif (!$this->validate_phone($data['customer_phone'])) {
            $errors[] = 'Invalid phone number';
        }
        
        if (empty($data['pickup_address']) || empty($data['pickup_lat']) || empty($data['pickup_lng'])) {
            $errors[] = 'Valid pickup address is required';
        } elseif (!$this->is_in_service_area($data['pickup_lat'], $data['pickup_lng'])) {
            $errors[] = 'Pickup address must be in Grafton, Cobourg, Port Hope, or Brighton';
        }
        
        if (empty($data['destination_address']) || empty($data['destination_lat']) || empty($data['destination_lng'])) {
            $errors[] = 'Valid destination address is required';
        } elseif (!$this->is_in_service_area($data['destination_lat'], $data['destination_lng'])) {
            $errors[] = 'Destination address must be in Grafton, Cobourg, Port Hope, or Brighton';
        }
        
        if (empty($data['pickup_datetime'])) {
            $errors[] = 'Pickup date and time is required';
        } else {
            $pickup_time = strtotime($data['pickup_datetime']);
            $min_notice = $this->db->get_setting('minimum_notice_hours', 2) * 3600;
            
            if ($pickup_time < time() + $min_notice) {
                $errors[] = sprintf(
                    'Bookings must be made at least %d hours in advance',
                    $this->db->get_setting('minimum_notice_hours', 2)
                );
            }
        }
        
        return $errors;
    }
}
