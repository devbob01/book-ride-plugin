<?php
/**
 * Admin help/documentation page
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap hp-booking-help">
    <h1>Handsome Pete Booking System - Documentation</h1>
    
    <div class="hp-help-tabs">
        <nav class="nav-tab-wrapper">
            <a href="#getting-started" class="nav-tab nav-tab-active">Getting Started</a>
            <a href="#configuration" class="nav-tab">Configuration</a>
            <a href="#using-booking-form" class="nav-tab">Using Booking Form</a>
            <a href="#managing-bookings" class="nav-tab">Managing Bookings</a>
            <a href="#availability" class="nav-tab">Availability</a>
            <a href="#troubleshooting" class="nav-tab">Troubleshooting</a>
        </nav>
        
        <div id="getting-started" class="hp-help-tab-content active">
            <h2>Getting Started</h2>
            
            <h3>1. Initial Setup</h3>
            <ol>
                <li><strong>Activate the Plugin:</strong> Go to Plugins → Installed Plugins and activate "Handsome Pete Booking System"</li>
                <li><strong>Configure API Keys:</strong> Navigate to Bookings → Settings and enter your API credentials:
                    <ul>
                        <li>Google Maps API key</li>
                        <li>Stripe API keys (for payments)</li>
                        <li>Twilio credentials (for SMS notifications)</li>
                    </ul>
                </li>
                <li><strong>Set Driver Information:</strong> Enter driver phone number and email in Settings</li>
                <li><strong>Configure Pricing:</strong> Set your price per kilometer (default: $1.75/km)</li>
            </ol>
            
            <h3>2. Add Booking Form to Your Site</h3>
            <p>To display the booking form on any page or post:</p>
            <ol>
                <li>Edit the page/post where you want the booking form</li>
                <li>Add this shortcode: <code>[hp_booking_form]</code></li>
                <li>Publish or update the page</li>
            </ol>
            <p><strong>Example:</strong> Create a page called "Book a Ride" and add the shortcode there.</p>
            
            <h3>3. Test Your Setup</h3>
            <ol>
                <li>Visit the page with the booking form</li>
                <li>Try creating a test booking with addresses in your service area</li>
                <li>Check that you receive SMS notifications (if Twilio is configured)</li>
                <li>Verify bookings appear in Bookings → All Bookings</li>
            </ol>
        </div>
        
        <div id="configuration" class="hp-help-tab-content">
            <h2>Configuration Guide</h2>
            
            <h3>Pricing Settings</h3>
            <ul>
                <li><strong>Price per Kilometer:</strong> Set your rate (default: $1.75). The system calculates: Distance × Price per km</li>
                <li><strong>Buffer Time:</strong> Time between rides (default: 30 minutes). Prevents overlapping bookings.</li>
                <li><strong>Minimum Notice:</strong> How far in advance bookings must be made (default: 2 hours)</li>
            </ul>
            
            <h3>Booking Settings</h3>
            <ul>
                <li><strong>Time Slot Interval:</strong> Interval between available time slots (default: 15 minutes)</li>
                <li><strong>Working Hours:</strong> Start and end times for available bookings (default: 8:00 AM - 8:00 PM)</li>
            </ul>
            
            <h3>Google Maps API Setup</h3>
            <ol>
                <li>Go to <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                <li>Create a project or select existing one</li>
                <li>Enable these APIs:
                    <ul>
                        <li>Places API</li>
                        <li>Distance Matrix API</li>
                        <li>Geocoding API</li>
                        <li>Maps JavaScript API</li>
                    </ul>
                </li>
                <li>Create an API key</li>
                <li>Paste the key in Bookings → Settings → Google Maps Settings</li>
            </ol>
            <p><strong>Note:</strong> Google Maps requires billing to be enabled, but you get $200 free credit monthly.</p>
            
            <h3>Stripe Setup</h3>
            <ol>
                <li>Create a <a href="https://stripe.com" target="_blank">Stripe account</a></li>
                <li>Get your API keys from Dashboard → Developers → API keys</li>
                <li>Enter Publishable key and Secret key in Settings</li>
                <li><strong>Set up Webhook:</strong>
                    <ul>
                        <li>Go to Stripe Dashboard → Developers → Webhooks</li>
                        <li>Add endpoint: <code>https://yoursite.com/wp-json/hp-booking/v1/stripe-webhook</code></li>
                        <li>Select events: <code>payment_intent.succeeded</code>, <code>terminal.payment_succeeded</code>, <code>payment_intent.payment_failed</code></li>
                        <li>Copy webhook signing secret and paste in Settings</li>
                    </ul>
                </li>
            </ol>
            
            <h3>Twilio Setup</h3>
            <ol>
                <li>Create a <a href="https://www.twilio.com" target="_blank">Twilio account</a></li>
                <li>Get your Account SID and Auth Token from the dashboard</li>
                <li>Purchase a phone number (or use trial number for testing)</li>
                <li>Enter all three in Bookings → Settings → Twilio Settings</li>
            </ol>
        </div>
        
        <div id="using-booking-form" class="hp-help-tab-content">
            <h2>Using the Booking Form (Customer View)</h2>
            
            <h3>Booking Process</h3>
            <p>Customers go through these steps:</p>
            
            <h4>Step 1: Select Date</h4>
            <ul>
                <li>Choose a pickup date from the calendar</li>
                <li>Only dates with available slots are shown</li>
                <li>Minimum notice period applies (default: 2 hours)</li>
            </ul>
            
            <h4>Step 2: Select Time</h4>
            <ul>
                <li>Available time slots appear for the selected date</li>
                <li>Slots are automatically filtered to prevent overlaps</li>
                <li>Time slots respect working hours and buffer times</li>
            </ul>
            
            <h4>Step 3: Enter Addresses</h4>
            <ul>
                <li><strong>Pickup Address:</strong> Where the driver will pick up the customer</li>
                <li><strong>Destination Address:</strong> Where the customer wants to go</li>
                <li>Both addresses must be in the service area (Grafton, Cobourg, Port Hope, or Brighton)</li>
                <li>Address autocomplete helps with accurate addresses</li>
            </ul>
            
            <h4>Step 4: Review Price</h4>
            <ul>
                <li>System calculates distance using Google Maps</li>
                <li>Price = Distance (km) × Price per km</li>
                <li>Estimated duration is also shown</li>
            </ul>
            
            <h4>Step 5: Choose Payment Method</h4>
            <ul>
                <li><strong>Pay Now:</strong> Customer pays immediately via credit/debit card</li>
                <li><strong>Pay on Pickup:</strong> Customer pays when driver arrives using Tap to Pay</li>
            </ul>
            
            <h4>Step 6: Enter Information</h4>
            <ul>
                <li>Customer name (required)</li>
                <li>Email address (required)</li>
                <li>Phone number (required)</li>
            </ul>
            
            <h4>Confirmation</h4>
            <ul>
                <li>Booking reference number is displayed</li>
                <li>Customer receives confirmation email</li>
                <li>Customer receives SMS confirmation (if Twilio configured)</li>
                <li>Driver receives SMS notification with booking details</li>
            </ul>
        </div>
        
        <div id="managing-bookings" class="hp-help-tab-content">
            <h2>Managing Bookings (Admin)</h2>
            
            <h3>Viewing Bookings</h3>
            <p>Go to <strong>Bookings → All Bookings</strong> to see all bookings.</p>
            
            <h4>Filtering Bookings</h4>
            <ul>
                <li><strong>By Date:</strong> Select a specific date to see bookings for that day</li>
                <li><strong>By Status:</strong> Filter by Pending, Confirmed, Completed, or Cancelled</li>
                <li>Click "Filter" to apply filters</li>
            </ul>
            
            <h4>Booking Information Displayed</h4>
            <ul>
                <li>Booking Reference (e.g., HP-2026-001)</li>
                <li>Customer name and phone</li>
                <li>Pickup and destination addresses</li>
                <li>Date and time</li>
                <li>Distance and price</li>
                <li>Booking status</li>
                <li>Payment status</li>
            </ul>
            
            <h3>Booking Actions</h3>
            
            <h4>Complete Booking</h4>
            <ol>
                <li>Find the booking in the list</li>
                <li>Click "Complete" button</li>
                <li>Booking status changes to "Completed"</li>
            </ol>
            
            <h4>Cancel Booking</h4>
            <ol>
                <li>Find the booking in the list</li>
                <li>Click "Cancel" button</li>
                <li>Confirm cancellation</li>
                <li>Booking status changes to "Cancelled"</li>
                <li>If payment was made, refund may be required (handle manually in Stripe)</li>
            </ol>
            
            <h3>Dashboard Overview</h3>
            <p>The <strong>Bookings → Dashboard</strong> shows:</p>
            <ul>
                <li>Today's bookings count</li>
                <li>Pending bookings count</li>
                <li>Total revenue</li>
                <li>This month's bookings</li>
                <li>Upcoming bookings list</li>
            </ul>
        </div>
        
        <div id="availability" class="hp-help-tab-content">
            <h2>Managing Availability</h2>
            
            <h3>Blocking Time</h3>
            <p>Go to <strong>Bookings → Availability</strong> to block times when you're unavailable.</p>
            
            <h4>How to Block Time</h4>
            <ol>
                <li>Select block type:
                    <ul>
                        <li><strong>Unavailable:</strong> General unavailability</li>
                        <li><strong>Maintenance:</strong> Vehicle maintenance time</li>
                        <li><strong>Personal:</strong> Personal time off</li>
                    </ul>
                </li>
                <li>Enter start date and time</li>
                <li>Enter end date and time</li>
                <li>Add reason (optional)</li>
                <li>Click "Block Time"</li>
            </ol>
            
            <h4>What Happens When Time is Blocked</h4>
            <ul>
                <li>Blocked time slots won't appear in the booking form</li>
                <li>Customers cannot book during blocked periods</li>
                <li>Existing bookings are not affected</li>
            </ul>
            
            <h3>Removing Blocks</h3>
            <p>To make blocked time available again, delete the availability block from the list.</p>
            
            <h3>Best Practices</h3>
            <ul>
                <li>Block time in advance for days off</li>
                <li>Use maintenance blocks for vehicle service</li>
                <li>Review blocked times regularly</li>
                <li>Block entire days if needed (e.g., 8:00 AM to 8:00 PM)</li>
            </ul>
        </div>
        
        <div id="troubleshooting" class="hp-help-tab-content">
            <h2>Troubleshooting</h2>
            
            <h3>Booking Form Not Appearing</h3>
            <ul>
                <li>Verify shortcode is correct: <code>[hp_booking_form]</code></li>
                <li>Check that Google Maps API key is entered in Settings</li>
                <li>Check browser console for JavaScript errors</li>
                <li>Ensure Google Maps API is enabled in Google Cloud Console</li>
            </ul>
            
            <h3>Address Autocomplete Not Working</h3>
            <ul>
                <li>Verify Google Maps API key is correct</li>
                <li>Check that Places API is enabled</li>
                <li>Check browser console for API errors</li>
                <li>Verify API key restrictions allow your domain</li>
            </ul>
            
            <h3>Price Not Calculating</h3>
            <ul>
                <li>Verify both pickup and destination addresses are entered</li>
                <li>Check that Distance Matrix API is enabled</li>
                <li>Verify addresses are in service area</li>
                <li>Check Google Cloud Console for API usage/quota issues</li>
            </ul>
            
            <h3>Bookings Not Creating</h3>
            <ul>
                <li>Check that all required fields are filled</li>
                <li>Verify addresses are in service area</li>
                <li>Check that selected time slot is still available</li>
                <li>Review WordPress error logs</li>
                <li>Check database connection</li>
            </ul>
            
            <h3>SMS Not Sending</h3>
            <ul>
                <li>Verify Twilio credentials are correct</li>
                <li>Check Twilio account has sufficient balance</li>
                <li>Verify phone number format is correct</li>
                <li>Check Twilio logs in their dashboard</li>
            </ul>
            
            <h3>Payments Not Processing</h3>
            <ul>
                <li>Verify Stripe API keys are correct</li>
                <li>Check Stripe webhook is configured</li>
                <li>Verify webhook signing secret matches</li>
                <li>Check Stripe dashboard for payment attempts</li>
                <li>Review WordPress error logs for webhook errors</li>
            </ul>
            
            <h3>Time Slots Not Showing</h3>
            <ul>
                <li>Check working hours are set correctly</li>
                <li>Verify minimum notice period</li>
                <li>Check for availability blocks</li>
                <li>Verify existing bookings aren't blocking all slots</li>
            </ul>
            
            <h3>Service Area Validation Failing</h3>
            <ul>
                <li>Verify service area coordinates in database</li>
                <li>Check that addresses are actually in service cities</li>
                <li>Review Google Geocoding API responses</li>
                <li>Update service area bounds if needed</li>
            </ul>
            
            <h3>Getting Help</h3>
            <ul>
                <li>Check WordPress error logs: <code>wp-content/debug.log</code></li>
                <li>Enable WordPress debug mode in <code>wp-config.php</code></li>
                <li>Check browser console for JavaScript errors</li>
                <li>Review API dashboards (Google, Stripe, Twilio) for errors</li>
            </ul>
        </div>
    </div>
</div>

<style>
.hp-help-tabs {
    margin-top: 20px;
}

.hp-help-tab-content {
    display: none;
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    margin-top: -1px;
}

.hp-help-tab-content.active {
    display: block;
}

.hp-help-tab-content h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #0073aa;
}

.hp-help-tab-content h3 {
    margin-top: 30px;
    color: #0073aa;
}

.hp-help-tab-content h4 {
    margin-top: 20px;
    color: #555;
}

.hp-help-tab-content code {
    background: #f5f5f5;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
}

.hp-help-tab-content ul, .hp-help-tab-content ol {
    margin-left: 20px;
}

.hp-help-tab-content li {
    margin-bottom: 8px;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href');
        
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        $('.hp-help-tab-content').removeClass('active');
        $(target).addClass('active');
    });
});
</script>
