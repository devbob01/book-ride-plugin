<?php
/**
 * Uninstall script
 * Cleans up database tables and options when plugin is deleted
 */

// Exit if not called from WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$tables = array(
    $wpdb->prefix . 'hp_bookings',
    $wpdb->prefix . 'hp_availability_blocks',
    $wpdb->prefix . 'hp_service_areas',
    $wpdb->prefix . 'hp_settings'
);

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// Clear scheduled cron jobs
wp_clear_scheduled_hook('hp_booking_send_reminder');

// Optionally remove options
delete_option('hp_booking_version');
