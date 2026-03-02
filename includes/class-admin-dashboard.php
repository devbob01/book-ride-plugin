<?php
/**
 * Admin dashboard class
 * Handles admin asset enqueuing and all admin-ajax handlers.
 *
 * Admin pages use admin-ajax.php instead of the WP REST API because
 * SiteGround (and similar cached hosts) strip cookies from REST requests,
 * breaking cookie-based authentication and causing 401 errors.
 */

if (!defined('ABSPATH')) {
    exit;
}

class HP_Booking_Admin_Dashboard {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Admin-ajax handlers — reliable on every host
        add_action('wp_ajax_hp_complete_booking',       array($this, 'ajax_complete_booking'));
        add_action('wp_ajax_hp_cancel_booking',         array($this, 'ajax_cancel_booking'));
        add_action('wp_ajax_hp_delete_booking',         array($this, 'ajax_delete_booking'));
        add_action('wp_ajax_hp_confirm_booking',       array($this, 'ajax_confirm_booking'));
        add_action('wp_ajax_hp_mark_paid_tap',         array($this, 'ajax_mark_paid_tap'));
        add_action('wp_ajax_hp_mark_paid_cash',        array($this, 'ajax_mark_paid_cash'));
        add_action('wp_ajax_hp_get_stripe_collect_url', array($this, 'ajax_get_stripe_collect_url'));
        add_action('wp_ajax_hp_create_collect_intent',  array($this, 'ajax_create_collect_intent'));
        add_action('wp_ajax_hp_confirm_collect_payment', array($this, 'ajax_confirm_collect_payment'));
        add_action('wp_ajax_hp_get_bookings',           array($this, 'ajax_get_bookings'));
        add_action('wp_ajax_hp_get_stats',              array($this, 'ajax_get_stats'));
        add_action('wp_ajax_hp_block_availability',     array($this, 'ajax_block_availability'));
        add_action('wp_ajax_hp_unblock_availability',   array($this, 'ajax_unblock_availability'));
        add_action('wp_ajax_hp_unblock_by_range',       array($this, 'ajax_unblock_by_range'));
        add_action('wp_ajax_hp_extend_availability',    array($this, 'ajax_extend_availability'));
        add_action('wp_ajax_hp_remove_extension',       array($this, 'ajax_remove_extension'));
        add_action('wp_ajax_hp_save_availability_working_hours', array($this, 'ajax_save_availability_working_hours'));
        add_action('wp_ajax_hp_get_availability_blocks', array($this, 'ajax_get_availability_blocks'));
        add_action('wp_ajax_hp_bulk_delete_bookings',   array($this, 'ajax_bulk_delete_bookings'));
        add_action('wp_ajax_hp_test_twilio',            array($this, 'ajax_test_twilio'));
    }

    public function ajax_test_twilio() {
        $this->verify_admin_ajax();
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        
        if (empty($phone)) {
            wp_send_json_error(array('message' => 'Please enter a phone number'));
        }
        
        $twilio = HP_Booking_Twilio_Integration::get_instance();
        $message = "Only Handsome Pete! Test message via Twilio sent at " . current_time('g:i A');
        
        $result = $twilio->send_sms($phone, $message);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array('message' => 'Test SMS sent successfully to ' . $phone));
    }

    /* ==================================================================
       Assets
       ================================================================== */

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'hp-booking') === false) {
            return;
        }

        wp_enqueue_style(
            'hp-booking-admin',
            HP_BOOKING_PLUGIN_URL . 'admin/assets/admin.css',
            array(),
            HP_BOOKING_VERSION
        );

        // Load Stripe.js for in-person payment collection
        wp_enqueue_script(
            'stripe-js',
            'https://js.stripe.com/v3/',
            array(),
            null,
            true
        );

        wp_enqueue_script(
            'hp-booking-admin',
            HP_BOOKING_PLUGIN_URL . 'admin/assets/admin.js',
            array('jquery', 'stripe-js'),
            HP_BOOKING_VERSION,
            true
        );

        $stripe = HP_Booking_Stripe_Integration::get_instance();
        wp_localize_script('hp-booking-admin', 'phpBookingAdmin', array(
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('hp_booking_admin'),
            'pluginUrl' => HP_BOOKING_PLUGIN_URL,
            'stripePk'  => $stripe->get_publishable_key(),
        ));
    }

    /* ==================================================================
       Shared helpers
       ================================================================== */

    /**
     * Verify the request came from an authenticated admin.
     * Terminates with JSON error on failure.
     */
    private function verify_admin_ajax() {
        check_ajax_referer('hp_booking_admin', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'), 403);
        }
    }

    /* ==================================================================
       Booking actions
       ================================================================== */

    public function ajax_complete_booking() {
        $this->verify_admin_ajax();
        global $wpdb;
        $db = HP_Booking_Database::get_instance();
        $table = $db->get_table('bookings');
        $id = intval($_POST['booking_id']);

        $ok = $wpdb->update($table, array('booking_status' => 'completed'), array('id' => $id), array('%s'), array('%d'));
        if ($ok === false) {
            wp_send_json_error(array('message' => 'Database error'));
        }
        wp_send_json_success(array('message' => 'Booking completed'));
    }

    public function ajax_cancel_booking() {
        $this->verify_admin_ajax();
        global $wpdb;
        $db = HP_Booking_Database::get_instance();
        $table = $db->get_table('bookings');
        $id = intval($_POST['booking_id']);

        $ok = $wpdb->update($table, array('booking_status' => 'cancelled'), array('id' => $id), array('%s'), array('%d'));
        if ($ok === false) {
            wp_send_json_error(array('message' => 'Database error'));
        }
        wp_send_json_success(array('message' => 'Booking cancelled'));
    }

    public function ajax_delete_booking() {
        $this->verify_admin_ajax();
        global $wpdb;
        $db = HP_Booking_Database::get_instance();
        $table = $db->get_table('bookings');
        $id = intval($_POST['booking_id']);

        $deleted = $wpdb->delete($table, array('id' => $id), array('%d'));
        if ($deleted === false) {
            wp_send_json_error(array('message' => 'Database error'));
        }
        wp_send_json_success(array('message' => 'Booking deleted'));
    }

    public function ajax_confirm_booking() {
        $this->verify_admin_ajax();
        global $wpdb;
        $db = HP_Booking_Database::get_instance();
        $table = $db->get_table('bookings');
        $id = intval($_POST['booking_id']);

        $ok = $wpdb->update($table, array('booking_status' => 'confirmed'), array('id' => $id), array('%s'), array('%d'));
        if ($ok === false) {
            wp_send_json_error(array('message' => 'Database error'));
        }
        wp_send_json_success(array('message' => 'Booking confirmed'));
    }

    public function ajax_bulk_delete_bookings() {
        $this->verify_admin_ajax();
        global $wpdb;
        $db = HP_Booking_Database::get_instance();
        $table = $db->get_table('bookings');

        $ids = isset($_POST['booking_ids']) ? $_POST['booking_ids'] : array();
        if (empty($ids) || !is_array($ids)) {
            wp_send_json_error(array('message' => 'No bookings selected'));
        }

        $ids = array_map('intval', $ids);
        $result = $wpdb->query("DELETE FROM {$table} WHERE id IN (" . implode(',', $ids) . ")");

        if ($result === false) {
            wp_send_json_error(array('message' => 'Database error'));
        }
        wp_send_json_success(array('message' => 'Bookings deleted'));
    }

    public function ajax_mark_paid_tap() {
        $this->verify_admin_ajax();
        global $wpdb;
        $db = HP_Booking_Database::get_instance();
        $table = $db->get_table('bookings');
        $id = intval($_POST['booking_id']);

        $ok = $wpdb->update(
            $table,
            array('payment_status' => 'paid_tap', 'booking_status' => 'confirmed'),
            array('id' => $id),
            array('%s', '%s'),
            array('%d')
        );
        if ($ok === false) {
            wp_send_json_error(array('message' => 'Database error'));
        }
        wp_send_json_success(array('message' => 'Marked as paid (tap)'));
    }

    public function ajax_mark_paid_cash() {
        $this->verify_admin_ajax();
        global $wpdb;
        $db = HP_Booking_Database::get_instance();
        $table = $db->get_table('bookings');
        $id = intval($_POST['booking_id']);

        $ok = $wpdb->update(
            $table,
            array('payment_status' => 'paid_cash', 'booking_status' => 'confirmed'),
            array('id' => $id),
            array('%s', '%s'),
            array('%d')
        );
        if ($ok === false) {
            wp_send_json_error(array('message' => 'Database error'));
        }
        wp_send_json_success(array('message' => 'Marked as paid (cash)'));
    }

    public function ajax_get_stripe_collect_url() {
        $this->verify_admin_ajax();
        $id = intval($_REQUEST['booking_id']);
        if (!$id) {
            wp_send_json_error(array('message' => 'Missing booking_id'));
        }
        global $wpdb;
        $db = HP_Booking_Database::get_instance();
        $table = $db->get_table('bookings');
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if (!$booking || $booking->booking_status === 'cancelled') {
            wp_send_json_error(array('message' => 'Booking not found or cancelled'));
        }
        $amount = (float) $booking->price;
        if ($amount <= 0) {
            wp_send_json_error(array('message' => 'Invalid amount'));
        }
        $stripe = HP_Booking_Stripe_Integration::get_instance();
        // Customer pays on their own phone via QR code, so redirect to public pages (no login needed)
        $base_url = site_url('/');
        $result = $stripe->create_checkout_session(
            $amount,
            'cad',
            add_query_arg(array('hp_payment_complete' => '1', 'ref' => $booking->booking_reference), $base_url),
            add_query_arg(array('hp_payment_cancelled' => '1'), $base_url),
            array('booking_reference' => $booking->booking_reference)
        );
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        wp_send_json_success(array(
            'url'          => $result['url'],
            'amount'       => $amount,
            'reference'    => $booking->booking_reference,
            'customerName' => $booking->customer_name,
        ));
    }

    /**
     * Create a PaymentIntent for in-person card collection (embedded payment form).
     */
    public function ajax_create_collect_intent() {
        $this->verify_admin_ajax();
        $id = intval($_REQUEST['booking_id']);
        if (!$id) {
            wp_send_json_error(array('message' => 'Missing booking_id'));
        }
        global $wpdb;
        $db = HP_Booking_Database::get_instance();
        $table = $db->get_table('bookings');
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if (!$booking || $booking->booking_status === 'cancelled') {
            wp_send_json_error(array('message' => 'Booking not found or cancelled'));
        }
        $amount = (float) $booking->price;
        if ($amount <= 0) {
            wp_send_json_error(array('message' => 'Invalid amount'));
        }
        $stripe = HP_Booking_Stripe_Integration::get_instance();
        $result = $stripe->create_payment_intent(
            $amount,
            'cad',
            array('booking_reference' => $booking->booking_reference)
        );
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        wp_send_json_success(array(
            'clientSecret' => $result['client_secret'],
            'amount'       => $amount,
            'reference'    => $booking->booking_reference,
            'customerName' => $booking->customer_name,
        ));
    }

    /**
     * After in-person payment succeeds, mark booking as paid.
     */
    public function ajax_confirm_collect_payment() {
        $this->verify_admin_ajax();
        $id = intval($_POST['booking_id']);
        if (!$id) {
            wp_send_json_error(array('message' => 'Missing booking_id'));
        }
        global $wpdb;
        $db = HP_Booking_Database::get_instance();
        $table = $db->get_table('bookings');
        $ok = $wpdb->update(
            $table,
            array('payment_status' => 'paid_online', 'booking_status' => 'confirmed'),
            array('id' => $id),
            array('%s', '%s'),
            array('%d')
        );
        if ($ok === false) {
            wp_send_json_error(array('message' => 'Database error'));
        }
        wp_send_json_success(array('message' => 'Payment confirmed'));
    }

    /* ==================================================================
       Booking queries
       ================================================================== */

    /**
     * Return bookings. Supports:
     *   date        — single date (YYYY-MM-DD)
     *   start_date  — range start (YYYY-MM-DD)  (used by availability calendar)
     *   end_date    — range end   (YYYY-MM-DD)
     *   status      — booking_status filter
     *   per_page    — max rows (default 50, max 200)
     */
    public function ajax_get_bookings() {
        $this->verify_admin_ajax();
        global $wpdb;
        $db = HP_Booking_Database::get_instance();
        $table = $db->get_table('bookings');

        $date       = isset($_REQUEST['date'])       ? sanitize_text_field($_REQUEST['date'])       : '';
        $start_date = isset($_REQUEST['start_date']) ? sanitize_text_field($_REQUEST['start_date']) : '';
        $end_date   = isset($_REQUEST['end_date'])   ? sanitize_text_field($_REQUEST['end_date'])   : '';
        $status     = isset($_REQUEST['status'])     ? sanitize_text_field($_REQUEST['status'])     : '';
        $per_page   = min(200, max(1, intval(isset($_REQUEST['per_page']) ? $_REQUEST['per_page'] : 50)));

        $where = array('1=1');
        $values = array();

        // Date filters — range takes priority over single date
        if ($start_date && $end_date) {
            $where[]  = "DATE(pickup_datetime) BETWEEN %s AND %s";
            $values[] = $start_date;
            $values[] = $end_date;
        } elseif ($date) {
            $where[]  = "DATE(pickup_datetime) = %s";
            $values[] = $date;
        }

        if ($status) {
            $where[]  = "booking_status = %s";
            $values[] = $status;
        }

        $sql_where = implode(' AND ', $where);

        if (!empty($values)) {
            $bookings = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$sql_where} ORDER BY pickup_datetime DESC LIMIT %d",
                array_merge($values, array($per_page))
            ));
            $total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE {$sql_where}",
                $values
            ));
        } else {
            $bookings = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY pickup_datetime DESC LIMIT %d",
                $per_page
            ));
            $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        }

        wp_send_json_success(array('bookings' => $bookings, 'total' => $total));
    }

    /* ==================================================================
       Stats
       ================================================================== */

    public function ajax_get_stats() {
        $this->verify_admin_ajax();
        global $wpdb;
        $db = HP_Booking_Database::get_instance();
        $t = $db->get_table('bookings');
        $today = current_time('Y-m-d');
        $month_start = current_time('Y-m-01');

        wp_send_json_success(array(
            'today_bookings'   => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$t} WHERE DATE(pickup_datetime) = %s AND booking_status != 'cancelled'", $today)),
            'pending_bookings' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$t} WHERE booking_status = 'pending'"),
            'total_revenue'    => (float) $wpdb->get_var(
                "SELECT COALESCE(SUM(price),0) FROM {$t} WHERE payment_status IN ('paid_online','paid_tap','paid_cash')"),
            'this_month'       => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$t} WHERE pickup_datetime >= %s AND booking_status != 'cancelled'", $month_start . ' 00:00:00')),
        ));
    }

    /* ==================================================================
       Availability blocks
       ================================================================== */

    public function ajax_block_availability() {
        $this->verify_admin_ajax();
        global $wpdb;
        $db = HP_Booking_Database::get_instance();
        $table = $db->get_table('availability_blocks');

        $ok = $wpdb->insert($table, array(
            'block_type'      => sanitize_text_field($_POST['block_type']),
            'start_datetime'  => sanitize_text_field($_POST['start_datetime']),
            'end_datetime'    => sanitize_text_field($_POST['end_datetime']),
            'reason'          => isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : null,
        ));

        if ($ok === false) {
            wp_send_json_error(array('message' => 'Failed to create block'));
        }
        wp_send_json_success(array('id' => $wpdb->insert_id));
    }

    public function ajax_unblock_availability() {
        $this->verify_admin_ajax();
        global $wpdb;
        $db = HP_Booking_Database::get_instance();
        $table = $db->get_table('availability_blocks');

        $ok = $wpdb->delete($table, array('id' => intval($_POST['block_id'])), array('%d'));
        if ($ok === false) {
            wp_send_json_error(array('message' => 'Failed to delete block'));
        }
        wp_send_json_success(array('message' => 'Block deleted'));
    }

    /**
     * Unblock availability by date/time range — deletes blocks that overlap the range.
     */
    public function ajax_unblock_by_range() {
        $this->verify_admin_ajax();
        $start = isset($_POST['start_datetime']) ? sanitize_text_field($_POST['start_datetime']) : '';
        $end   = isset($_POST['end_datetime'])   ? sanitize_text_field($_POST['end_datetime'])   : '';
        if (!$start || !$end) {
            wp_send_json_error(array('message' => 'Start and end datetime required'));
        }
        global $wpdb;
        $db = HP_Booking_Database::get_instance();
        $table = $db->get_table('availability_blocks');
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE start_datetime < %s AND end_datetime > %s",
            $end,
            $start
        ));
        wp_send_json_success(array('message' => 'Unblocked', 'deleted' => $deleted));
    }

    /**
     * Extend availability (unblock times outside default 08:00-20:00).
     */
    public function ajax_extend_availability() {
        $this->verify_admin_ajax();
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $start_time = isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : '';
        $end_time   = isset($_POST['end_time'])   ? sanitize_text_field($_POST['end_time'])   : '';
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !$start_time || !$end_time) {
            wp_send_json_error(array('message' => 'Invalid date or time range'));
        }
        if (!preg_match('/^\d{2}:\d{2}/', $start_time)) $start_time = '00:00';
        if (!preg_match('/^\d{2}:\d{2}/', $end_time))   $end_time   = '23:59';
        if (strlen($start_time) === 5) $start_time .= ':00';
        if (strlen($end_time) === 5)   $end_time   .= ':00';
        global $wpdb;
        $db = HP_Booking_Database::get_instance();
        $table = $db->get_table('availability_extensions');
        if (!$table || $wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            wp_send_json_error(array('message' => 'Extensions table not available'));
        }
        $ok = $wpdb->insert($table, array(
            'date'       => $date,
            'start_time' => $start_time,
            'end_time'   => $end_time,
        ), array('%s', '%s', '%s'));
        if ($ok === false) {
            wp_send_json_error(array('message' => 'Failed to extend availability'));
        }
        wp_send_json_success(array('id' => $wpdb->insert_id));
    }

    /**
     * Remove availability extension (re-block times that were unblocked outside 08:00-20:00).
     */
    public function ajax_remove_extension() {
        $this->verify_admin_ajax();
        $start = isset($_POST['start_datetime']) ? sanitize_text_field($_POST['start_datetime']) : '';
        $end   = isset($_POST['end_datetime'])   ? sanitize_text_field($_POST['end_datetime'])   : '';
        if (!$start || !$end) {
            wp_send_json_error(array('message' => 'Start and end datetime required'));
        }
        $date = substr($start, 0, 10);
        $start_t = substr($start, 11, 8);
        $end_t   = substr($end, 11, 8);
        global $wpdb;
        $db = HP_Booking_Database::get_instance();
        $table = $db->get_table('availability_extensions');
        if (!$table || $wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            wp_send_json_error(array('message' => 'Extensions table not available'));
        }
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE date = %s AND start_time < %s AND end_time > %s",
            $date,
            $end_t,
            $start_t
        ));
        wp_send_json_success(array('message' => 'Extension removed', 'deleted' => $deleted));
    }

    /**
     * Save working hours / slot interval from the Availability page.
     */
    public function ajax_save_availability_working_hours() {
        $this->verify_admin_ajax();
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $start = isset($_POST['working_hours_start']) ? sanitize_text_field($_POST['working_hours_start']) : '';
        $end   = isset($_POST['working_hours_end'])   ? sanitize_text_field($_POST['working_hours_end'])   : '';
        $intv  = isset($_POST['time_slot_interval'])  ? intval($_POST['time_slot_interval']) : 15;
        if ($intv < 1) $intv = 15;

        if (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) {
            wp_send_json_error(array('message' => 'Invalid time format. Use HH:MM.'));
        }

        $db = HP_Booking_Database::get_instance();
        $db->update_setting('working_hours_start', $start);
        $db->update_setting('working_hours_end', $end);
        $db->update_setting('time_slot_interval', (string) $intv);
        wp_send_json_success(array('message' => 'Saved'));
    }

    public function ajax_get_availability_blocks() {
        $this->verify_admin_ajax();
        global $wpdb;
        $db = HP_Booking_Database::get_instance();
        $table = $db->get_table('availability_blocks');
        $ext_table = $db->get_table('availability_extensions');

        $start = isset($_REQUEST['start_date']) ? sanitize_text_field($_REQUEST['start_date']) : '';
        $end   = isset($_REQUEST['end_date'])   ? sanitize_text_field($_REQUEST['end_date'])   : '';

        $where = array('1=1');
        $values = array();
        if ($start) { $where[] = "start_datetime >= %s"; $values[] = $start . ' 00:00:00'; }
        if ($end)   { $where[] = "end_datetime <= %s";   $values[] = $end   . ' 23:59:59'; }
        $sql_where = implode(' AND ', $where);

        if (!empty($values)) {
            $blocks = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$sql_where} ORDER BY start_datetime ASC", $values));
        } else {
            $blocks = $wpdb->get_results("SELECT * FROM {$table} ORDER BY start_datetime ASC");
        }

        $extensions = array();
        if ($ext_table && $wpdb->get_var("SHOW TABLES LIKE '{$ext_table}'") === $ext_table && $start && $end) {
            $extensions = $wpdb->get_results($wpdb->prepare(
                "SELECT id, date, start_time, end_time FROM {$ext_table} WHERE date >= %s AND date <= %s ORDER BY date, start_time",
                $start,
                $end
            ));
            $extensions = is_array($extensions) ? $extensions : array();
        }

        wp_send_json_success(array('blocks' => $blocks, 'extensions' => $extensions));
    }
}
