<?php
/**
 * Admin settings page
 */

if (!defined('ABSPATH')) {
    exit;
}

$db = HP_Booking_Database::get_instance();

if (isset($_POST['hp_save_settings']) && check_admin_referer('hp_save_settings')) {
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
        if (isset($_POST[$setting])) {
            $db->update_setting($setting, sanitize_text_field($_POST[$setting]));
        }
    }
    if (!isset($_POST['stripe_test_mode'])) {
        $db->update_setting('stripe_test_mode', '0');
    }
    
    echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
}
?>

<div class="wrap">
    <h1>Handsome Pete Booking Settings</h1>
    <p class="description">Configure your booking system settings below. For detailed instructions, visit <a href="<?php echo admin_url('admin.php?page=hp-booking-help'); ?>">Help & Documentation</a>.</p>
    
    <form method="post" action="">
        <?php wp_nonce_field('hp_save_settings'); ?>
        
        <h2>Pricing Settings</h2>
        <p class="description">Configure how customers are charged for rides. You can use a single rate or tiered rates that change after certain distances (e.g. lower $/km after 20 km and again after 40 km).</p>
        <table class="form-table">
            <tr>
                <th><label for="price_per_km">Price per Kilometer — first tier ($)</label></th>
                <td>
                    <input type="number" step="0.01" name="price_per_km" id="price_per_km" value="<?php echo esc_attr($db->get_setting('price_per_km', '1.75')); ?>" />
                    <p class="description">Rate for the first distance tier (from 0 km up to the first threshold below).</p>
                </td>
            </tr>
            <tr>
                <th><label for="pricing_tier2_km">Second tier — from (km)</label></th>
                <td>
                    <input type="number" step="0.1" min="0" name="pricing_tier2_km" id="pricing_tier2_km" value="<?php echo esc_attr($db->get_setting('pricing_tier2_km', '20')); ?>" placeholder="20" />
                    <p class="description">After this many km, the rate below applies. Leave 0 to use a single rate for all distances.</p>
                </td>
            </tr>
            <tr>
                <th><label for="pricing_tier2_rate">Second tier — price per km ($)</label></th>
                <td>
                    <input type="number" step="0.01" min="0" name="pricing_tier2_rate" id="pricing_tier2_rate" value="<?php echo esc_attr($db->get_setting('pricing_tier2_rate', '1.50')); ?>" placeholder="1.50" />
                </td>
            </tr>
            <tr>
                <th><label for="pricing_tier3_km">Third tier — from (km)</label></th>
                <td>
                    <input type="number" step="0.1" min="0" name="pricing_tier3_km" id="pricing_tier3_km" value="<?php echo esc_attr($db->get_setting('pricing_tier3_km', '40')); ?>" placeholder="40" />
                    <p class="description">After this many km, the third tier rate applies. Leave 0 to only use two tiers.</p>
                </td>
            </tr>
            <tr>
                <th><label for="pricing_tier3_rate">Third tier — price per km ($)</label></th>
                <td>
                    <input type="number" step="0.01" min="0" name="pricing_tier3_rate" id="pricing_tier3_rate" value="<?php echo esc_attr($db->get_setting('pricing_tier3_rate', '1.25')); ?>" placeholder="1.25" />
                </td>
            </tr>
        </table>
        <p class="description" style="margin-top:0;">Example: 0–20 km at $1.75/km, 20–40 km at $1.50/km, 40+ km at $1.25/km. Total for 50 km = 20×1.75 + 20×1.50 + 10×1.25 = $77.50.</p>
        
        <h2>Booking Settings</h2>
        <p class="description">Control when bookings can be made and how time slots are managed.</p>
        <table class="form-table">
            <tr>
                <th><label for="buffer_minutes">Buffer Time (minutes)</label></th>
                <td>
                    <input type="number" name="buffer_minutes" id="buffer_minutes" value="<?php echo esc_attr($db->get_setting('buffer_minutes', '30')); ?>" />
                    <p class="description">Time between rides to prevent overlapping bookings. Includes cleanup, turnaround, and refuel time.</p>
                </td>
            </tr>
            <tr>
                <th><label for="minimum_notice_hours">Minimum Notice (hours)</label></th>
                <td>
                    <input type="number" name="minimum_notice_hours" id="minimum_notice_hours" value="<?php echo esc_attr($db->get_setting('minimum_notice_hours', '2')); ?>" />
                    <p class="description">How far in advance customers must book. Prevents last-minute bookings.</p>
                </td>
            </tr>
            <tr>
                <th><label for="time_slot_interval">Time Slot Interval (minutes)</label></th>
                <td>
                    <input type="number" name="time_slot_interval" id="time_slot_interval" value="<?php echo esc_attr($db->get_setting('time_slot_interval', '15')); ?>" />
                    <p class="description">Interval between available time slots (e.g., 15 = slots at :00, :15, :30, :45). Operating hours and availability are managed in <a href="<?php echo esc_url(admin_url('admin.php?page=hp-booking-availability')); ?>">Availability</a>.</p>
                </td>
            </tr>
        </table>
        
        <h2>Stripe Settings</h2>
        <p class="description">Configure Stripe for payment processing. Get your keys from <a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe Dashboard → API Keys</a>. Webhook URL: <code><?php echo esc_html(rest_url('hp-booking/v1/stripe-webhook')); ?></code></p>
        <?php
        $stripe_test_mode = $db->get_setting('stripe_test_mode', '1');
        $is_test_mode = ($stripe_test_mode === '1' || $stripe_test_mode === 'on');
        ?>
        <table class="form-table">
            <tr>
                <th>Test Mode</th>
                <td>
                    <label>
                        <input type="checkbox" name="stripe_test_mode" value="1" <?php checked($is_test_mode); ?> />
                        Use test keys (no real charges). Uncheck for live payments.
                    </label>
                </td>
            </tr>
        </table>
        <h3>Test Keys</h3>
        <p class="description">Used when Test Mode is enabled. Get test keys from <a href="https://dashboard.stripe.com/test/apikeys" target="_blank">Stripe Dashboard (Test Mode)</a>.</p>
        <table class="form-table">
            <tr>
                <th><label for="stripe_publishable_key_test">Test Publishable Key</label></th>
                <td>
                    <input type="text" name="stripe_publishable_key_test" id="stripe_publishable_key_test" value="<?php echo esc_attr($db->get_setting('stripe_publishable_key_test')); ?>" class="regular-text" placeholder="pk_test_..." />
                    <p class="description">Starts with <code>pk_test_</code>.</p>
                </td>
            </tr>
            <tr>
                <th><label for="stripe_secret_key_test">Test Secret Key</label></th>
                <td>
                    <input type="password" name="stripe_secret_key_test" id="stripe_secret_key_test" value="<?php echo esc_attr($db->get_setting('stripe_secret_key_test')); ?>" class="regular-text" placeholder="sk_test_..." />
                    <p class="description">Starts with <code>sk_test_</code>. Keep secret!</p>
                </td>
            </tr>
            <tr>
                <th><label for="stripe_webhook_secret_test">Test Webhook Secret</label></th>
                <td>
                    <input type="password" name="stripe_webhook_secret_test" id="stripe_webhook_secret_test" value="<?php echo esc_attr($db->get_setting('stripe_webhook_secret_test')); ?>" class="regular-text" placeholder="whsec_..." />
                    <p class="description">Create a test webhook at <a href="https://dashboard.stripe.com/test/webhooks" target="_blank">Stripe Test Webhooks</a>.</p>
                </td>
            </tr>
        </table>
        <h3>Live Keys</h3>
        <p class="description">Used when Test Mode is disabled (real payments). Get live keys from <a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe Dashboard (Live Mode)</a>.</p>
        <table class="form-table">
            <tr>
                <th><label for="stripe_publishable_key">Live Publishable Key</label></th>
                <td>
                    <input type="text" name="stripe_publishable_key" id="stripe_publishable_key" value="<?php echo esc_attr($db->get_setting('stripe_publishable_key')); ?>" class="regular-text" placeholder="pk_live_..." />
                    <p class="description">Starts with <code>pk_live_</code>.</p>
                </td>
            </tr>
            <tr>
                <th><label for="stripe_secret_key">Live Secret Key</label></th>
                <td>
                    <input type="password" name="stripe_secret_key" id="stripe_secret_key" value="<?php echo esc_attr($db->get_setting('stripe_secret_key')); ?>" class="regular-text" placeholder="sk_live_..." />
                    <p class="description">Starts with <code>sk_live_</code>. Keep secret!</p>
                </td>
            </tr>
            <tr>
                <th><label for="stripe_webhook_secret">Live Webhook Secret</label></th>
                <td>
                    <input type="password" name="stripe_webhook_secret" id="stripe_webhook_secret" value="<?php echo esc_attr($db->get_setting('stripe_webhook_secret')); ?>" class="regular-text" placeholder="whsec_..." />
                    <p class="description">Create a live webhook at <a href="https://dashboard.stripe.com/webhooks" target="_blank">Stripe Live Webhooks</a>.</p>
                </td>
            </tr>
        </table>
        
        <h2>Twilio Settings</h2>
        <p class="description">Configure Twilio for SMS notifications. Get credentials from <a href="https://www.twilio.com/console" target="_blank">Twilio Console</a>.</p>
        <table class="form-table">
            <tr>
                <th><label for="twilio_account_sid">Account SID</label></th>
                <td>
                    <input type="text" name="twilio_account_sid" id="twilio_account_sid" value="<?php echo esc_attr($db->get_setting('twilio_account_sid')); ?>" class="regular-text" />
                    <p class="description">Found in Twilio Console dashboard.</p>
                </td>
            </tr>
            <tr>
                <th><label for="twilio_auth_token">Auth Token</label></th>
                <td>
                    <input type="password" name="twilio_auth_token" id="twilio_auth_token" value="<?php echo esc_attr($db->get_setting('twilio_auth_token')); ?>" class="regular-text" />
                    <p class="description">Keep this secret! Found in Twilio Console.</p>
                </td>
            </tr>
            <tr>
                <th><label for="twilio_phone_number">Phone Number</label></th>
                <td>
                    <input type="text" name="twilio_phone_number" id="twilio_phone_number" value="<?php echo esc_attr($db->get_setting('twilio_phone_number')); ?>" class="regular-text" placeholder="+1234567890" />
                    <p class="description">Twilio phone number to send SMS from. Include country code (e.g., +1 for US/Canada).</p>
                </td>
            </tr>
        </table>
        
        <!-- Twilio Test SMS -->
        <h3>Test SMS Integration</h3>
        <p class="description">Send a test message to verify your settings.</p>
        <table class="form-table">
            <tr>
                <th><label for="twilio_test_phone">Test Phone Number</label></th>
                <td>
                    <input type="text" id="twilio_test_phone" class="regular-text" placeholder="+1234567890" />
                    <button type="button" id="hp-test-twilio-btn" class="button button-secondary">Send Test SMS</button>
                    <span id="hp-test-twilio-result" style="margin-left: 10px; font-weight: 500;"></span>
                    <p class="description">Make sure to save your settings above before testing.</p>
                </td>
            </tr>
        </table>
        <script>
        jQuery(document).ready(function($) {
            $('#hp-test-twilio-btn').click(function() {
                var btn = $(this);
                var phone = $('#twilio_test_phone').val();
                var resultSpan = $('#hp-test-twilio-result');
                
                if (!phone) {
                    alert('Please enter a phone number');
                    return;
                }
                
                btn.prop('disabled', true).text('Sending...');
                resultSpan.text('').removeClass('success-message error-message').css('color', '');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hp_test_twilio',
                        phone: phone,
                        nonce: '<?php echo wp_create_nonce("hp_booking_admin"); ?>'
                    },
                    success: function(response) {
                        btn.prop('disabled', false).text('Send Test SMS');
                        if (response.success) {
                            resultSpan.text('✓ ' + response.data.message).css('color', 'green');
                        } else {
                            resultSpan.text('✗ ' + (response.data.message || 'Unknown error')).css('color', 'red');
                        }
                    },
                    error: function() {
                        btn.prop('disabled', false).text('Send Test SMS');
                        resultSpan.text('✗ Server error').css('color', 'red');
                    }
                });
            });
        });
        </script>
        
        <h2>Google Maps Settings</h2>
        <p class="description">Required for address autocomplete and distance calculation. Get your key from <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a>.</p>
        <table class="form-table">
            <tr>
                <th><label for="google_maps_api_key">API Key</label></th>
                <td>
                    <input type="text" name="google_maps_api_key" id="google_maps_api_key" value="<?php echo esc_attr($db->get_setting('google_maps_api_key')); ?>" class="regular-text" />
                    <p class="description">
                        Enable these APIs: Places API, Distance Matrix API, Geocoding API, Maps JavaScript API.<br>
                        <strong>Note:</strong> Billing must be enabled, but you get $200 free credit monthly.
                    </p>
                </td>
            </tr>
        </table>
        
        <h2>Driver Settings</h2>
        <p class="description">Contact information for receiving booking notifications.</p>
        <table class="form-table">
            <tr>
                <th><label for="driver_phone_number">Driver Phone Number</label></th>
                <td>
                    <input type="text" name="driver_phone_number" id="driver_phone_number" value="<?php echo esc_attr($db->get_setting('driver_phone_number')); ?>" class="regular-text" placeholder="+1234567890" />
                    <p class="description">Phone number to receive SMS notifications for new bookings. Include country code.</p>
                </td>
            </tr>
            <tr>
                <th><label for="driver_email">Driver Email</label></th>
                <td>
                    <input type="email" name="driver_email" id="driver_email" value="<?php echo esc_attr($db->get_setting('driver_email')); ?>" class="regular-text" />
                    <p class="description">Email address for booking notifications (optional, SMS is primary).</p>
                </td>
            </tr>
        </table>
        
        <?php submit_button('Save Settings', 'primary', 'hp_save_settings'); ?>
    </form>
</div>
