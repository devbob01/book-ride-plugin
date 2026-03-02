<?php
/**
 * Stripe integration class
 * Handles Stripe payment processing (online and Tap to Pay)
 */

if (!defined('ABSPATH')) {
    exit;
}

class HP_Booking_Stripe_Integration {
    
    private static $instance = null;
    private $db;
    private $secret_key;
    private $publishable_key;
    private $webhook_secret;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->db = HP_Booking_Database::get_instance();
        $test_mode = $this->db->get_setting('stripe_test_mode', '1');
        $is_test = ($test_mode === '1' || $test_mode === 'on');

        if ($is_test) {
            // Test mode: ONLY use test keys – never fall back to live keys
            $this->secret_key = $this->db->get_setting('stripe_secret_key_test', '');
            $this->publishable_key = $this->db->get_setting('stripe_publishable_key_test', '');
            $this->webhook_secret = $this->db->get_setting('stripe_webhook_secret_test', '');
        } else {
            // Live mode: ONLY use live keys
            $this->secret_key = $this->db->get_setting('stripe_secret_key', '');
            $this->publishable_key = $this->db->get_setting('stripe_publishable_key', '');
            $this->webhook_secret = $this->db->get_setting('stripe_webhook_secret', '');
        }

        // Trim whitespace from all keys
        $this->secret_key = is_string($this->secret_key) ? trim($this->secret_key) : '';
        $this->publishable_key = is_string($this->publishable_key) ? trim($this->publishable_key) : '';
        $this->webhook_secret = is_string($this->webhook_secret) ? trim($this->webhook_secret) : '';

        // Validate key prefixes match mode to catch misconfiguration
        $mode_label = $is_test ? 'TEST' : 'LIVE';
        $expected_sk_prefix = $is_test ? 'sk_test_' : 'sk_live_';
        $expected_pk_prefix = $is_test ? 'pk_test_' : 'pk_live_';

        if ($this->secret_key && strpos($this->secret_key, $expected_sk_prefix) !== 0) {
            error_log("[HP Booking] WARNING: Stripe is in {$mode_label} mode but secret key does not start with {$expected_sk_prefix}. Check Bookings > Settings.");
        }
        if ($this->publishable_key && strpos($this->publishable_key, $expected_pk_prefix) !== 0) {
            error_log("[HP Booking] WARNING: Stripe is in {$mode_label} mode but publishable key does not start with {$expected_pk_prefix}. Check Bookings > Settings.");
        }

