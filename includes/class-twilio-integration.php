<?php
/**
 * Twilio integration class
 * Handles SMS notifications
 */

if (!defined('ABSPATH')) {
    exit;
}

class HP_Booking_Twilio_Integration {
    
    private static $instance = null;
    private $db;
    private $account_sid;
    private $auth_token;
    private $phone_number;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->db = HP_Booking_Database::get_instance();
        $this->account_sid = $this->db->get_setting('twilio_account_sid');
        $this->auth_token = $this->db->get_setting('twilio_auth_token');
        $this->phone_number = $this->db->get_setting('twilio_phone_number');
    }
    
    /**
     * Send SMS message
     */
    public function send_sms($to, $message) {
        if (empty($this->account_sid) || empty($this->auth_token) || empty($this->phone_number)) {
            return new WP_Error('no_credentials', 'Twilio credentials not configured');
        }
        
        // Use Twilio SDK if available, otherwise use REST API directly
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->account_sid}/Messages.json";
        
        $body = array(
            'From' => $this->phone_number,
            'To' => $to,
            'Body' => $message
        );
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->account_sid . ':' . $this->auth_token)
            ),
            'body' => $body
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200 && $response_code !== 201) {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            
            $message = 'Unknown error';
            if (isset($error_data['message'])) {
                $message = $error_data['message'];
            } elseif (isset($error_data['detail'])) {
                $message = $error_data['detail'];
            }
            
            // Check for specific error codes to give better advice
            if (isset($error_data['code'])) {
                if ($error_data['code'] == 21266) {
                    $message = "You cannot send a test SMS to the same number that is sending it. Please enter a different mobile number (like your personal cell).";
                } elseif ($error_data['code'] == 21211) {
                    $message = "The phone number is invalid. Please ensure it follows the format +1234567890.";
                } elseif ($error_data['code'] == 21608) {
                    $message = "This is a trial account and can only send to verified numbers. Please verify this number in your Twilio Console.";
                }
            }
            
            return new WP_Error('twilio_error', 'Twilio Error: ' . $message);
        }
        
        return true;
    }
    
    /**
     * Send booking confirmation to customer
     */
    public function send_customer_confirmation($booking) {
        $message = sprintf(
            "Your ride is booked with Handsome Pete!\n\n" .
            "Reference: %s\n" .
            "Pickup: %s\n" .
            "Destination: %s\n" .
            "Date & Time: %s\n" .
            "Distance: %.2f km\n" .
            "Price: $%.2f\n\n" .
            "See you soon!",
            $booking->booking_reference,
            $booking->pickup_address,
            $booking->destination_address,
            date('M j, Y g:i A', strtotime($booking->pickup_datetime)),
            $booking->distance_km,
            $booking->price
        );
        
        return $this->send_sms($booking->customer_phone, $message);
    }
    
    /**
     * Send new booking notification to driver
     */
    public function send_driver_notification($booking) {
        $driver_phone = $this->db->get_setting('driver_phone_number');
        
        if (empty($driver_phone)) {
            return new WP_Error('no_driver_phone', 'Driver phone number not configured');
        }
        
        $google_maps = HP_Booking_Google_Maps::get_instance();
        $nav_url = $google_maps->get_navigation_url(
            $booking->pickup_address,
            $booking->destination_address,
            $booking->pickup_lat,
            $booking->pickup_lng,
            $booking->destination_lat,
            $booking->destination_lng
        );
        
        $notes_line = !empty($booking->notes) ? "\nNotes: " . $booking->notes : '';

        $message = sprintf(
            "New Booking: %s\n" .
            "Phone: %s\n" .
            "Pickup: %s\n" .
            "Destination: %s\n" .
            "Time: %s\n" .
            "Distance: %.2f km | Price: $%.2f%s\n" .
            "Map: %s",
            $booking->customer_name,
            $booking->customer_phone,
            $booking->pickup_address,
            $booking->destination_address,
            date('M j, Y g:i A', strtotime($booking->pickup_datetime)),
            $booking->distance_km,
            $booking->price,
            $notes_line,
            $nav_url
        );
        
        return $this->send_sms($driver_phone, $message);
    }
    
    /**
     * Schedule reminder SMS (customer: 2 hours before ride; driver: 1 hour before).
     * Uses the WordPress site timezone so reminders fire at the correct local time
     * (e.g. 8:30 AM local for a 10:30 AM ride), not server time.
     */
    public function schedule_reminder($booking, $hours_before = 2) {
        $tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
        $pickup = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $booking->pickup_datetime, $tz);
        if (!$pickup) {
            return;
        }
        $pickup_ts = $pickup->getTimestamp();
        $reminder_time = $pickup_ts - ($hours_before * 3600);

        if ($reminder_time <= time()) {
            return; // Too late to schedule
        }

        // Schedule via WordPress cron (timestamp is UTC; WP cron uses it correctly)
        $args = array(
            'booking_id' => $booking->id,
            'type' => 'customer_reminder'
        );

        wp_schedule_single_event($reminder_time, 'hp_booking_send_reminder', array($args));

        // Also schedule driver reminder (1 hour before)
        if ($hours_before >= 1) {
            $driver_reminder_time = $pickup_ts - 3600;
            $driver_args = array(
                'booking_id' => $booking->id,
                'type' => 'driver_reminder'
            );
            wp_schedule_single_event($driver_reminder_time, 'hp_booking_send_reminder', array($driver_args));
        }
    }
}

// Hook for scheduled reminders
add_action('hp_booking_send_reminder', function($args) {
    global $wpdb;
    $bookings_table = HP_Booking_Database::get_instance()->get_table('bookings');
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$bookings_table} WHERE id = %d",
        $args['booking_id']
    ));
    
    if ($booking && $booking->booking_status !== 'cancelled') {
        $twilio = HP_Booking_Twilio_Integration::get_instance();
        
        if ($args['type'] === 'customer_reminder') {
            $tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
            $pickup_dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $booking->pickup_datetime, $tz);
            $ride_time = $pickup_dt ? (function_exists('wp_date') ? wp_date('g:i A', $pickup_dt->getTimestamp()) : $pickup_dt->format('g:i A')) : date('g:i A', strtotime($booking->pickup_datetime));
            $message = sprintf(
                "Reminder: Your ride is scheduled for %s\n" .
                "Pickup: %s\n" .
                "Destination: %s\n" .
                "Reference: %s",
                $ride_time,
                $booking->pickup_address,
                $booking->destination_address,
                $booking->booking_reference
            );
            $twilio->send_sms($booking->customer_phone, $message);
        } elseif ($args['type'] === 'driver_reminder') {
            $driver_phone = HP_Booking_Database::get_instance()->get_setting('driver_phone_number');
            if ($driver_phone) {
                $google_maps = HP_Booking_Google_Maps::get_instance();
                $nav_url = $google_maps->get_navigation_url(
                    $booking->pickup_address,
                    $booking->destination_address,
                    $booking->pickup_lat,
                    $booking->pickup_lng,
                    $booking->destination_lat,
                    $booking->destination_lng
                );
                $message = sprintf(
                    "Reminder: You have a ride in 1 hour\n" .
                    "Customer: %s (%s)\n" .
                    "Pickup: %s\n" .
                    "Map: %s",
                    $booking->customer_name,
                    $booking->customer_phone,
                    $booking->pickup_address,
                    $nav_url
                );
                $twilio->send_sms($driver_phone, $message);
            }
        }
    }
});
