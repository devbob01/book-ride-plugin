<?php
/**
 * Admin dashboard page — fully server-side rendered.
 * No REST API or AJAX calls needed for this page.
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$db = HP_Booking_Database::get_instance();

// --- Stripe mode banner data ---
$stripe_test_mode = $db->get_setting('stripe_test_mode', '1');
$is_test_mode = ($stripe_test_mode === '1' || $stripe_test_mode === 'on');

if ($is_test_mode) {
    $has_stripe_pk = !empty(trim($db->get_setting('stripe_publishable_key_test', '')));
    $has_stripe_sk = !empty(trim($db->get_setting('stripe_secret_key_test', '')));
} else {
    $has_stripe_pk = !empty(trim($db->get_setting('stripe_publishable_key', '')));
    $has_stripe_sk = !empty(trim($db->get_setting('stripe_secret_key', '')));
}
$has_google_key = !empty($db->get_setting('google_maps_api_key'));
$has_twilio     = !empty($db->get_setting('twilio_account_sid'));

// --- Load dashboard stats server-side ---
$bookings_table = $db->get_table('bookings');
$today       = current_time('Y-m-d');
$month_start = current_time('Y-m-01');

$hp_today_bookings = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$bookings_table} WHERE DATE(pickup_datetime) = %s AND booking_status != 'cancelled'",
    $today
));
$hp_pending_bookings = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$bookings_table} WHERE booking_status = 'pending'"
);
$hp_total_revenue = (float) $wpdb->get_var(
    "SELECT COALESCE(SUM(price), 0) FROM {$bookings_table} WHERE payment_status IN ('paid_online', 'paid_tap', 'paid_cash')"
);
$hp_this_month = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$bookings_table} WHERE pickup_datetime >= %s AND booking_status != 'cancelled'",
    $month_start . ' 00:00:00'
));
$hp_unpaid_total = (float) $wpdb->get_var(
    "SELECT COALESCE(SUM(price), 0) FROM {$bookings_table} WHERE payment_status = 'pending' AND booking_status != 'cancelled'"
);

$now = current_time('mysql');

// Next ride: soonest upcoming (by time) confirmed or pending booking
$hp_next_ride = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$bookings_table}
     WHERE pickup_datetime >= %s AND booking_status IN ('confirmed','pending')
     ORDER BY pickup_datetime ASC LIMIT 1",
    $now
));
if (!$hp_next_ride) {
    $hp_next_ride = $wpdb->get_row(
        "SELECT * FROM {$bookings_table} WHERE booking_status IN ('confirmed','pending') ORDER BY pickup_datetime ASC LIMIT 1"
    );
}

// Awaiting action: unpaid (pay later) bookings, soonest first
$hp_awaiting = $wpdb->get_results(
    "SELECT * FROM {$bookings_table} WHERE payment_status = 'pending' AND booking_status != 'cancelled' ORDER BY pickup_datetime ASC LIMIT 10"
);

// Recent list for "All bookings" link -> Now the main list
// --- Params for list ---
$search       = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
$selected_date_start = isset($_GET['date_start']) ? sanitize_text_field($_GET['date_start']) : '';
$selected_date_end   = isset($_GET['date_end'])   ? sanitize_text_field($_GET['date_end'])   : '';
$selected_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$selected_payment = isset($_GET['payment']) ? sanitize_text_field($_GET['payment']) : '';
$sort_by      = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'pickup_datetime';
$order_dir    = isset($_GET['order']) && strtolower($_GET['order']) === 'asc' ? 'ASC' : 'DESC';

$allowed_sort = array('pickup_datetime' => 'pickup_datetime', 'customer_name' => 'customer_name', 'price' => 'price', 'booking_status' => 'booking_status', 'payment_status' => 'payment_status');
$sort_by = isset($allowed_sort[$sort_by]) ? $allowed_sort[$sort_by] : 'pickup_datetime';

$where  = array('1=1');
$values = array();

if ($search !== '') {
    $like = '%' . $wpdb->esc_like($search) . '%';
    $where[] = "(booking_reference LIKE %s OR customer_name LIKE %s OR customer_email LIKE %s OR customer_phone LIKE %s)";
    $values[] = $like;
    $values[] = $like;
    $values[] = $like;
    $values[] = $like;
}
if ($selected_date_start) {
    $where[] = "DATE(pickup_datetime) >= %s";
    $values[] = $selected_date_start;
}
if ($selected_date_end) {
    $where[] = "DATE(pickup_datetime) <= %s";
    $values[] = $selected_date_end;
}
if ($selected_status) {
    $where[] = "booking_status = %s";
    $values[] = $selected_status;
}
if ($selected_payment) {
    $where[] = "payment_status = %s";
    $values[] = $selected_payment;
}

$sql_where = implode(' AND ', $where);
$order_clause = $sort_by . ' ' . $order_dir;

if (!empty($values)) {
    $hp_upcoming = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$bookings_table} WHERE {$sql_where} ORDER BY {$order_clause} LIMIT 100",
        $values
    ));
} else {
    // Default view: shows everything sorted
    $hp_upcoming = $wpdb->get_results(
        "SELECT * FROM {$bookings_table} ORDER BY {$order_clause} LIMIT 100"
    );
}

// === Welcome banner ===
if (!$has_google_key || !$has_stripe_pk || !$has_twilio): ?>
<div class="notice notice-info is-dismissible">
    <p><strong>Welcome to Handsome Pete Booking System!</strong> Get started by configuring your API keys.
        <a href="<?php echo esc_url(admin_url('admin.php?page=hp-booking-quick-start')); ?>">View Quick Start Guide</a> or
        <a href="<?php echo esc_url(admin_url('admin.php?page=hp-booking-settings')); ?>">Go to Settings</a></p>
</div>
<?php endif; ?>

<?php // === Stripe mode banner === ?>
<?php if ($is_test_mode): ?>
<div class="notice notice-warning" style="border-left-color: #f0ad4e;">
    <p><strong style="color: #f0ad4e;">&#9888; STRIPE TEST MODE</strong> &mdash; No real charges will be made. Use test card <code>4242 4242 4242 4242</code> to test payments.
    <?php if (!$has_stripe_pk || !$has_stripe_sk): ?>
        <br><span style="color: #dc3232;">&#10007; Missing test keys:</span>
        <?php if (!$has_stripe_pk): ?><code>Test Publishable Key</code><?php endif; ?>
        <?php if (!$has_stripe_sk): ?><code>Test Secret Key</code><?php endif; ?>
        &mdash; <a href="<?php echo esc_url(admin_url('admin.php?page=hp-booking-settings')); ?>">Go to Settings</a>
    <?php else: ?>
        <br><span style="color: #46b450;">&#10003; Test keys configured.</span>
    <?php endif; ?>
    </p>
</div>
<?php else: ?>
<div class="notice notice-success" style="border-left-color: #46b450;">
    <p><strong style="color: #46b450;">&#9679; STRIPE LIVE MODE</strong> &mdash; Real payments are active.
    <?php if (!$has_stripe_pk || !$has_stripe_sk): ?>
        <br><span style="color: #dc3232;">&#10007; Missing live keys:</span>
        <?php if (!$has_stripe_pk): ?><code>Live Publishable Key</code><?php endif; ?>
        <?php if (!$has_stripe_sk): ?><code>Live Secret Key</code><?php endif; ?>
        &mdash; <a href="<?php echo esc_url(admin_url('admin.php?page=hp-booking-settings')); ?>">Go to Settings</a>
    <?php else: ?>
        <br><span style="color: #46b450;">&#10003; Live keys configured.</span>
    <?php endif; ?>
    </p>
</div>
<?php endif; ?>

<div class="wrap hp-booking-dashboard hp-dashboard-fun">
    <h1 class="hp-dashboard-title">Handsome Pete Booking</h1>
    <p class="hp-dashboard-links">
        <a href="<?php echo esc_url(admin_url('admin.php?page=hp-booking-list')); ?>">All bookings</a> &middot;
        <a href="<?php echo esc_url(admin_url('admin.php?page=hp-booking-help')); ?>">Help</a> &middot;
        <a href="<?php echo esc_url(admin_url('admin.php?page=hp-booking-settings')); ?>">Settings</a>
    </p>

    <!-- Stats Cards -->
    <div class="hp-fun-stats">
        <div class="hp-fun-stat hp-fun-stat--today">
            <span class="hp-fun-stat-label">Today's Rides</span>
            <span class="hp-fun-stat-value"><?php echo intval($hp_today_bookings); ?></span>
        </div>
        <div class="hp-fun-stat hp-fun-stat--confirm">
            <span class="hp-fun-stat-label">Need Confirm</span>
            <span class="hp-fun-stat-value"><?php echo intval($hp_pending_bookings); ?></span>
        </div>
        <div class="hp-fun-stat hp-fun-stat--paid">
            <span class="hp-fun-stat-label">Paid Total</span>
            <span class="hp-fun-stat-value">$<?php echo number_format($hp_total_revenue, 0); ?></span>
        </div>
        <div class="hp-fun-stat hp-fun-stat--unpaid">
            <span class="hp-fun-stat-label">Unpaid</span>
            <span class="hp-fun-stat-value">$<?php echo number_format($hp_unpaid_total, 0); ?></span>
        </div>
    </div>

    <div class="hp-fun-layout-two">
    <?php if ($hp_next_ride) : $b = $hp_next_ride;
        $next_paid = in_array($b->payment_status, array('paid_online', 'paid_tap', 'paid_cash'), true);
    ?>
    <section class="hp-fun-card hp-fun-next-ride">
        <h2 class="hp-fun-card-title"><span class="hp-fun-icon hp-fun-icon--target"></span> Next Ride</h2>
        <?php if ($next_paid) : ?><div class="hp-fun-badge hp-fun-badge--paid">PAID</div><?php else : ?><div class="hp-fun-badge hp-fun-badge--unpaid">UNPAID</div><?php endif; ?>
        <?php if ($is_test_mode) : ?><p class="hp-fun-test-mode">TEST MODE &mdash; Card: 4242 4242 4242 4242</p><?php endif; ?>
        <p class="hp-fun-when"><?php echo esc_html(date_i18n('l \a\t g:i A', strtotime($b->pickup_datetime))); ?></p>
        <p class="hp-fun-customer">
            <strong><?php echo esc_html($b->customer_name); ?></strong>
            <a href="tel:<?php echo esc_attr(preg_replace('/\D/', '', $b->customer_phone)); ?>" class="hp-fun-tel"><?php echo esc_html($b->customer_phone); ?></a>
        </p>
        <div class="hp-fun-route">
            <span class="hp-fun-dot hp-fun-dot--green"></span>
            <span class="hp-fun-address"><?php echo esc_html(mb_strimwidth($b->pickup_address ?: '-', 0, 50, '...')); ?></span>
        </div>
        <div class="hp-fun-route">
            <span class="hp-fun-dot hp-fun-dot--red"></span>
            <span class="hp-fun-address"><?php echo esc_html(mb_strimwidth($b->destination_address ?: '-', 0, 50, '...')); ?></span>
        </div>
        <p class="hp-fun-meta">$<?php echo number_format((float) $b->price, 2); ?> &middot; <?php echo number_format((float) $b->distance_km, 2); ?> km</p>
        <?php if (!empty($b->notes)): ?>
        <div class="hp-fun-special-notes" style="background: #fffbf0; border-left: 3px solid #C9A227; padding: 10px 12px; margin: 12px 0; border-radius: 4px;">
            <strong style="color: #C9A227; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">📝 Special Instructions</strong>
            <p style="margin: 6px 0 0; font-size: 14px; color: #1d2327; font-style: italic;"><?php echo esc_html($b->notes); ?></p>
        </div>
        <?php endif; ?>
        <?php if (!$next_paid) : ?>
        <p class="hp-fun-collect">Collect $<?php echo number_format((float) $b->price, 2); ?> on arrival.</p>
        <div class="hp-fun-actions hp-fun-actions--wrap">
            <button type="button" class="hp-fun-btn hp-fun-btn--stripe hp-js-stripe-collect" data-id="<?php echo (int) $b->id; ?>">Open Stripe &ndash; customer taps here</button>
            <button type="button" class="hp-fun-btn hp-fun-btn--tap hp-js-mark-tap" data-id="<?php echo (int) $b->id; ?>">Paid &ndash; Card (Tap)</button>
            <button type="button" class="hp-fun-btn hp-fun-btn--cash hp-js-mark-cash" data-id="<?php echo (int) $b->id; ?>">Paid &ndash; Cash</button>
            <a href="tel:<?php echo esc_attr(preg_replace('/\D/', '', $b->customer_phone)); ?>" class="hp-fun-btn hp-fun-btn--call">Call</a>
        </div>
        <?php endif; ?>
        <div class="hp-fun-actions">
            <a href="https://www.google.com/maps/dir/?api=1&origin=<?php echo rawurlencode($b->pickup_address); ?>&destination=<?php echo rawurlencode($b->destination_address); ?>" target="_blank" rel="noopener" class="hp-fun-btn hp-fun-btn--nav">Navigate</a>
            <a href="tel:<?php echo esc_attr(preg_replace('/\D/', '', $b->customer_phone)); ?>" class="hp-fun-btn hp-fun-btn--call">Call</a>
            <button type="button" class="hp-fun-btn hp-fun-btn--done hp-js-complete" data-id="<?php echo (int) $b->id; ?>">Done</button>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($hp_awaiting)) : ?>
    <section class="hp-fun-card hp-fun-awaiting">
        <h2 class="hp-fun-card-title"><span class="hp-fun-icon hp-fun-icon--warn"></span> Awaiting Action</h2>
        <?php foreach ($hp_awaiting as $b) : ?>
        <div class="hp-fun-awaiting-item" data-booking-id="<?php echo (int) $b->id; ?>">
            <p class="hp-fun-when"><?php echo esc_html(date_i18n('l g:i A', strtotime($b->pickup_datetime))); ?> &middot; <?php echo esc_html($b->customer_name); ?></p>
            <p class="hp-fun-address-line"><?php echo esc_html(mb_strimwidth($b->pickup_address ?: '-', 0, 45, '...')); ?></p>
            <div class="hp-fun-badge hp-fun-badge--unpaid">UNPAID</div>
            <p class="hp-fun-collect">Collect $<?php echo number_format((float) $b->price, 2); ?> on arrival.</p>
            <div class="hp-fun-actions hp-fun-actions--wrap">
                <button type="button" class="hp-fun-btn hp-fun-btn--stripe hp-js-stripe-collect" data-id="<?php echo (int) $b->id; ?>">Open Stripe &ndash; customer taps here</button>
                <button type="button" class="hp-fun-btn hp-fun-btn--tap hp-js-mark-tap" data-id="<?php echo (int) $b->id; ?>">Paid &ndash; Card (Tap)</button>
                <button type="button" class="hp-fun-btn hp-fun-btn--cash hp-js-mark-cash" data-id="<?php echo (int) $b->id; ?>">Paid &ndash; Cash</button>
                <a href="tel:<?php echo esc_attr(preg_replace('/\D/', '', $b->customer_phone)); ?>" class="hp-fun-btn hp-fun-btn--call">Call</a>
            </div>
        </div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>
    </div>

    <section class="hp-fun-card hp-fun-upcoming">
        <h2 class="hp-fun-card-title">All Bookings</h2>
        
        <!-- Filters -->
        <div class="hp-list-filters" style="margin-bottom: 20px;">
            <form method="get" class="hp-list-filter-form">
                <input type="hidden" name="page" value="hp-booking" />
                <div class="hp-filter-row" style="flex-wrap: wrap; gap: 10px;">
                    <label class="hp-filter-search">
                        <span class="hp-filter-label">Search</span>
                        <input type="search" name="search" value="<?php echo esc_attr($search); ?>" placeholder="Name, email, phone..." class="hp-input" />
                    </label>
                    <label class="hp-filter-field">
                        <span class="hp-filter-label">Status</span>
                        <select name="status" class="hp-input">
                            <option value="">All</option>
                            <option value="pending"   <?php selected($selected_status, 'pending');   ?>>Pending</option>
                            <option value="confirmed" <?php selected($selected_status, 'confirmed'); ?>>Confirmed</option>
                            <option value="completed" <?php selected($selected_status, 'completed'); ?>>Completed</option>
                            <option value="cancelled" <?php selected($selected_status, 'cancelled'); ?>>Cancelled</option>
                        </select>
                    </label>
                    <label class="hp-filter-field">
                        <span class="hp-filter-label">Sort</span>
                        <select name="sort" class="hp-input">
                            <option value="pickup_datetime" <?php selected($sort_by, 'pickup_datetime'); ?>>Date</option>
                            <option value="customer_name"   <?php selected($sort_by, 'customer_name');   ?>>Name</option>
                            <option value="price"           <?php selected($sort_by, 'price');           ?>>Price</option>
                            <option value="booking_status"  <?php selected($sort_by, 'booking_status');  ?>>Status</option>
                        </select>
                    </label>
                    <label class="hp-filter-field">
                        <span class="hp-filter-label">Order</span>
                        <select name="order" class="hp-input">
                            <option value="desc" <?php selected($order_dir, 'DESC'); ?>>Newest first</option>
                            <option value="asc"  <?php selected($order_dir, 'ASC');  ?>>Oldest first</option>
                        </select>
                    </label>
                    <div class="hp-filter-actions">
                        <button type="submit" class="button button-primary">Apply Filters</button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=hp-booking')); ?>" class="button">Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Bulk Actions -->
        <div class="tablenav top" style="margin: 0 0 10px;">
            <div class="alignleft actions bulkactions">
                <select id="hp-bulk-action-selector-top">
                    <option value="-1">Bulk Actions</option>
                    <option value="delete">Delete</option>
                </select>
                <button type="button" id="hp-do-bulk-action" class="button action">Apply</button>
            </div>
            <br class="clear">
        </div>

        <?php if (!empty($hp_upcoming)) : ?>
        <table class="hp-fun-table widefat striped">
            <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all-1">
                    </td>
                    <th>Ref</th>
                    <th>Customer</th>
                    <th>When</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($hp_upcoming as $b) :
                $pay_label = $b->payment_status;
                $pay_color = '#888';
                if ($b->payment_status === 'paid_online')    { $pay_label = 'Paid (Card)'; $pay_color = '#46b450'; }
                elseif ($b->payment_status === 'paid_tap')   { $pay_label = 'Paid (Tap)';  $pay_color = '#46b450'; }
                elseif ($b->payment_status === 'paid_cash')  { $pay_label = 'Paid (Cash)'; $pay_color = '#46b450'; }
                elseif ($b->payment_status === 'pay_at_pickup') { $pay_label = 'At Pickup'; $pay_color = '#0073aa'; }
                elseif ($b->payment_status === 'pending')    { $pay_label = 'Pending';      $pay_color = '#f0ad4e'; }
            ?>
                <tr id="booking-row-<?php echo intval($b->id); ?>">
                    <th scope="row" class="check-column">
                        <input type="checkbox" name="booking[]" class="hp-bulk-check" value="<?php echo intval($b->id); ?>">
                    </th>
                    <td><strong><?php echo esc_html($b->booking_reference); ?></strong></td>
                    <td>
                        <?php echo esc_html($b->customer_name); ?><br>
                        <small><?php echo esc_html(mb_strimwidth($b->pickup_address, 0, 30, '...')); ?></small>
                        <?php if (!empty($b->notes)): ?>
                        <br><small style="color: #C9A227; font-style: italic;">📝 <?php echo esc_html(mb_strimwidth($b->notes, 0, 40, '...')); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html(date_i18n('M j, g:i A', strtotime($b->pickup_datetime))); ?></td>
                    <td>$<?php echo esc_html(number_format((float) $b->price, 2)); ?></td>
                    <td><span class="hp-status-<?php echo esc_attr($b->booking_status); ?>"><?php echo esc_html($b->booking_status); ?></span></td>
                    <td><span style="color:<?php echo esc_attr($pay_color); ?>;font-weight:600;"><?php echo esc_html($pay_label); ?></span></td>
                    <td>
                        <button type="button" class="button button-small button-link-delete hp-delete-btn" data-id="<?php echo intval($b->id); ?>" style="color: #a00;">Delete</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php else : ?>
        <p>No bookings found.</p>
        <?php endif; ?>
    </section>
</div>

<!-- QR code payment modal (driver shows this to customer) -->
<div id="hp-pay-modal" class="hp-pay-modal" style="display:none;">
    <div class="hp-pay-modal-inner">
        <button type="button" id="hp-pay-close" class="hp-pay-close">&times;</button>

        <!-- Loading -->
        <div id="hp-pay-loading" class="hp-pay-view hp-pay-loading-view">
            <div class="hp-pay-spinner"></div>
            <div style="margin-top:16px;color:#646970;">Creating payment link…</div>
        </div>

        <!-- QR code view -->
        <div id="hp-pay-qr" class="hp-pay-view" style="display:none;">
            <div class="hp-pay-logo">Handsome Pete</div>
            <div class="hp-pay-amount" id="hp-pay-amount"></div>
            <div class="hp-pay-ref" id="hp-pay-ref"></div>
            <div class="hp-pay-customer" id="hp-pay-customer-name"></div>

            <div class="hp-pay-qr-box">
                <img id="hp-qr-img" src="" alt="Scan to pay" width="260" height="260">
            </div>

            <p class="hp-pay-instruction">Customer: scan with your phone camera to pay</p>

            <div class="hp-pay-link-row">
                <button type="button" id="hp-pay-copy" class="hp-pay-link-btn">📋 Copy payment link</button>
                <button type="button" id="hp-pay-share" class="hp-pay-link-btn" style="display:none;">📤 Share link</button>
            </div>

            <p class="hp-pay-hint">Apple Pay &amp; Google Pay available on customer's phone.<br>Payment updates automatically via webhook.</p>
        </div>
    </div>
</div>

<style>
/* ===== QR-code payment modal ===== */
.hp-pay-modal {
    position: fixed; inset: 0; z-index: 999999;
    background: #f5f6f7;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}
.hp-pay-modal-inner {
    max-width: 420px; margin: 0 auto; padding: 32px 20px 40px;
    min-height: 100vh; display: flex; flex-direction: column;
    position: relative;
}
.hp-pay-close {
    position: absolute; top: 8px; right: 8px;
    background: none; border: none; font-size: 28px;
    color: #646970; cursor: pointer; z-index: 10;
    width: 48px; height: 48px; display: flex;
    align-items: center; justify-content: center;
}
.hp-pay-view { display: flex; flex-direction: column; align-items: center; }

.hp-pay-logo {
    text-align: center; font-size: 18px; font-weight: 700;
    color: #1d2327; margin-bottom: 20px; letter-spacing: -0.02em;
}
.hp-pay-amount {
    text-align: center; font-size: 2.8rem; font-weight: 800;
    color: #1d2327; margin-bottom: 2px; line-height: 1.1;
}
.hp-pay-ref {
    text-align: center; font-size: 13px; color: #646970; margin-bottom: 2px;
}
.hp-pay-customer {
    text-align: center; font-size: 15px; color: #1d2327;
    font-weight: 600; margin-bottom: 24px;
}

.hp-pay-qr-box {
    background: #fff; border-radius: 16px; padding: 20px;
    box-shadow: 0 2px 12px rgba(0,0,0,.08);
    margin-bottom: 20px;
    display: flex; align-items: center; justify-content: center;
}
.hp-pay-qr-box img {
    display: block; image-rendering: pixelated;
    border-radius: 4px;
}

.hp-pay-instruction {
    text-align: center; font-size: 15px; font-weight: 600;
    color: #1d2327; margin: 0 0 20px;
    line-height: 1.4;
}

.hp-pay-link-row {
    display: flex; gap: 10px; width: 100%;
    margin-bottom: 24px;
}
.hp-pay-link-btn {
    flex: 1; padding: 14px 12px;
    background: #fff; color: #1d2327; border: 1px solid #dcdcde;
    border-radius: 8px; font-size: 14px; font-weight: 600;
    cursor: pointer; min-height: 48px;
    transition: background .15s;
}
.hp-pay-link-btn:active { background: #f0f0f1; }

.hp-pay-hint {
    text-align: center; font-size: 12px; color: #999;
    line-height: 1.5; margin: 0;
}

/* Loading spinner */
.hp-pay-loading-view {
    align-items: center; justify-content: center;
    padding-top: 120px; flex: 1;
}
.hp-pay-spinner {
    width: 40px; height: 40px;
    border: 4px solid #dcdcde; border-top-color: #635bff;
    border-radius: 50%;
    animation: hp-spin 0.8s linear infinite;
}
@keyframes hp-spin { to { transform: rotate(360deg); } }
</style>

<script>
jQuery(document).ready(function($) {
    'use strict';
    if (typeof phpBookingAdmin === 'undefined') {
        console.error('[HP Booking] phpBookingAdmin not found. Admin scripts may not be enqueued.');
        return;
    }
    var ajaxUrl = phpBookingAdmin.ajaxUrl, nonce = phpBookingAdmin.nonce;
    var currentPayUrl = '';

    function reload() { window.location.reload(); }

    $(document).on('click', '.hp-js-complete', function() {
        var $btn = $(this), id = $btn.data('id');
        $btn.prop('disabled', true).text('...');
        $.post(ajaxUrl, { action: 'hp_complete_booking', nonce: nonce, booking_id: id }).done(function(r) {
            if (r.success) reload(); else alert(r.data && r.data.message || 'Error');
        }).fail(function() { alert('Request failed.'); }).always(function() { $btn.prop('disabled', false).text('Done'); });
    });

    /* ============================================================
       Collect Payment — QR code flow
       ============================================================ */

    $(document).on('click', '.hp-js-stripe-collect', function() {
        var $btn = $(this), id = $btn.data('id');

        // Show modal with loading
        $('#hp-pay-modal').show();
        $('#hp-pay-qr').hide();
        $('#hp-pay-loading').show();
        $('body').css('overflow', 'hidden');

        // Create Stripe Checkout Session
        $.get(ajaxUrl, { action: 'hp_get_stripe_collect_url', nonce: nonce, booking_id: id }).done(function(r) {
            if (!r.success) {
                alert(r.data && r.data.message || 'Could not create payment link.');
                closePayModal();
                return;
            }

            var d = r.data;
            currentPayUrl = d.url;

            // Populate info
            $('#hp-pay-amount').text('CA$' + parseFloat(d.amount).toFixed(2));
            $('#hp-pay-ref').text(d.reference);
            $('#hp-pay-customer-name').text(d.customerName);

            // Generate QR code via API
            $('#hp-qr-img').attr('src', 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&margin=8&data=' + encodeURIComponent(d.url));

            // Show share button if Web Share API available (mobile)
            if (navigator.share) {
                $('#hp-pay-share').show();
            }

            // Switch to QR view
            $('#hp-pay-loading').hide();
            $('#hp-pay-qr').show();

        }).fail(function() {
            alert('Request failed.');
            closePayModal();
        });
    });

    // Copy link
    $('#hp-pay-copy').on('click', function() {
        var $btn = $(this);
        if (navigator.clipboard && currentPayUrl) {
            navigator.clipboard.writeText(currentPayUrl).then(function() {
                $btn.text('✓ Copied!');
                setTimeout(function() { $btn.text('📋 Copy payment link'); }, 2000);
            });
        } else {
            // Fallback
            var ta = document.createElement('textarea');
            ta.value = currentPayUrl;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            $btn.text('✓ Copied!');
            setTimeout(function() { $btn.text('📋 Copy payment link'); }, 2000);
        }
    });

    // Share link (mobile)
    $('#hp-pay-share').on('click', function() {
        if (navigator.share && currentPayUrl) {
            navigator.share({
                title: 'Pay for your ride',
                text: 'Tap to pay for your Handsome Pete ride:',
                url: currentPayUrl
            }).catch(function() {});
        }
    });

    // Close modal
    function closePayModal() {
        $('#hp-pay-modal').hide();
        $('body').css('overflow', '');
        currentPayUrl = '';
    }
    $('#hp-pay-close').on('click', closePayModal);

    $(document).on('click', '.hp-js-mark-tap', function() {
        var $btn = $(this), id = $btn.data('id');
        $btn.prop('disabled', true);
        $.post(ajaxUrl, { action: 'hp_mark_paid_tap', nonce: nonce, booking_id: id }).done(function(r) {
            if (r.success) reload(); else alert(r.data && r.data.message || 'Error');
        }).fail(function() { alert('Request failed.'); }).always(function() { $btn.prop('disabled', false); });
    });

    $(document).on('click', '.hp-js-mark-cash', function() {
        var $btn = $(this), id = $btn.data('id');
        $btn.prop('disabled', true);
        $.post(ajaxUrl, { action: 'hp_mark_paid_cash', nonce: nonce, booking_id: id }).done(function(r) {
            if (r.success) reload(); else alert(r.data && r.data.message || 'Error');
        }).fail(function() { alert('Request failed.'); }).always(function() { $btn.prop('disabled', false); });
    });

    // Bulk actions
    $('#cb-select-all-1').on('change', function() {
        $('.hp-bulk-check').prop('checked', this.checked);
    });

    $('#hp-do-bulk-action').on('click', function() {
        var action = $('#hp-bulk-action-selector-top').val();
        if (action !== 'delete') return;

        var ids = [];
        $('.hp-bulk-check:checked').each(function() {
            ids.push($(this).val());
        });

        if (ids.length === 0) {
            alert('Please select at least one booking.');
            return;
        }

        if (!confirm('Permanent delete ' + ids.length + ' bookings?')) return;

        var $btn = $(this);
        $btn.prop('disabled', true).text('...');

        $.post(ajaxUrl, { 
            action: 'hp_bulk_delete_bookings', 
            nonce: nonce, 
            booking_ids: ids 
        }).done(function(r) {
            if (r.success) {
                reload();
            } else {
                alert(r.data && r.data.message || 'Error');
            }
        }).fail(function() {
            alert('Request failed.');
        }).always(function() {
            $btn.prop('disabled', false).text('Apply');
        });
    });

    // Delete individual booking
    $(document).on('click', '.hp-delete-btn', function() {
        var $btn = $(this), id = $btn.data('id');
        if (!confirm('Delete this booking?')) return;
        $btn.prop('disabled', true);
        $.post(ajaxUrl, { action: 'hp_delete_booking', nonce: nonce, booking_id: id }).done(function(r) {
            if (r.success) { $('#booking-row-' + id).fadeOut(300); } else { alert(r.data && r.data.message || 'Error'); }
        }).fail(function() { alert('Request failed.'); }).always(function() { $btn.prop('disabled', false); });
    });
});
</script>


