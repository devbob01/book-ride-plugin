<?php
/**
 * Admin bookings list — search, filter, sort, full actions.
 * Desktop: full table. Mobile: card list.
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$db = HP_Booking_Database::get_instance();
$bookings_table = $db->get_table('bookings');

$stripe_test = $db->get_setting('stripe_test_mode', '1');
$is_test     = ($stripe_test === '1' || $stripe_test === 'on');

// --- Params ---
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
    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$bookings_table} WHERE {$sql_where} ORDER BY {$order_clause} LIMIT 200",
        $values
    ));
} else {
    $bookings = $wpdb->get_results(
        "SELECT * FROM {$bookings_table} ORDER BY {$order_clause} LIMIT 200"
    );
}
?>

<div class="wrap hp-bookings-list-wrap">
    <h1>
        All Bookings
        <?php if ($is_test): ?>
            <span class="hp-mode-badge hp-mode-badge--test">TEST MODE</span>
        <?php else: ?>
            <span class="hp-mode-badge hp-mode-badge--live">LIVE</span>
        <?php endif; ?>
    </h1>
    <p class="description">
        <a href="<?php echo esc_url(admin_url('admin.php?page=hp-booking')); ?>">Dashboard</a> &middot;
        View and manage all bookings. Search, filter, and sort below.
    </p>

    <!-- Filters (desktop & mobile) -->
    <div class="hp-list-filters">
        <form method="get" class="hp-list-filter-form">
            <input type="hidden" name="page" value="hp-booking-list" />
            <div class="hp-filter-row">
                <label class="hp-filter-search">
                    <span class="hp-filter-label">Search</span>
                    <input type="search" name="search" value="<?php echo esc_attr($search); ?>" placeholder="Name, email, phone, reference..." class="hp-input" />
                </label>
                <label class="hp-filter-field">
                    <span class="hp-filter-label">From</span>
                    <input type="date" name="date_start" value="<?php echo esc_attr($selected_date_start); ?>" class="hp-input" />
                </label>
                <label class="hp-filter-field">
                    <span class="hp-filter-label">To</span>
                    <input type="date" name="date_end" value="<?php echo esc_attr($selected_date_end); ?>" class="hp-input" />
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
                    <span class="hp-filter-label">Payment</span>
                    <select name="payment" class="hp-input">
                        <option value="">All</option>
                        <option value="pending"     <?php selected($selected_payment, 'pending');     ?>>Pending</option>
                        <option value="paid_online" <?php selected($selected_payment, 'paid_online'); ?>>Paid (Card)</option>
                        <option value="paid_tap"    <?php selected($selected_payment, 'paid_tap');    ?>>Paid (Tap)</option>
                        <option value="paid_cash"   <?php selected($selected_payment, 'paid_cash');   ?>>Paid (Cash)</option>
                    </select>
                </label>
                <label class="hp-filter-field">
                    <span class="hp-filter-label">Sort</span>
                    <select name="sort" class="hp-input">
                        <option value="pickup_datetime" <?php selected($sort_by, 'pickup_datetime'); ?>>Date</option>
                        <option value="customer_name"   <?php selected($sort_by, 'customer_name');   ?>>Name</option>
                        <option value="price"           <?php selected($sort_by, 'price');           ?>>Price</option>
                        <option value="booking_status"  <?php selected($sort_by, 'booking_status');  ?>>Status</option>
                        <option value="payment_status"  <?php selected($sort_by, 'payment_status');  ?>>Payment</option>
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
                    <button type="submit" class="button button-primary">Apply</button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=hp-booking-list')); ?>" class="button">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Bulk actions -->
    <div class="hp-bulk-actions" style="margin-bottom: 15px;">
        <button type="button" id="hp-bulk-delete-btn" class="button button-secondary" style="color: #b32d2e; border-color: #b32d2e;" disabled>Delete Selected</button>
    </div>

    <!-- Desktop: table -->
    <div class="hp-list-desktop">
        <table class="wp-list-table widefat fixed striped hp-bookings-table">
            <thead>
                <tr>
                    <th style="width: 30px;"><input type="checkbox" id="hp-bulk-select-all" /></th>
                    <th>Reference</th>
                    <th>Passenger</th>
                    <th>Contact</th>
                    <th>Pickup</th>
                    <th>Destination</th>
                    <th>Date &amp; Time</th>
                    <th>Distance</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($bookings)) : ?>
                <?php foreach ($bookings as $b) :
                    $pay_label = $b->payment_status;
                    $pay_color = '#888';
                    if ($b->payment_status === 'paid_online')    { $pay_label = 'Paid (Card)'; $pay_color = '#46b450'; }
                    elseif ($b->payment_status === 'paid_tap')   { $pay_label = 'Paid (Tap)';  $pay_color = '#46b450'; }
                    elseif ($b->payment_status === 'paid_cash')  { $pay_label = 'Paid (Cash)'; $pay_color = '#46b450'; }
                    elseif ($b->payment_status === 'pay_at_pickup') { $pay_label = 'At Pickup'; $pay_color = '#0073aa'; }
                    elseif ($b->payment_status === 'pending')    { $pay_label = 'Pending';      $pay_color = '#f0ad4e'; }
                ?>
                <tr id="booking-row-<?php echo intval($b->id); ?>">
                    <td><input type="checkbox" class="hp-booking-checkbox" value="<?php echo intval($b->id); ?>" /></td>
                    <td><strong><?php echo esc_html($b->booking_reference); ?></strong></td>
                    <td><?php echo esc_html($b->customer_name); ?></td>
                    <td class="hp-contact-cell">
                        <a href="tel:<?php echo esc_attr(preg_replace('/\D/', '', $b->customer_phone)); ?>"><?php echo esc_html($b->customer_phone); ?></a><br>
                        <a href="mailto:<?php echo esc_attr($b->customer_email); ?>"><?php echo esc_html($b->customer_email); ?></a>
                    </td>
                    <td><?php echo esc_html(mb_strimwidth($b->pickup_address, 0, 35, '...')); ?></td>
                    <td><?php echo esc_html(mb_strimwidth($b->destination_address, 0, 35, '...')); ?></td>
                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($b->pickup_datetime))); ?></td>
                    <td><?php echo esc_html($b->distance_km); ?> km</td>
                    <td>$<?php echo esc_html(number_format((float) $b->price, 2)); ?></td>
                    <td><span class="hp-status-<?php echo esc_attr($b->booking_status); ?>"><?php echo esc_html($b->booking_status); ?></span></td>
                    <td><span style="color:<?php echo esc_attr($pay_color); ?>;font-weight:600;"><?php echo esc_html($pay_label); ?></span></td>
                    <td class="hp-actions-cell">
                        <?php if ($b->booking_status === 'pending') : ?>
                            <button type="button" class="button button-small hp-approve-btn" data-id="<?php echo intval($b->id); ?>">Approve</button>
                            <button type="button" class="button button-small hp-decline-btn" data-id="<?php echo intval($b->id); ?>">Decline</button>
                        <?php endif; ?>
                        <?php if (in_array($b->booking_status, array('confirmed', 'pending'), true)) : ?>
                            <button type="button" class="button button-small hp-complete-btn" data-id="<?php echo intval($b->id); ?>">Complete</button>
                            <button type="button" class="button button-small hp-cancel-btn" data-id="<?php echo intval($b->id); ?>">Cancel</button>
                        <?php endif; ?>
                        <button type="button" class="button button-small button-link-delete hp-delete-btn" data-id="<?php echo intval($b->id); ?>">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="12">No bookings found. Try adjusting search or filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile: cards -->
    <div class="hp-list-mobile">
        <?php if (!empty($bookings)) : ?>
            <?php foreach ($bookings as $b) :
                $pay_label = $b->payment_status;
                if ($b->payment_status === 'paid_online')    { $pay_label = 'Paid (Card)'; }
                elseif ($b->payment_status === 'paid_tap')   { $pay_label = 'Paid (Tap)'; }
                elseif ($b->payment_status === 'paid_cash')  { $pay_label = 'Paid (Cash)'; }
                elseif ($b->payment_status === 'pending')    { $pay_label = 'Pending'; }
            ?>
            <div class="hp-booking-card" id="booking-card-<?php echo intval($b->id); ?>">
                <div class="hp-booking-card-header">
                    <strong>
                        <input type="checkbox" class="hp-booking-checkbox" value="<?php echo intval($b->id); ?>" style="margin-right: 8px;" />
                        <?php echo esc_html($b->booking_reference); ?>
                    </strong>
                    <span class="hp-status-<?php echo esc_attr($b->booking_status); ?>"><?php echo esc_html($b->booking_status); ?></span>
                </div>
                <p class="hp-booking-card-name"><?php echo esc_html($b->customer_name); ?></p>
                <p class="hp-booking-card-contact">
                    <a href="tel:<?php echo esc_attr(preg_replace('/\D/', '', $b->customer_phone)); ?>"><?php echo esc_html($b->customer_phone); ?></a><br>
                    <a href="mailto:<?php echo esc_attr($b->customer_email); ?>"><?php echo esc_html($b->customer_email); ?></a>
                </p>
                <p class="hp-booking-card-route"><?php echo esc_html(mb_strimwidth($b->pickup_address, 0, 40, '...')); ?> → <?php echo esc_html(mb_strimwidth($b->destination_address, 0, 30, '...')); ?></p>
                <p class="hp-booking-card-meta"><?php echo esc_html(date_i18n('M j, g:i A', strtotime($b->pickup_datetime))); ?> &middot; $<?php echo esc_html(number_format((float) $b->price, 2)); ?> &middot; <?php echo esc_html($pay_label); ?></p>
                <div class="hp-booking-card-actions">
                    <?php if ($b->booking_status === 'pending') : ?>
                        <button type="button" class="button button-small hp-approve-btn" data-id="<?php echo intval($b->id); ?>">Approve</button>
                        <button type="button" class="button button-small hp-decline-btn" data-id="<?php echo intval($b->id); ?>">Decline</button>
                    <?php endif; ?>
                    <?php if (in_array($b->booking_status, array('confirmed', 'pending'), true)) : ?>
                        <button type="button" class="button button-small hp-complete-btn" data-id="<?php echo intval($b->id); ?>">Complete</button>
                        <button type="button" class="button button-small hp-cancel-btn" data-id="<?php echo intval($b->id); ?>">Cancel</button>
                    <?php endif; ?>
                    <button type="button" class="button button-small button-link-delete hp-delete-btn" data-id="<?php echo intval($b->id); ?>">Delete</button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else : ?>
            <p class="hp-no-results">No bookings found. Try adjusting search or filters.</p>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var A = phpBookingAdmin;
    if (!A) return;

    function updateRow(id, statusLabel, statusClass) {
        $('#booking-row-' + id).find('[class^="hp-status-"]').attr('class', statusClass).text(statusLabel).end()
            .find('.hp-approve-btn, .hp-decline-btn, .hp-complete-btn, .hp-cancel-btn').remove();
        $('#booking-card-' + id).find('[class^="hp-status-"]').attr('class', statusClass).text(statusLabel).end()
            .find('.hp-approve-btn, .hp-decline-btn, .hp-complete-btn, .hp-cancel-btn').remove();
    }
    function removeRow(id) {
        $('#booking-row-' + id).fadeOut(300, function() { $(this).remove(); });
        $('#booking-card-' + id).fadeOut(300, function() { $(this).remove(); });
    }

    function toggleBulkDeleteBtn() {
        var selectedCount = $('.hp-booking-checkbox:checked').length;
        $('#hp-bulk-delete-btn').prop('disabled', selectedCount === 0);
    }

    $('#hp-bulk-select-all').on('change', function() {
        var isChecked = $(this).is(':checked');
        $('.hp-booking-checkbox').prop('checked', isChecked);
        toggleBulkDeleteBtn();
    });

    $(document).on('change', '.hp-booking-checkbox', function() {
        var val = $(this).val();
        var isChecked = $(this).is(':checked');
        // Sync desktop and mobile checkboxes
        $('.hp-booking-checkbox[value="'+val+'"]').prop('checked', isChecked);

        // Check if all are checked (considering duplicates due to responsive views)
        var totalUniqueCount = new Set($('.hp-booking-checkbox').map(function() { return $(this).val(); }).get()).size;
        var checkedUniqueCount = new Set($('.hp-booking-checkbox:checked').map(function() { return $(this).val(); }).get()).size;
        
        $('#hp-bulk-select-all').prop('checked', totalUniqueCount > 0 && totalUniqueCount === checkedUniqueCount);
        toggleBulkDeleteBtn();
    });

    $('#hp-bulk-delete-btn').on('click', function() {
        var selectedIds = [];
        $('.hp-booking-checkbox:checked').each(function() {
            var val = $(this).val();
            if (selectedIds.indexOf(val) === -1) {
                selectedIds.push(val);
            }
        });

        if (selectedIds.length === 0) return;

        if (!confirm('Permanently delete ' + selectedIds.length + ' booking(s)? This cannot be undone.')) return;

        var $btn = $(this);
        $btn.prop('disabled', true).text('Deleting...');

        $.post(A.ajaxUrl, { action: 'hp_bulk_delete_bookings', nonce: A.nonce, booking_ids: selectedIds })
            .done(function(r) { 
                if (r.success) {
                    $.each(selectedIds, function(i, id) {
                        removeRow(id);
                    });
                    $('#hp-bulk-select-all').prop('checked', false);
                    setTimeout(toggleBulkDeleteBtn, 350); // After fadeOut
                } else {
                    alert(r.data && r.data.message || 'Error'); 
                    $btn.prop('disabled', false).text('Delete Selected');
                }
            })
            .fail(function() { 
                alert('Request failed.'); 
                $btn.prop('disabled', false).text('Delete Selected');
            })
            .always(function() { 
                $btn.text('Delete Selected'); 
            });
    });

    $(document).on('click', '.hp-approve-btn', function() {
        var $btn = $(this), id = $btn.data('id');
        $btn.prop('disabled', true).text('...');
        $.post(A.ajaxUrl, { action: 'hp_confirm_booking', nonce: A.nonce, booking_id: id })
            .done(function(r) { if (r.success) updateRow(id, 'confirmed', 'hp-status-confirmed'); else alert(r.data && r.data.message || 'Error'); })
            .fail(function() { alert('Request failed.'); }).always(function() { $btn.prop('disabled', false).text('Approve'); });
    });
    $(document).on('click', '.hp-decline-btn', function() {
        if (!confirm('Decline this booking? The booking will be cancelled.')) return;
        var $btn = $(this), id = $btn.data('id');
        $btn.prop('disabled', true).text('...');
        $.post(A.ajaxUrl, { action: 'hp_cancel_booking', nonce: A.nonce, booking_id: id })
            .done(function(r) { if (r.success) updateRow(id, 'cancelled', 'hp-status-cancelled'); else alert(r.data && r.data.message || 'Error'); })
            .fail(function() { alert('Request failed.'); }).always(function() { $btn.prop('disabled', false).text('Decline'); });
    });
    $(document).on('click', '.hp-complete-btn', function() {
        var $btn = $(this), id = $btn.data('id');
        $btn.prop('disabled', true).text('...');
        $.post(A.ajaxUrl, { action: 'hp_complete_booking', nonce: A.nonce, booking_id: id })
            .done(function(r) { if (r.success) updateRow(id, 'completed', 'hp-status-completed'); else alert(r.data && r.data.message || 'Error'); })
            .fail(function() { alert('Request failed.'); }).always(function() { $btn.prop('disabled', false).text('Complete'); });
    });
    $(document).on('click', '.hp-cancel-btn', function() {
        if (!confirm('Cancel this booking?')) return;
        var $btn = $(this), id = $btn.data('id');
        $btn.prop('disabled', true).text('...');
        $.post(A.ajaxUrl, { action: 'hp_cancel_booking', nonce: A.nonce, booking_id: id })
            .done(function(r) { if (r.success) updateRow(id, 'cancelled', 'hp-status-cancelled'); else alert(r.data && r.data.message || 'Error'); })
            .fail(function() { alert('Request failed.'); }).always(function() { $btn.prop('disabled', false).text('Cancel'); });
    });
    $(document).on('click', '.hp-delete-btn', function() {
        if (!confirm('Permanently delete this booking? This cannot be undone.')) return;
        var $btn = $(this), id = $btn.data('id');
        $btn.prop('disabled', true).text('...');
        $.post(A.ajaxUrl, { action: 'hp_delete_booking', nonce: A.nonce, booking_id: id })
            .done(function(r) { if (r.success) removeRow(id); else alert(r.data && r.data.message || 'Error'); })
            .fail(function() { alert('Request failed.'); }).always(function() { $btn.prop('disabled', false).text('Delete'); });
    });
});
</script>
