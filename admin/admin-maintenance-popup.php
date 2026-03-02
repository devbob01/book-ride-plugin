<?php
/**
 * Maintenance Popup settings page
 */

if (!defined('ABSPATH')) {
    exit;
}

$db = HP_Booking_Database::get_instance();

if (isset($_POST['hp_save_maintenance_popup']) && check_admin_referer('hp_save_maintenance_popup')) {
    $enabled = isset($_POST['maintenance_popup_enabled']) ? '1' : '0';
    $db->update_setting('maintenance_popup_enabled', $enabled);
    $db->update_setting('maintenance_popup_message', sanitize_textarea_field($_POST['maintenance_popup_message'] ?? ''));
    $db->update_setting('maintenance_popup_phone', sanitize_text_field($_POST['maintenance_popup_phone'] ?? '905-746-7547'));
    $db->update_setting('maintenance_popup_scope', sanitize_text_field($_POST['maintenance_popup_scope'] ?? 'all'));
    $page_ids = isset($_POST['maintenance_popup_page_ids']) ? implode(',', array_map('intval', (array) $_POST['maintenance_popup_page_ids'])) : '';
    $db->update_setting('maintenance_popup_page_ids', $page_ids);
    echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
}

$enabled = $db->get_setting('maintenance_popup_enabled', '0');
$message = $db->get_setting('maintenance_popup_message', 'Website is currently under maintenance for improvements. Please call Handsome Pete directly at 905-746-7547 to book a ride! Thank you for your support and understanding.');
$phone = $db->get_setting('maintenance_popup_phone', '905-746-7547');
$scope = $db->get_setting('maintenance_popup_scope', 'all');
$page_ids = $db->get_setting('maintenance_popup_page_ids', '');
$selected_ids = $page_ids ? array_map('intval', array_filter(explode(',', $page_ids))) : array();

$pages = get_pages(array('sort_column' => 'post_title'));
?>

<div class="wrap">
    <h1>Maintenance Popup</h1>
    <p class="description">Show a branded popup to visitors when the site is under maintenance. Use during bugs or improvements so people know to call directly.</p>

    <form method="post" action="">
        <?php wp_nonce_field('hp_save_maintenance_popup'); ?>

        <table class="form-table">
            <tr>
                <th>Status</th>
                <td>
                    <label>
                        <input type="checkbox" name="maintenance_popup_enabled" value="1" <?php checked($enabled, '1'); ?> />
                        <strong>Activate popup</strong> — Show the maintenance message to visitors
                    </label>
                    <p class="description">When active, new and returning visitors see the popup. They can close it with X to browse, but it appears again when they click any "Book Now" button.</p>
                </td>
            </tr>
            <tr>
                <th><label for="maintenance_popup_message">Message</label></th>
                <td>
                    <textarea name="maintenance_popup_message" id="maintenance_popup_message" rows="4" class="large-text"><?php echo esc_textarea($message); ?></textarea>
                </td>
            </tr>
            <tr>
                <th><label for="maintenance_popup_phone">Phone number</label></th>
                <td>
                    <input type="text" name="maintenance_popup_phone" id="maintenance_popup_phone" value="<?php echo esc_attr($phone); ?>" class="regular-text" />
                    <p class="description">Clickable in the popup. Include area code (e.g. 905-746-7547).</p>
                </td>
            </tr>
            <tr>
                <th>Show on pages</th>
                <td>
                    <fieldset>
                        <label><input type="radio" name="maintenance_popup_scope" value="all" <?php checked($scope, 'all'); ?> /> All pages</label><br>
                        <label><input type="radio" name="maintenance_popup_scope" value="home" <?php checked($scope, 'home'); ?> /> Homepage only</label><br>
                        <label><input type="radio" name="maintenance_popup_scope" value="specific" <?php checked($scope, 'specific'); ?> /> Specific pages</label>
                    </fieldset>
                    <div id="hp-maintenance-page-list" style="<?php echo $scope !== 'specific' ? 'display:none;' : ''; ?> margin-top: 12px;">
                        <p class="description">Select the pages where the popup should appear:</p>
                        <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; background: #fff;">
                            <?php foreach ($pages as $p) : ?>
                                <label style="display: block; margin-bottom: 6px;">
                                    <input type="checkbox" name="maintenance_popup_page_ids[]" value="<?php echo esc_attr($p->ID); ?>" <?php echo in_array($p->ID, $selected_ids) ? 'checked' : ''; ?> />
                                    <?php echo esc_html($p->post_title); ?> (<?php echo esc_html($p->post_name); ?>)
                                </label>
                            <?php endforeach; ?>
                            <?php if (empty($pages)) : ?>
                                <p class="description">No pages found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="hp_save_maintenance_popup" class="button button-primary" value="Save" />
        </p>
    </form>
</div>

<script>
jQuery(function($) {
    $('input[name="maintenance_popup_scope"]').on('change', function() {
        $('#hp-maintenance-page-list').toggle($(this).val() === 'specific');
    });
});
</script>
