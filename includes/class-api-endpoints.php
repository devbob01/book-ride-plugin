<?php
/**
 * REST API endpoints class
 * Handles all REST API endpoints for booking system
 */

if (!defined('ABSPATH')) {
    exit;
}

class HP_Booking_API_Endpoints {
    
    private static $instance = null;
    private $namespace = 'hp-booking/v1';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Public endpoints
        register_rest_route($this->namespace, '/operating-status', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_operating_status'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route($this->namespace, '/availability', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_availability'),
            'permission_callback' => '__return_true',
            'args' => array(
                'date' => array(
                    'required' => true,
                    'type' => 'string',
                    'validate_callback' => array($this, 'validate_date')
                ),
                'duration' => array(
                    'required' => false,
                    'type' => 'integer'
                )
            )
        ));
        
        register_rest_route($this->namespace, '/calculate-price', array(
            'methods' => 'POST',
            'callback' => array($this, 'calculate_price'),
            'permission_callback' => '__return_true',
            'args' => array(
                'pickup_address' => array('required' => true, 'type' => 'string'),
                'destination_address' => array('required' => true, 'type' => 'string'),
                'pickup_lat' => array('required' => false, 'type' => 'number'),
                'pickup_lng' => array('required' => false, 'type' => 'number'),
                'destination_lat' => array('required' => false, 'type' => 'number'),
                'destination_lng' => array('required' => false, 'type' => 'number'),
            )
        ));
        
        register_rest_route($this->namespace, '/validate-address', array(
            'methods' => 'POST',
            'callback' => array($this, 'validate_address'),
            'permission_callback' => '__return_true',
            'args' => array(
                'address' => array('required' => true, 'type' => 'string'),
                'type' => array('required' => false, 'type' => 'string')
            )
        ));
        
        register_rest_route($this->namespace, '/create-booking', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_booking'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route($this->namespace, '/confirm-payment', array(
            'methods' => 'POST',
            'callback' => array($this, 'confirm_payment'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route($this->namespace, '/stripe-webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'stripe_webhook'),
            'permission_callback' => '__return_true'
        ));
        
        // Admin endpoints
        register_rest_route($this->namespace, '/admin/bookings', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_bookings'),
            'permission_callback' => array($this, 'check_admin_permission')
        ));
        
        register_rest_route($this->namespace, '/admin/booking/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_booking'),
            'permission_callback' => array($this, 'check_admin_permission'),
            'args' => array(
                'id' => array('required' => true, 'type' => 'integer')
            )
        ));
        
        register_rest_route($this->namespace, '/admin/booking/(?P<id>\d+)/cancel', array(
            'methods' => 'POST',
            'callback' => array($this, 'cancel_booking'),
            'permission_callback' => array($this, 'check_admin_permission'),
            'args' => array(
                'id' => array('required' => true, 'type' => 'integer')
            )
        ));
        
        register_rest_route($this->namespace, '/admin/booking/(?P<id>\d+)/complete', array(
            'methods' => 'POST',
            'callback' => array($this, 'complete_booking'),
            'permission_callback' => array($this, 'check_admin_permission'),
            'args' => array(
                'id' => array('required' => true, 'type' => 'integer')
            )
        ));
        
        register_rest_route($this->namespace, '/admin/availability/block', array(
            'methods' => 'POST',
            'callback' => array($this, 'block_availability'),
            'permission_callback' => array($this, 'check_admin_permission')
        ));
        
        register_rest_route($this->namespace, '/admin/availability/block/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'unblock_availability'),
            'permission_callback' => array($this, 'check_admin_permission'),
            'args' => array(
                'id' => array('required' => true, 'type' => 'integer')
            )
        ));
        
        register_rest_route($this->namespace, '/admin/availability/blocks', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_availability_blocks'),
            'permission_callback' => array($this, 'check_admin_permission')
        ));
        
        register_rest_route($this->namespace, '/admin/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_stats'),
            'permission_callback' => array($this, 'check_admin_permission')
        ));
    }
    
    /**
     * Get available time slots for a date.
     * Normalizes date to Y-m-d and returns only slots not taken by confirmed/pending bookings or blocks.
     */
    public function get_availability($request) {
        $date_raw = $request->get_param('date');
        $duration = $request->get_param('duration'); // in minutes
        
        $date = $this->normalize_date($date_raw);
        if (!$date) {
            return new WP_Error('invalid_date', 'Invalid date format. Use YYYY-MM-DD.', array('status' => 400));
        }

        $availability_manager = HP_Booking_Availability_Manager::get_instance();
        $slots = $availability_manager->get_available_slots($date, $duration);
        $blocked = $availability_manager->get_blocked_times($date);

        $db = HP_Booking_Database::get_instance();
        $time_slot_interval = intval($db->get_setting('time_slot_interval', 15));
        if ($time_slot_interval < 1) $time_slot_interval = 15;

        $all_slots = array();
        for ($time = strtotime($date . ' 00:00'); $time < strtotime($date . ' 24:00'); $time += ($time_slot_interval * 60)) {
            $all_slots[] = date('H:i', $time);
        }

        return rest_ensure_response(array(
            'date' => $date,
            'available_slots' => $slots,
            'all_slots' => $all_slots,
            'blocked_times' => $blocked
        ));
    }

    /**
     * Get whether we are currently operating (for #book-ride gate).
     * Returns operating status plus popup message and phone for non-operating hours.
     */
    public function get_operating_status($request) {
        $availability_manager = HP_Booking_Availability_Manager::get_instance();
        $operating = $availability_manager->is_operating_now();

        $db = HP_Booking_Database::get_instance();
        $start = $db->get_setting('working_hours_start', '08:00');
        $end = $db->get_setting('working_hours_end', '22:00');
        $phone = trim((string) $db->get_setting('driver_phone_number', ''));
        if ($phone === '') {
            $phone = '905-746-7547';
        }
        $start_fmt = date('g:iA', strtotime('2000-01-01 ' . $start));
        $end_fmt = date('g:iA', strtotime('2000-01-01 ' . $end));
        $message = sprintf(
            'Normal operating hours is daily from %s to %s. Please call %s to speak with Handsome Pete directly for bookings outside these hours.',
            $start_fmt,
            $end_fmt,
            $phone
        );

        return rest_ensure_response(array(
            'operating' => $operating,
            'message' => $message,
            'phone' => $phone,
        ));
    }

    /**
     * Normalize date param to Y-m-d for DB and strtotime.
     */
    private function normalize_date($date) {
        if (empty($date) || !is_string($date)) {
            return null;
        }
        $date = trim($date);
        $ts = strtotime($date);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d', $ts);
    }
    
    /**
     * Calculate price based on distance using tiered rates (if configured).
     * Tiers: 0–tier2_km at price_per_km; tier2_km–tier3_km at tier2_rate; above tier3_km at tier3_rate.
     *
     * @param float $distance_km
     * @return float
     */
    private function calculate_price_from_distance($distance_km) {
        $db = HP_Booking_Database::get_instance();
        $price_per_km = floatval($db->get_setting('price_per_km', 1.75));
        $tier2_km = floatval($db->get_setting('pricing_tier2_km', 0));
        $tier2_rate = floatval($db->get_setting('pricing_tier2_rate', $price_per_km));
        $tier3_km = floatval($db->get_setting('pricing_tier3_km', 0));
        $tier3_rate = floatval($db->get_setting('pricing_tier3_rate', $tier2_rate));

        if ($tier2_km <= 0) {
            return round($distance_km * $price_per_km, 2);
        }

        $km = max(0, (float) $distance_km);
        $segment1 = min($km, $tier2_km);
        $price = $segment1 * $price_per_km;
        $km -= $segment1;

        if ($tier3_km <= $tier2_km || $km <= 0) {
            return round($price + $km * $tier2_rate, 2);
        }

        $segment2_max = $tier3_km - $tier2_km;
        $segment2 = min($km, $segment2_max);
        $price += $segment2 * $tier2_rate;
        $km -= $segment2;
        $price += $km * $tier3_rate;

        return round($price, 2);
    }

    /**
     * Calculate price based on distance
     */
    public function calculate_price($request) {
        $pickup_address = $request->get_param('pickup_address');
        $destination_address = $request->get_param('destination_address');
        $pickup_lat = $request->get_param('pickup_lat');
        $pickup_lng = $request->get_param('pickup_lng');
        $dest_lat = $request->get_param('destination_lat');
        $dest_lng = $request->get_param('destination_lng');
        
        $google_maps = HP_Booking_Google_Maps::get_instance();
        $distance_data = $google_maps->calculate_distance(
            $pickup_address,
            $destination_address,
            $pickup_lat,
            $pickup_lng,
            $dest_lat,
            $dest_lng
        );
        
        if (is_wp_error($distance_data)) {
            return $distance_data;
        }
        
        $price = $this->calculate_price_from_distance($distance_data['distance_km']);
        
        return rest_ensure_response(array(
            'distance_km' => $distance_data['distance_km'],
            'duration_minutes' => $distance_data['duration_minutes'],
            'price' => $price,
            'pickup_coords' => array(
                'lat' => $pickup_lat,
                'lng' => $pickup_lng
            ),
            'destination_coords' => array(
                'lat' => $dest_lat,
                'lng' => $dest_lng
            )
        ));
    }
    
    /**
     * Validate address and check service area
     */
    public function validate_address($request) {
        $address = $request->get_param('address');
        
        $google_maps = HP_Booking_Google_Maps::get_instance();
        $geocode_data = $google_maps->geocode_address($address);
        
        if (is_wp_error($geocode_data)) {
            return rest_ensure_response(array(
                'valid' => false,
                'in_service_area' => false,
                'error' => $geocode_data->get_error_message()
            ));
        }
        
        $validator = HP_Booking_Validator::get_instance();
        $in_service_area = $validator->is_in_service_area($geocode_data['lat'], $geocode_data['lng']);
        
        // Extract city name from formatted address
        $city = '';
        if (preg_match('/(Grafton|Cobourg|Port Hope|Brighton)/i', $geocode_data['formatted_address'], $matches)) {
            $city = $matches[1];
        }
        
        return rest_ensure_response(array(
            'valid' => true,
            'in_service_area' => $in_service_area,
            'formatted_address' => $geocode_data['formatted_address'],
            'coordinates' => array(
                'lat' => $geocode_data['lat'],
                'lng' => $geocode_data['lng']
            ),
            'city' => $city
        ));
    }
    
    /**
     * Create new booking
     */
    public function create_booking($request) {
        global $wpdb;

        try {
            $data = $request->get_json_params();
            if (empty($data)) {
                return new WP_Error('invalid_json', 'Invalid or empty request body', array('status' => 400));
            }

            // Validate booking data
            $validator = HP_Booking_Validator::get_instance();
            $errors = $validator->validate_booking_data($data);
            if (!empty($errors)) {
                return new WP_Error('validation_error', implode(', ', $errors), array('status' => 400));
            }

            // Calculate distance and price
            $google_maps = HP_Booking_Google_Maps::get_instance();
            $distance_data = $google_maps->calculate_distance(
                $data['pickup_address'],
                $data['destination_address'],
                isset($data['pickup_lat']) ? $data['pickup_lat'] : null,
                isset($data['pickup_lng']) ? $data['pickup_lng'] : null,
                isset($data['destination_lat']) ? $data['destination_lat'] : null,
                isset($data['destination_lng']) ? $data['destination_lng'] : null
            );
            if (is_wp_error($distance_data)) {
                return $distance_data;
            }

            $db = HP_Booking_Database::get_instance();
            $buffer_minutes = intval($db->get_setting('buffer_minutes', 30));
            $price = $this->calculate_price_from_distance($distance_data['distance_km']);
            $end_datetime = date('Y-m-d H:i:s', strtotime($data['pickup_datetime'] . " +{$distance_data['duration_minutes']} minutes +{$buffer_minutes} minutes"));

            // Check availability
            $availability_manager = HP_Booking_Availability_Manager::get_instance();
            if (!$availability_manager->is_time_available($data['pickup_datetime'], $distance_data['duration_minutes'])) {
                return new WP_Error('time_unavailable', 'Selected time slot is no longer available', array('status' => 409));
            }

            $booking_reference = $db->generate_booking_reference();
            $booking_data = array(
                'booking_reference' => $booking_reference,
                'customer_name' => sanitize_text_field($data['customer_name']),
                'customer_email' => sanitize_email($data['customer_email']),
                'customer_phone' => sanitize_text_field($data['customer_phone']),
                'pickup_address' => sanitize_textarea_field($data['pickup_address']),
                'pickup_lat' => floatval($data['pickup_lat']),
                'pickup_lng' => floatval($data['pickup_lng']),
                'destination_address' => sanitize_textarea_field($data['destination_address']),
                'destination_lat' => floatval($data['destination_lat']),
                'destination_lng' => floatval($data['destination_lng']),
                'distance_km' => $distance_data['distance_km'],
                'estimated_duration_minutes' => $distance_data['duration_minutes'],
                'price' => $price,
                'pickup_datetime' => sanitize_text_field($data['pickup_datetime']),
                'end_datetime' => $end_datetime,
                'payment_method' => isset($data['payment_method']) ? sanitize_text_field($data['payment_method']) : 'none',
                'payment_status' => (isset($data['payment_method']) && $data['payment_method'] === 'online') ? 'pending' : 'pay_at_pickup',
                'booking_status' => (isset($data['payment_method']) && $data['payment_method'] === 'online') ? 'pending' : 'confirmed',
                'notes' => isset($data['special_instructions']) ? sanitize_textarea_field($data['special_instructions']) : null
            );

            $bookings_table = $db->get_table('bookings');
            $wpdb->query('START TRANSACTION');
            if (!$availability_manager->is_time_available($data['pickup_datetime'], $distance_data['duration_minutes'])) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('time_unavailable', 'Selected time slot is no longer available', array('status' => 409));
            }
            $result = $wpdb->insert($bookings_table, $booking_data);
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('database_error', 'Failed to create booking', array('status' => 500));
            }
            $booking_id = $wpdb->insert_id;
            $wpdb->query('COMMIT');

            $booking = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$bookings_table} WHERE id = %d",
                $booking_id
            ));

            // Create Stripe Payment Intent if pay now (inline card form on page)
            $client_secret = null;
            if (isset($data['payment_method']) && $data['payment_method'] === 'online') {
                $stripe = HP_Booking_Stripe_Integration::get_instance();
                $intent_result = $stripe->create_payment_intent(
                    $price,
                    'cad',
                    array(
                        'booking_reference' => $booking_reference,
                        'customer_email' => $booking->customer_email,
                        'customer_phone' => $booking->customer_phone
                    )
                );
                if (is_wp_error($intent_result)) {
                    $msg = $intent_result->get_error_message();
                    return new WP_Error('stripe_error', $msg ?: 'Payment setup failed', array('status' => 502));
                }
                $client_secret = $intent_result['client_secret'];

                // Store the payment intent ID on the booking
                $wpdb->update(
                    $bookings_table,
                    array('stripe_payment_intent_id' => $intent_result['payment_intent_id']),
                    array('id' => $booking_id),
                    array('%s'),
                    array('%d')
                );
            }

            // Notifications (non-fatal: log errors but don't fail the request)
            $twilio = HP_Booking_Twilio_Integration::get_instance();
            foreach (array('send_customer_confirmation', 'send_driver_notification', 'schedule_reminder') as $method) {
                $res = $twilio->$method($booking);
                if (is_wp_error($res)) {
                    if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
                        error_log('[HP Booking] Twilio ' . $method . ': ' . $res->get_error_message());
                    }
                }
            }

            // Send admin SMS immediately for ALL bookings — notes are in $booking now
            $this->send_admin_sms($booking);

            // Only send confirmation email immediately for non-online payments
            // For online payments, email is sent after payment is confirmed
            if (!isset($data['payment_method']) || $data['payment_method'] !== 'online') {
                $this->send_booking_confirmation_email($booking);
                $this->send_admin_email($booking);
            }

            return rest_ensure_response(array(
                'success' => true,
                'booking_reference' => $booking_reference,
                'booking_id' => $booking_id,
                'client_secret' => $client_secret
            ));
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
                error_log('[HP Booking] create_booking exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
            return new WP_Error('server_error', 'Booking failed: ' . $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Confirm payment after successful Stripe card payment
     * Verifies the payment intent, updates booking status, and sends confirmation email
     */
    public function confirm_payment($request) {
        global $wpdb;

        try {
            $data = $request->get_json_params();
            $payment_intent_id = isset($data['payment_intent_id']) ? sanitize_text_field($data['payment_intent_id']) : '';
            $booking_reference = isset($data['booking_reference']) ? sanitize_text_field($data['booking_reference']) : '';

            if (empty($payment_intent_id) || empty($booking_reference)) {
                return new WP_Error('missing_data', 'Payment intent ID and booking reference are required', array('status' => 400));
            }

            $db = HP_Booking_Database::get_instance();
            $bookings_table = $db->get_table('bookings');

            // Look up booking
            $booking = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$bookings_table} WHERE booking_reference = %s",
                $booking_reference
            ));

            if (!$booking) {
                return new WP_Error('not_found', 'Booking not found', array('status' => 404));
            }

            // Verify the payment intent with Stripe
            $stripe = HP_Booking_Stripe_Integration::get_instance();
            $intent_status = $stripe->retrieve_payment_intent($payment_intent_id);

            if (is_wp_error($intent_status)) {
                return new WP_Error('stripe_error', $intent_status->get_error_message(), array('status' => 502));
            }

            if ($intent_status['status'] !== 'succeeded') {
                return new WP_Error('payment_not_complete', 'Payment has not been completed. Status: ' . $intent_status['status'], array('status' => 400));
            }

            // Update booking to confirmed and paid
            $wpdb->update(
                $bookings_table,
                array(
                    'payment_status' => 'paid_online',
                    'booking_status' => 'confirmed',
                    'stripe_payment_intent_id' => $payment_intent_id
                ),
                array('booking_reference' => $booking_reference),
                array('%s', '%s', '%s'),
                array('%s')
            );

            // Refresh booking data
            $booking = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$bookings_table} WHERE booking_reference = %s",
                $booking_reference
            ));

            // Send confirmation email now that payment is confirmed
            $this->send_booking_confirmation_email($booking);
            $this->send_admin_notifications($booking);

            error_log("[HP Booking] Payment confirmed for booking: {$booking_reference}");

            return rest_ensure_response(array(
                'success' => true,
                'booking_reference' => $booking_reference,
                'status' => 'confirmed'
            ));
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
                error_log('[HP Booking] confirm_payment exception: ' . $e->getMessage());
            }
            return new WP_Error('server_error', 'Payment confirmation failed: ' . $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * Send admin email notification only (no SMS — SMS sent separately during create_booking)
     */
    private function send_admin_email($booking) {
        if (!$booking) return;

        $admin_email = 'devbob.co@gmail.com';
        $subject = 'New Booking Confirmed - ' . $booking->booking_reference;
        $pickup_date = date('l, F j, Y', strtotime($booking->pickup_datetime));
        $pickup_time = date('g:i A', strtotime($booking->pickup_datetime));
        $price = number_format((float)$booking->price, 2);
        $distance = number_format((float)$booking->distance_km, 2);

        $email_message = "<html><body style='font-family: Arial, sans-serif;'>";
        $email_message .= "<h2 style='color: #2D4A3E;'>New Booking Confirmed</h2>";
        $email_message .= "<p><strong>Booking Reference:</strong> {$booking->booking_reference}</p>";
        $email_message .= "<hr style='border: 1px solid #ddd;'>";
        $email_message .= "<h3 style='color: #2D4A3E;'>Customer Details</h3>";
        $email_message .= "<p><strong>Name:</strong> {$booking->customer_name}</p>";
        $email_message .= "<p><strong>Email:</strong> {$booking->customer_email}</p>";
        $email_message .= "<p><strong>Phone:</strong> {$booking->customer_phone}</p>";
        $email_message .= "<hr style='border: 1px solid #ddd;'>";
        $email_message .= "<h3 style='color: #2D4A3E;'>Trip Details</h3>";
        $email_message .= "<p><strong>Date:</strong> {$pickup_date}</p>";
        $email_message .= "<p><strong>Time:</strong> {$pickup_time}</p>";
        $email_message .= "<p><strong>Pickup:</strong> {$booking->pickup_address}</p>";
        $email_message .= "<p><strong>Destination:</strong> {$booking->destination_address}</p>";
        $email_message .= "<p><strong>Distance:</strong> {$distance} km</p>";
        $email_message .= "<p><strong>Price:</strong> \${$price} CAD</p>";
        $email_message .= "<p><strong>Payment Status:</strong> {$booking->payment_status}</p>";
        if (!empty($booking->notes)) {
            $email_message .= "<hr style='border: 1px solid #ddd;'>";
            $email_message .= "<h3 style='color: #2D4A3E;'>Special Instructions</h3>";
            $email_message .= "<p style='background: #f9f9f9; padding: 12px; border-left: 3px solid #C9A227; font-style: italic;'>" . nl2br(esc_html($booking->notes)) . "</p>";
        }
        $email_message .= "<hr style='border: 1px solid #ddd;'>";
        $email_message .= "<p style='color: #666; font-size: 12px;'>This is an automated notification from Handsome Pete Booking System.</p>";
        $email_message .= "</body></html>";

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Handsome Pete Booking <bookings@handsomepete.ca>'
        );
        wp_mail($admin_email, $subject, $email_message, $headers);
    }

    /**
     * Send admin SMS notification only
     */
    private function send_admin_sms($booking) {
        if (!$booking) return;

        $admin_phone = '+1574235350';
        $twilio = HP_Booking_Twilio_Integration::get_instance();
        $pickup_date = date('l, F j, Y', strtotime($booking->pickup_datetime));
        $pickup_time = date('g:i A', strtotime($booking->pickup_datetime));
        $notes_line = !empty($booking->notes) ? "\nNotes: " . $booking->notes : '';

        $sms_message = sprintf(
            "New Booking: %s\n" .
            "Customer: %s (%s)\n" .
            "Pickup: %s at %s\n" .
            "From: %s\n" .
            "To: %s\n" .
            "Price: $%.2f%s",
            $booking->booking_reference,
            $booking->customer_name,
            $booking->customer_phone,
            $pickup_date,
            $pickup_time,
            $booking->pickup_address,
            $booking->destination_address,
            $booking->price,
            $notes_line
        );

        $twilio->send_sms($admin_phone, $sms_message);
    }

    /**
     * Send admin notifications (email + SMS) for confirmed bookings — used by confirm_payment for pay-now
     */
    private function send_admin_notifications($booking) {
        if (!$booking) return;
        $this->send_admin_email($booking);
        // Note: SMS was already sent during create_booking via send_admin_sms()
        // Only re-send SMS here if this is being called from confirm_payment
        // to avoid double SMS for pay-later. So we skip SMS here.
    }

    /**
     * Send booking confirmation email with HTML template and PDF receipt
     */
    private function send_booking_confirmation_email($booking) {
        if (!$booking || empty($booking->customer_email)) {
            return;
        }

        $subject = 'Booking Confirmation - ' . $booking->booking_reference;

        // Generate branded HTML email
        $email_message = HP_Booking_Email_Template::render_confirmation_email($booking);
        $headers = HP_Booking_Email_Template::set_html_mail_headers();

        // Generate PDF receipt attachment
        $attachments = array();
        $pdf_path = HP_Booking_PDF_Receipt::generate($booking);
        if ($pdf_path && file_exists($pdf_path)) {
            $attachments[] = $pdf_path;
        }

        wp_mail($booking->customer_email, $subject, $email_message, $headers, $attachments);

        // Clean up PDF after sending (schedule cleanup for old files)
        if ($pdf_path && file_exists($pdf_path)) {
            // Delete immediately after email is sent
            @unlink($pdf_path);
        }
    }


    /**
     * Handle Stripe webhook
     */
    public function stripe_webhook($request) {
        // Get raw body for signature verification
        $payload = @file_get_contents('php://input');
        $signature = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';
        
        if (empty($payload) || empty($signature)) {
            return new WP_Error('missing_data', 'Missing payload or signature', array('status' => 400));
        }
        
        $stripe = HP_Booking_Stripe_Integration::get_instance();
        $event = $stripe->verify_webhook_signature($payload, $signature);
        
        if (!$event) {
            error_log('Stripe webhook signature verification failed');
            return new WP_Error('invalid_signature', 'Invalid webhook signature', array('status' => 400));
        }
        
        $stripe->process_webhook_event($event);
        
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Get bookings (admin)
     */
    public function get_bookings($request) {
        global $wpdb;
        $db = HP_Booking_Database::get_instance();
        $bookings_table = $db->get_table('bookings');
        
        $date = $request->get_param('date');
        $status = $request->get_param('status');
        $page = max(1, intval($request->get_param('page')));
        $per_page = min(100, max(1, intval($request->get_param('per_page')) ?: 20));
        $offset = ($page - 1) * $per_page;
        
        $where = array('1=1');
        $where_values = array();
        
        if ($date) {
            $where[] = "DATE(pickup_datetime) = %s";
            $where_values[] = $date;
        }
        
        if ($status) {
            $where[] = "booking_status = %s";
            $where_values[] = $status;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$bookings_table} WHERE {$where_clause} ORDER BY pickup_datetime DESC LIMIT %d OFFSET %d",
            array_merge($where_values, array($per_page, $offset))
        ));
        
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$bookings_table} WHERE {$where_clause}",
            $where_values
        ));
        
        return rest_ensure_response(array(
            'bookings' => $bookings,
            'total' => intval($total),
            'page' => $page,
            'per_page' => $per_page
        ));
    }
    
    /**
     * Get single booking (admin)
     */
    public function get_booking($request) {
        global $wpdb;
        $db = HP_Booking_Database::get_instance();
        $bookings_table = $db->get_table('bookings');
        
        $booking_id = intval($request->get_param('id'));
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$bookings_table} WHERE id = %d",
            $booking_id
        ));
        
        if (!$booking) {
            return new WP_Error('not_found', 'Booking not found', array('status' => 404));
        }
        
        return rest_ensure_response($booking);
    }
    
    /**
     * Cancel booking (admin)
     */
    public function cancel_booking($request) {
        global $wpdb;
        $db = HP_Booking_Database::get_instance();
        $bookings_table = $db->get_table('bookings');
        
        $booking_id = intval($request->get_param('id'));
        
        $result = $wpdb->update(
            $bookings_table,
            array('booking_status' => 'cancelled'),
            array('id' => $booking_id),
            array('%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('database_error', 'Failed to cancel booking', array('status' => 500));
        }
        
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Complete booking (admin)
     */
    public function complete_booking($request) {
        global $wpdb;
        $db = HP_Booking_Database::get_instance();
        $bookings_table = $db->get_table('bookings');
        
        $booking_id = intval($request->get_param('id'));
        
        $result = $wpdb->update(
            $bookings_table,
            array('booking_status' => 'completed'),
            array('id' => $booking_id),
            array('%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('database_error', 'Failed to complete booking', array('status' => 500));
        }
        
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Block availability (admin)
     */
    public function block_availability($request) {
        global $wpdb;
        $data = $request->get_json_params();
        $db = HP_Booking_Database::get_instance();
        $blocks_table = $db->get_table('availability_blocks');
        
        $result = $wpdb->insert($blocks_table, array(
            'block_type' => sanitize_text_field($data['block_type']),
            'start_datetime' => sanitize_text_field($data['start_datetime']),
            'end_datetime' => sanitize_text_field($data['end_datetime']),
            'reason' => isset($data['reason']) ? sanitize_text_field($data['reason']) : null
        ));
        
        if ($result === false) {
            return new WP_Error('database_error', 'Failed to create availability block', array('status' => 500));
        }
        
        return rest_ensure_response(array('success' => true, 'id' => $wpdb->insert_id));
    }
    
    /**
     * Unblock availability (admin)
     */
    public function unblock_availability($request) {
        global $wpdb;
        $db = HP_Booking_Database::get_instance();
        $blocks_table = $db->get_table('availability_blocks');
        
        $block_id = intval($request->get_param('id'));
        
        $result = $wpdb->delete($blocks_table, array('id' => $block_id), array('%d'));
        
        if ($result === false) {
            return new WP_Error('database_error', 'Failed to delete availability block', array('status' => 500));
        }
        
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Get availability blocks (admin)
     */
    public function get_availability_blocks($request) {
        global $wpdb;
        $db = HP_Booking_Database::get_instance();
        $blocks_table = $db->get_table('availability_blocks');
        
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');
        
        $where = array('1=1');
        $where_values = array();
        
        if ($start_date) {
            $where[] = "start_datetime >= %s";
            $where_values[] = $start_date . ' 00:00:00';
        }
        
        if ($end_date) {
            $where[] = "end_datetime <= %s";
            $where_values[] = $end_date . ' 23:59:59';
        }
        
        $where_clause = implode(' AND ', $where);
        
        $blocks = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$blocks_table} WHERE {$where_clause} ORDER BY start_datetime ASC",
            $where_values
        ));
        
        return rest_ensure_response(array('blocks' => $blocks));
    }
    
    /**
     * Get statistics (admin)
     */
    public function get_stats($request) {
        global $wpdb;
        $db = HP_Booking_Database::get_instance();
        $bookings_table = $db->get_table('bookings');
        $today = current_time('Y-m-d');
        $month_start = current_time('Y-m-01');

        $stats = array(
            'total_bookings' => intval($wpdb->get_var("SELECT COUNT(*) FROM {$bookings_table}")),
            'pending_bookings' => intval($wpdb->get_var("SELECT COUNT(*) FROM {$bookings_table} WHERE booking_status = 'pending'")),
            'confirmed_bookings' => intval($wpdb->get_var("SELECT COUNT(*) FROM {$bookings_table} WHERE booking_status = 'confirmed'")),
            'total_revenue' => floatval($wpdb->get_var("SELECT COALESCE(SUM(price), 0) FROM {$bookings_table} WHERE payment_status IN ('paid_online', 'paid_tap', 'paid_cash')")),
            'today_bookings' => intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$bookings_table} WHERE DATE(pickup_datetime) = %s AND booking_status NOT IN ('cancelled')", $today))),
            'this_month' => intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$bookings_table} WHERE pickup_datetime >= %s AND booking_status NOT IN ('cancelled')", $month_start . ' 00:00:00'))),
        );

        return rest_ensure_response($stats);
    }
    
    /**
     * Check admin permission
     */
    public function check_admin_permission() {
        return current_user_can('manage_options');
    }
    
    /**
     * Validate date format
     */
    public function validate_date($param, $request, $key) {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $param) && strtotime($param) !== false;
    }
}