        if (function_exists('error_log')) {
            $sk_info = $this->secret_key ? substr($this->secret_key, 0, 12) . '...' : '(empty)';
            $pk_info = $this->publishable_key ? substr($this->publishable_key, 0, 12) . '...' : '(empty)';
            error_log("[HP Booking] Stripe mode: {$mode_label}, sk: {$sk_info}, pk: {$pk_info}");
        }
    }
    
    /**
     * Create payment intent for online payment
     */
    public function create_payment_intent($amount, $currency = 'cad', $metadata = array()) {
        if (empty($this->secret_key)) {
            return new WP_Error('no_api_key', 'Stripe secret key not configured');
        }
        
        // Load Stripe library (bundled vendor/stripe/stripe-php or Composer)
        if (!class_exists('\Stripe\Stripe')) {
            $stripe_init = HP_BOOKING_PLUGIN_DIR . 'vendor/stripe/stripe-php/init.php';
            if (file_exists($stripe_init)) {
                require_once $stripe_init;
            } else {
                $autoload = HP_BOOKING_PLUGIN_DIR . 'vendor/autoload.php';
                if (!file_exists($autoload)) {
                    return new WP_Error('stripe_sdk_missing', 'Stripe SDK not installed. Upload the plugin vendor folder.');
                }
                require_once $autoload;
            }
        }
        
        try {
            \Stripe\Stripe::setApiKey($this->secret_key);
            
            $intent = \Stripe\PaymentIntent::create(array(
                'amount' => round($amount * 100), // Convert to cents
                'currency' => strtolower($currency),
                'metadata' => $metadata,
                'payment_method_types' => array('card')
            ));
            
            return array(
                'client_secret' => $intent->client_secret,
                'payment_intent_id' => $intent->id
            );
        } catch (Exception $e) {
            return new WP_Error('stripe_error', $e->getMessage());
        }
    }
    
    /**
     * Retrieve a payment intent to verify its status
     */
    public function retrieve_payment_intent($payment_intent_id) {
        if (empty($this->secret_key)) {
            return new WP_Error('no_api_key', 'Stripe secret key not configured');
        }
        
        if (!class_exists('\Stripe\Stripe')) {
            $stripe_init = HP_BOOKING_PLUGIN_DIR . 'vendor/stripe/stripe-php/init.php';
            if (file_exists($stripe_init)) {
                require_once $stripe_init;
            } else {
                $autoload = HP_BOOKING_PLUGIN_DIR . 'vendor/autoload.php';
                if (!file_exists($autoload)) {
                    return new WP_Error('stripe_sdk_missing', 'Stripe SDK not installed.');
                }
                require_once $autoload;
            }
        }
        
        try {
            \Stripe\Stripe::setApiKey($this->secret_key);
            $intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);
            return array(
                'status' => $intent->status,
                'amount' => $intent->amount,
                'currency' => $intent->currency,
                'metadata' => $intent->metadata ? $intent->metadata->toArray() : array()
            );
        } catch (Exception $e) {
            return new WP_Error('stripe_error', $e->getMessage());
        }
    }

    /**
     * Create Checkout Session for redirect payment (no Payment Element on page)
     */
    public function create_checkout_session($amount, $currency = 'cad', $success_url, $cancel_url, $metadata = array()) {
        if (empty($this->secret_key)) {
            return new WP_Error('no_api_key', 'Stripe secret key not configured');
        }
        if (empty($success_url) || empty($cancel_url)) {
            return new WP_Error('invalid_url', 'Success and cancel URLs required');
        }
        if (!class_exists('\Stripe\Stripe')) {
            $stripe_init = HP_BOOKING_PLUGIN_DIR . 'vendor/stripe/stripe-php/init.php';
            if (file_exists($stripe_init)) {
                require_once $stripe_init;
            } else {
                $autoload = HP_BOOKING_PLUGIN_DIR . 'vendor/autoload.php';
                if (!file_exists($autoload)) {
                    return new WP_Error('stripe_sdk_missing', 'Stripe SDK not installed. Upload the plugin vendor folder.');
                }
                require_once $autoload;
            }
        }
        try {
            \Stripe\Stripe::setApiKey($this->secret_key);
            $session = \Stripe\Checkout\Session::create(array(
                'mode' => 'payment',
                'payment_method_types' => array('card'),
                'success_url' => $success_url,
                'cancel_url' => $cancel_url,
                'line_items' => array(
                    array(
                        'price_data' => array(
                            'currency' => strtolower($currency),
                            'unit_amount' => round($amount * 100),
                            'product_data' => array(
                                'name' => 'Ride booking',
                                'description' => isset($metadata['booking_reference']) ? 'Booking ' . $metadata['booking_reference'] : 'Handsome Pete ride'
                            )
                        ),
                        'quantity' => 1
                    )
                ),
                'metadata' => $metadata
            ));
            return array(
                'url' => $session->url,
                'session_id' => $session->id
            );
        } catch (Exception $e) {
            return new WP_Error('stripe_error', $e->getMessage());
        }
    }
    
    /**
     * Verify webhook signature
     */
    public function verify_webhook_signature($payload, $signature) {
        if (empty($this->webhook_secret)) {
            return false;
        }
        
        try {
            if (!class_exists('\Stripe\Webhook')) {
                require_once HP_BOOKING_PLUGIN_DIR . 'vendor/autoload.php';
            }
            
            \Stripe\Stripe::setApiKey($this->secret_key);
            $event = \Stripe\Webhook::constructEvent($payload, $signature, $this->webhook_secret);
            return $event;
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Process webhook event
     */
    public function process_webhook_event($event) {
        global $wpdb;
        $bookings_table = HP_Booking_Database::get_instance()->get_table('bookings');
        
        switch ($event->type) {
            case 'payment_intent.succeeded':
                // Online payment succeeded
                $payment_intent_id = $event->data->object->id;
                $metadata = $event->data->object->metadata;
                
                // Handle metadata as object or array
                $booking_ref = is_object($metadata) ? ($metadata->booking_reference ?? null) : ($metadata['booking_reference'] ?? null);
                
                if (!empty($booking_ref)) {
                    $result = $wpdb->update(
                        $bookings_table,
                        array(
                            'payment_status' => 'paid_online',
                            'booking_status' => 'confirmed',
                            'stripe_payment_intent_id' => $payment_intent_id
                        ),
                        array('booking_reference' => $booking_ref),
                        array('%s', '%s', '%s'),
                        array('%s')
                    );
                    
                    if ($result !== false) {
                        error_log("Stripe payment succeeded for booking: {$booking_ref}");
                        
                        // Send confirmation email (if not already sent by confirm-payment endpoint)
                        $booking = $wpdb->get_row($wpdb->prepare(
                            "SELECT * FROM {$bookings_table} WHERE booking_reference = %s",
                            $booking_ref
                        ));
                        if ($booking && !empty($booking->customer_email)) {
                            // The webhook fires as backup - email may already be sent by confirm-payment
                            // Only send if status was just updated (result > 0 means rows changed)
                            if ($result > 0) {
                                $subject = 'Booking Confirmation - ' . $booking_ref;
                                $email_message = HP_Booking_Email_Template::render_confirmation_email($booking);
                                $headers = HP_Booking_Email_Template::set_html_mail_headers();

                                // Generate PDF receipt
                                $attachments = array();
                                $pdf_path = HP_Booking_PDF_Receipt::generate($booking);
                                if ($pdf_path && file_exists($pdf_path)) {
                                    $attachments[] = $pdf_path;
                                }

                                wp_mail($booking->customer_email, $subject, $email_message, $headers, $attachments);

                                // Cleanup PDF
                                if ($pdf_path && file_exists($pdf_path)) {
                                    @unlink($pdf_path);
                                }
                            }
                        }
                    }
                }
                break;
                
            case 'terminal.payment_succeeded':
                // Tap to Pay payment succeeded
                $payment_id = $event->data->object->id;
                $metadata = $event->data->object->metadata ?? array();
                
                // Handle metadata as object or array
                $booking_ref = is_object($metadata) ? ($metadata->booking_reference ?? null) : ($metadata['booking_reference'] ?? null);
                
                if (!empty($booking_ref)) {
                    $result = $wpdb->update(
                        $bookings_table,
                        array(
                            'payment_status' => 'paid_tap',
                            'booking_status' => 'confirmed',
                            'stripe_terminal_payment_id' => $payment_id
                        ),
                        array('booking_reference' => $booking_ref),
                        array('%s', '%s', '%s'),
                        array('%s')
                    );
                    
                    if ($result !== false) {
                        error_log("Stripe Tap to Pay succeeded for booking: {$booking_ref}");
                    }
                }
                break;
                
            case 'checkout.session.completed':
                $session = $event->data->object;
                $metadata = $session->metadata ?? array();
                $booking_ref = is_object($metadata) ? ($metadata->booking_reference ?? null) : ($metadata['booking_reference'] ?? null);
                if (!empty($booking_ref)) {
                    $wpdb->update(
                        $bookings_table,
                        array(
                            'payment_status' => 'paid_online',
                            'booking_status' => 'confirmed'
                        ),
                        array('booking_reference' => $booking_ref),
                        array('%s', '%s'),
                        array('%s')
                    );
                    if (function_exists('error_log')) {
                        error_log("Stripe Checkout completed for booking: {$booking_ref}");
                    }
                }
                break;
                
            case 'payment_intent.payment_failed':
                // Payment failed
                $payment_intent_id = $event->data->object->id;
                $metadata = $event->data->object->metadata;
                
                // Handle metadata as object or array
                $booking_ref = is_object($metadata) ? ($metadata->booking_reference ?? null) : ($metadata['booking_reference'] ?? null);
                
                if (!empty($booking_ref)) {
                    // Log payment failure
                    error_log("Stripe payment failed for booking: {$booking_ref}");
                    
                    // Optionally update booking status or send notification
                    // For now, booking remains in pending status
                }
                break;
        }
    }
    
    /**
     * Get publishable key
     */
    public function get_publishable_key() {
        return $this->publishable_key;
    }
}
