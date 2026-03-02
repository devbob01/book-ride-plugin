<?php
/**
 * Google Maps integration class
 * Handles Google Maps API calls for distance calculation and geocoding
 */

if (!defined('ABSPATH')) {
    exit;
}

class HP_Booking_Google_Maps {
    
    private static $instance = null;
    private $db;
    private $api_key;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->db = HP_Booking_Database::get_instance();
        $this->api_key = $this->db->get_setting('google_maps_api_key');
    }
    
    /**
     * Calculate distance and duration between two addresses
     */
    public function calculate_distance($pickup_address, $destination_address, $pickup_lat = null, $pickup_lng = null, $dest_lat = null, $dest_lng = null) {
        // Use coordinates if provided, otherwise use addresses
        $origin = $pickup_lat && $pickup_lng ? "{$pickup_lat},{$pickup_lng}" : urlencode($pickup_address);
        $destination = $dest_lat && $dest_lng ? "{$dest_lat},{$dest_lng}" : urlencode($destination_address);
        
        // Check cache first
        $cache_key = 'hp_distance_' . md5($origin . $destination);
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'Google Maps API key not configured');
        }
        
        $url = add_query_arg(array(
            'origins' => $origin,
            'destinations' => $destination,
            'units' => 'metric',
            'key' => $this->api_key
        ), 'https://maps.googleapis.com/maps/api/distancematrix/json');
        
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data['status'] !== 'OK' || empty($data['rows'][0]['elements'][0])) {
            return new WP_Error('api_error', 'Failed to calculate distance: ' . ($data['error_message'] ?? 'Unknown error'));
        }
        
        $element = $data['rows'][0]['elements'][0];
        
        if ($element['status'] !== 'OK') {
            return new WP_Error('route_error', 'Route calculation failed: ' . $element['status']);
        }
        
        $distance_km = round($element['distance']['value'] / 1000, 2); // Convert meters to km
        $duration_minutes = round($element['duration']['value'] / 60); // Convert seconds to minutes
        
        $result = array(
            'distance_km' => $distance_km,
            'duration_minutes' => $duration_minutes,
            'distance_text' => $element['distance']['text'],
            'duration_text' => $element['duration']['text']
        );
        
        // Cache for 24 hours
        set_transient($cache_key, $result, DAY_IN_SECONDS);
        
        return $result;
    }
    
    /**
     * Geocode address to get coordinates
     */
    public function geocode_address($address) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'Google Maps API key not configured');
        }
        
        $url = add_query_arg(array(
            'address' => urlencode($address),
            'key' => $this->api_key
        ), 'https://maps.googleapis.com/maps/api/geocode/json');
        
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data['status'] !== 'OK' || empty($data['results'][0])) {
            return new WP_Error('geocode_error', 'Failed to geocode address');
        }
        
        $result = $data['results'][0];
        $location = $result['geometry']['location'];
        
        return array(
            'formatted_address' => $result['formatted_address'],
            'lat' => $location['lat'],
            'lng' => $location['lng'],
            'place_id' => $result['place_id']
        );
    }
    
    /**
     * Generate Google Maps navigation URL
     */
    public function get_navigation_url($pickup_address, $destination_address, $pickup_lat = null, $pickup_lng = null, $dest_lat = null, $dest_lng = null) {
        $origin = $pickup_lat && $pickup_lng ? "{$pickup_lat},{$pickup_lng}" : urlencode($pickup_address);
        $destination = $dest_lat && $dest_lng ? "{$dest_lat},{$dest_lng}" : urlencode($destination_address);
        
        return add_query_arg(array(
            'api' => '1',
            'origin' => $origin,
            'destination' => $destination
        ), 'https://www.google.com/maps/dir/');
    }
}
