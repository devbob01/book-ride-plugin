<?php
/**
 * Availability manager class
 * Handles availability checking and time blocking
 */

if (!defined('ABSPATH')) {
    exit;
}

class HP_Booking_Availability_Manager {
    
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
     * Check if time slot is available
     */
    public function is_time_available($pickup_datetime, $duration_minutes) {
        global $wpdb;
        
        $buffer_minutes = intval($this->db->get_setting('buffer_minutes', 30));
        $end_datetime = date('Y-m-d H:i:s', strtotime($pickup_datetime . " +{$duration_minutes} minutes +{$buffer_minutes} minutes"));
        
        // Check for overlapping bookings
        $bookings_table = $this->db->get_table('bookings');
        $overlapping = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$bookings_table} 
            WHERE booking_status NOT IN ('cancelled') 
            AND (
                (pickup_datetime <= %s AND end_datetime > %s) OR
                (pickup_datetime < %s AND end_datetime >= %s) OR
                (pickup_datetime >= %s AND end_datetime <= %s)
            )",
            $pickup_datetime, $pickup_datetime,
            $end_datetime, $end_datetime,
            $pickup_datetime, $end_datetime
        ));
        
        if ($overlapping > 0) {
            return false;
        }
        
        // Check for driver availability blocks
        $blocks_table = $this->db->get_table('availability_blocks');
        $blocked = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$blocks_table} 
            WHERE (
                (start_datetime <= %s AND end_datetime > %s) OR
                (start_datetime < %s AND end_datetime >= %s) OR
                (start_datetime >= %s AND end_datetime <= %s)
            )",
            $pickup_datetime, $pickup_datetime,
            $end_datetime, $end_datetime,
            $pickup_datetime, $end_datetime
        ));
        
        return $blocked == 0;
    }
    
    /**
     * Get available time slots for a date.
     * 24-hour range with slot duration from settings.
     * Default unblocked: working_start to working_end (e.g. 08:00-20:00).
     * Times outside default are available only if in availability_extensions.
     * Excludes bookings and blocks.
     */
    public function get_available_slots($date, $duration_minutes = 60) {
        global $wpdb;

        if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return array();
        }

        $settings = $this->get_availability_settings();
        $time_slot_interval = $settings['time_slot_interval'];
        $default_start = $settings['working_start'];
        $default_end = $settings['working_end'];
        $buffer_minutes = $settings['buffer_minutes'];
        $slot_duration = intval($duration_minutes) > 0 ? intval($duration_minutes) : 60;

        $default_start_ts = strtotime($date . ' ' . $default_start);
        $default_end_ts = strtotime($date . ' ' . $default_end);
        if ($default_start_ts === false || $default_end_ts === false || $default_start_ts >= $default_end_ts) {
            $default_start_ts = strtotime($date . ' 08:00');
            $default_end_ts = strtotime($date . ' 22:00');
        }

        $day_start = $date . ' 00:00:00';
        $day_end = $date . ' 23:59:59';
        $bookings_table = $this->db->get_table('bookings');
        $blocks_table = $this->db->get_table('availability_blocks');
        $ext_table = $this->db->get_table('availability_extensions');

        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT pickup_datetime, end_datetime FROM {$bookings_table}
            WHERE booking_status NOT IN ('cancelled')
            AND pickup_datetime <= %s AND end_datetime >= %s",
            $day_end,
            $day_start
        ));
        $bookings = is_array($bookings) ? $bookings : array();

        $blocks = $wpdb->get_results($wpdb->prepare(
            "SELECT start_datetime, end_datetime FROM {$blocks_table}
            WHERE start_datetime <= %s AND end_datetime >= %s",
            $day_end,
            $day_start
        ));
        $blocks = is_array($blocks) ? $blocks : array();

        $extensions = array();
        if ($ext_table && $wpdb->get_var("SHOW TABLES LIKE '{$ext_table}'") === $ext_table) {
            $extensions = $wpdb->get_results($wpdb->prepare(
                "SELECT start_time, end_time FROM {$ext_table} WHERE date = %s",
                $date
            ));
            $extensions = is_array($extensions) ? $extensions : array();
        }

        $slots = array();
        $day_start_ts = strtotime($date . ' 00:00');
        $day_end_ts = strtotime($date . ' 24:00');
        for ($time = $day_start_ts; $time < $day_end_ts; $time += ($time_slot_interval * 60)) {
            $slot_start_s = date('Y-m-d H:i:s', $time);
            $slot_end_s = date('Y-m-d H:i:s', $time + ($slot_duration * 60) + ($buffer_minutes * 60));

            $in_default = ($time >= $default_start_ts && $time < $default_end_ts);
            $in_extension = false;
            foreach ($extensions as $ex) {
                $ex_start = $date . ' ' . $ex->start_time;
                $ex_end = $date . ' ' . $ex->end_time;
                if ($this->ranges_overlap($slot_start_s, $slot_end_s, $ex_start, $ex_end)) {
                    $in_extension = true;
                    break;
                }
            }
            if (!$in_default && !$in_extension) {
                continue;
            }

            $blocked = false;
            foreach ($bookings as $b) {
                if ($this->ranges_overlap($slot_start_s, $slot_end_s, $b->pickup_datetime, $b->end_datetime)) {
                    $blocked = true;
                    break;
                }
            }
            if (!$blocked) {
                foreach ($blocks as $bl) {
                    if ($this->ranges_overlap($slot_start_s, $slot_end_s, $bl->start_datetime, $bl->end_datetime)) {
                        $blocked = true;
                        break;
                    }
                }
            }
            if (!$blocked) {
                $slots[] = date('H:i', $time);
            }
        }

        return $slots;
    }

    /**
     * Get availability-related settings (cached for the request to reduce DB reads).
     */
    private function get_availability_settings() {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $time_slot_interval = intval($this->db->get_setting('time_slot_interval', 15));
        $working_start = trim((string) $this->db->get_setting('working_hours_start', '08:00'));
        $working_end = trim((string) $this->db->get_setting('working_hours_end', '22:00'));
        if ($working_start === '') {
            $working_start = '08:00';
        }
        if ($working_end === '') {
            $working_end = '22:00';
        }
        if ($time_slot_interval < 1) {
            $time_slot_interval = 15;
        }
        $buffer_minutes = intval($this->db->get_setting('buffer_minutes', 30));
        $cache = array(
            'time_slot_interval' => $time_slot_interval,
            'working_start' => $working_start,
            'working_end' => $working_end,
            'buffer_minutes' => $buffer_minutes,
        );
        return $cache;
    }
    
    /**
     * Check if two time ranges overlap (used by get_available_slots).
     */
    private function ranges_overlap($start1, $end1, $start2, $end2) {
        return $start1 < $end2 && $end1 > $start2;
    }
    
    /**
     * Get availability extensions for a date (unblocked times outside default 08:00-20:00)
     */
    public function get_extensions_for_date($date) {
        global $wpdb;
        $table = $this->db->get_table('availability_extensions');
        if (!$table || $wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return array();
        }
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, start_time, end_time FROM {$table} WHERE date = %s",
            $date
        ));
        return is_array($rows) ? $rows : array();
    }

    /**
     * Get blocked time ranges for a date
     */
    public function get_blocked_times($date) {
        global $wpdb;
        $blocks_table = $this->db->get_table('availability_blocks');
        
        $start_of_day = $date . ' 00:00:00';
        $end_of_day = $date . ' 23:59:59';
        
        $blocks = $wpdb->get_results($wpdb->prepare(
            "SELECT start_datetime, end_datetime FROM {$blocks_table} 
            WHERE (
                (start_datetime >= %s AND start_datetime <= %s) OR
                (end_datetime >= %s AND end_datetime <= %s) OR
                (start_datetime <= %s AND end_datetime >= %s)
            )",
            $start_of_day, $end_of_day,
            $start_of_day, $end_of_day,
            $start_of_day, $end_of_day
        ));
        
        $ranges = array();
        foreach ($blocks as $block) {
            if (date('Y-m-d', strtotime($block->start_datetime)) == $date) {
                $ranges[] = array(
                    'start' => date('H:i', strtotime($block->start_datetime)),
                    'end' => date('H:i', strtotime($block->end_datetime))
                );
            }
        }
        
        return $ranges;
    }

    /**
     * Check if we are currently within operating hours (availability-based).
     * Used by #book-ride buttons: if not operating, show popup instead of navigating.
     * Uses site timezone.
     */
    public function is_operating_now() {
        $tz = wp_timezone();
        $now = new DateTimeImmutable('now', $tz);
        $date = $now->format('Y-m-d');
        $current_time = $now->format('H:i');

        $settings = $this->get_availability_settings();
        $interval = $settings['time_slot_interval'];
        $working_start = $settings['working_start'];
        $working_end = $settings['working_end'];

        // Round current time down to nearest slot (e.g. 08:17 -> 08:15 if interval 15)
        $mins = (int) $now->format('i');
        $rounded_mins = floor($mins / $interval) * $interval;
        $slot_time = sprintf('%02d:%02d', (int) $now->format('H'), (int) $rounded_mins);

        $slots = $this->get_available_slots($date, 60);
        return in_array($slot_time, $slots, true);
    }
}
