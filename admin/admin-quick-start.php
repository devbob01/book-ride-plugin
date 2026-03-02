<?php
/**
 * Quick Start Guide - Shown on first activation
 */

if (!defined('ABSPATH')) {
    exit;
}

$db = HP_Booking_Database::get_instance();
$has_google_key = !empty($db->get_setting('google_maps_api_key'));
$has_stripe_key = !empty($db->get_setting('stripe_publishable_key'));
$has_twilio = !empty($db->get_setting('twilio_account_sid'));
$setup_complete = $has_google_key && $has_stripe_key && $has_twilio;
?>

<div class="wrap hp-quick-start">
    <h1>🚀 Quick Start Guide</h1>
    
    <?php if (!$setup_complete): ?>
    <div class="notice notice-warning">
        <p><strong>Setup Incomplete:</strong> Please configure your API keys to start accepting bookings.</p>
    </div>
    <?php endif; ?>
    
    <div class="hp-setup-status">
        <h2>Setup Status</h2>
        <ul>
            <li class="<?php echo $has_google_key ? 'complete' : 'incomplete'; ?>">
                <?php echo $has_google_key ? '✅' : '❌'; ?> Google Maps API Key
            </li>
            <li class="<?php echo $has_stripe_key ? 'complete' : 'incomplete'; ?>">
                <?php echo $has_stripe_key ? '✅' : '❌'; ?> Stripe API Keys
            </li>
            <li class="<?php echo $has_twilio ? 'complete' : 'incomplete'; ?>">
                <?php echo $has_twilio ? '✅' : '❌'; ?> Twilio Credentials
            </li>
        </ul>
    </div>
    
    <div class="hp-quick-steps">
        <h2>5-Minute Setup</h2>
        
        <div class="hp-step">
            <h3>Step 1: Configure API Keys</h3>
            <p>Go to <a href="<?php echo admin_url('admin.php?page=hp-booking-settings'); ?>">Bookings → Settings</a> and enter:</p>
            <ul>
                <li>Google Maps API key (required for address autocomplete and distance calculation)</li>
                <li>Stripe keys (required for payments)</li>
                <li>Twilio credentials (required for SMS notifications)</li>
            </ul>
            <p><a href="<?php echo admin_url('admin.php?page=hp-booking-settings'); ?>" class="button button-primary">Go to Settings</a></p>
        </div>
        
        <div class="hp-step">
            <h3>Step 2: Add Booking Form to Your Site</h3>
            <p>Create a page (e.g., "Book a Ride") and add this shortcode:</p>
            <div class="hp-code-block">
                <code>[hp_booking_form]</code>
            </div>
            <p>Or add it to any existing page where you want the booking form to appear.</p>
        </div>
        
        <div class="hp-step">
            <h3>Step 3: Test Your Booking Form</h3>
            <ol>
                <li>Visit the page with the booking form</li>
                <li>Try selecting a date and time</li>
                <li>Enter test addresses in your service area (Grafton, Cobourg, Port Hope, or Brighton)</li>
                <li>Complete a test booking</li>
            </ol>
        </div>
        
        <div class="hp-step">
            <h3>Step 4: Set Up Stripe Webhook</h3>
            <p>For automatic payment status updates:</p>
            <ol>
                <li>Go to <a href="https://dashboard.stripe.com/webhooks" target="_blank">Stripe Dashboard → Webhooks</a></li>
                <li>Click "Add endpoint"</li>
                <li>Enter URL: <code><?php echo rest_url('hp-booking/v1/stripe-webhook'); ?></code></li>
                <li>Select events: <code>payment_intent.succeeded</code>, <code>terminal.payment_succeeded</code>, <code>payment_intent.payment_failed</code></li>
                <li>Copy the webhook signing secret and paste it in Settings</li>
            </ol>
        </div>
        
        <div class="hp-step">
            <h3>Step 5: You're Ready!</h3>
            <p>Start accepting bookings! Monitor them in <a href="<?php echo admin_url('admin.php?page=hp-booking-list'); ?>">Bookings → All Bookings</a></p>
        </div>
    </div>
    
    <div class="hp-help-links">
        <h2>Need More Help?</h2>
        <ul>
            <li><a href="<?php echo admin_url('admin.php?page=hp-booking-help'); ?>">📖 Full Documentation</a></li>
            <li><a href="<?php echo admin_url('admin.php?page=hp-booking-help#configuration'); ?>">⚙️ Configuration Guide</a></li>
            <li><a href="<?php echo admin_url('admin.php?page=hp-booking-help#troubleshooting'); ?>">🔧 Troubleshooting</a></li>
        </ul>
    </div>
</div>

<style>
.hp-quick-start {
    max-width: 900px;
}

.hp-setup-status {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin: 20px 0;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.hp-setup-status ul {
    list-style: none;
    margin: 0;
    padding: 0;
}

.hp-setup-status li {
    padding: 10px;
    margin: 5px 0;
    font-size: 16px;
}

.hp-setup-status li.complete {
    color: #00a32a;
}

.hp-setup-status li.incomplete {
    color: #d63638;
}

.hp-quick-steps {
    margin-top: 30px;
}

.hp-step {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin: 20px 0;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.hp-step h3 {
    margin-top: 0;
    color: #0073aa;
}

.hp-code-block {
    background: #f5f5f5;
    border: 1px solid #ddd;
    padding: 15px;
    margin: 10px 0;
    border-radius: 4px;
}

.hp-code-block code {
    font-size: 16px;
    color: #0073aa;
}

.hp-help-links {
    background: #f0f8ff;
    border: 1px solid #0073aa;
    padding: 20px;
    margin: 30px 0;
    border-radius: 4px;
}

.hp-help-links ul {
    list-style: none;
    margin: 10px 0 0 0;
    padding: 0;
}

.hp-help-links li {
    margin: 10px 0;
}

.hp-help-links a {
    font-size: 16px;
    text-decoration: none;
}

.hp-help-links a:hover {
    text-decoration: underline;
}
</style>
