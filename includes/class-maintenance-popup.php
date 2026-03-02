<?php
/**
 * Maintenance Popup
 * Branded popup for visitors when site is under maintenance. Call-to-action: phone booking.
 */

if (!defined('ABSPATH')) {
    exit;
}

class HP_Booking_Maintenance_Popup {

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
        add_action('wp_footer', array($this, 'render_popup'));
    }

    /**
     * Check if popup should show on current page
     */
    public function should_show_on_page() {
        if (is_admin() || wp_doing_ajax()) {
            return false;
        }
        $enabled = $this->db->get_setting('maintenance_popup_enabled', '0');
        if ($enabled !== '1' && $enabled !== 'on') {
            return false;
        }
        $scope = $this->db->get_setting('maintenance_popup_scope', 'all');
        $page_ids = $this->db->get_setting('maintenance_popup_page_ids', '');
        if ($scope === 'home') {
            return is_front_page();
        }
        if ($scope === 'specific' && !empty($page_ids)) {
            $ids = array_map('intval', array_filter(explode(',', $page_ids)));
            if (empty($ids)) return false;
            return is_page($ids);
        }
        return true; // all
    }

    /**
     * Output popup HTML and script
     */
    public function render_popup() {
        if (!$this->should_show_on_page()) {
            return;
        }
        $message = $this->db->get_setting('maintenance_popup_message', 'Website is currently under maintenance for improvements. Please call Handsome Pete directly at 905-746-7547 to book a ride! Thank you for your support and understanding.');
        $phone = $this->db->get_setting('maintenance_popup_phone', '905-746-7547');
        $phone_link = 'tel:' . preg_replace('/\D/', '', $phone);
        ?>
        <div id="hp-maintenance-popup" class="hp-maintenance-popup" role="dialog" aria-labelledby="hp-maintenance-popup-title" aria-modal="true" style="display:none;">
            <div class="hp-maintenance-popup-backdrop"></div>
            <div class="hp-maintenance-popup-box">
                <button type="button" class="hp-maintenance-popup-close" aria-label="Close">&times;</button>
                <div class="hp-maintenance-popup-content">
                    <h2 id="hp-maintenance-popup-title" class="hp-maintenance-popup-title">Handsome Pete</h2>
                    <p class="hp-maintenance-popup-message"><?php echo esc_html($message); ?></p>
                    <p class="hp-maintenance-popup-phone">
                        <a href="<?php echo esc_attr($phone_link); ?>" class="hp-maintenance-popup-phone-link"><?php echo esc_html($phone); ?></a>
                    </p>
                </div>
            </div>
        </div>
        <style>
        .hp-maintenance-popup { position: fixed; inset: 0; z-index: 999999; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .hp-maintenance-popup-backdrop { position: absolute; inset: 0; background: rgba(0,0,0,0.5); }
        .hp-maintenance-popup-box { position: relative; background: #fff; border-radius: 12px; padding: 28px 32px; max-width: 440px; width: 100%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); border: 2px solid #2E4A3E; }
        .hp-maintenance-popup-close { position: absolute; top: 12px; right: 12px; background: none; border: none; font-size: 28px; line-height: 1; cursor: pointer; color: #666; padding: 4px; }
        .hp-maintenance-popup-close:hover { color: #2E4A3E; }
        .hp-maintenance-popup-title { margin: 0 0 16px; font-size: 24px; color: #2E4A3E; }
        .hp-maintenance-popup-message { margin: 0 0 16px; font-size: 16px; line-height: 1.6; color: #333; }
        .hp-maintenance-popup-phone { margin: 0; }
        .hp-maintenance-popup-phone-link { font-size: 20px; font-weight: 600; color: #2E4A3E; text-decoration: none; }
        .hp-maintenance-popup-phone-link:hover { text-decoration: underline; }
        </style>
        <script>
        (function() {
            var popup = document.getElementById('hp-maintenance-popup');
            if (!popup) return;

            function show() { popup.style.display = 'flex'; }
            function hide() { popup.style.display = 'none'; sessionStorage.setItem('hp_maint_closed', '1'); }

            function shouldShowOnLoad() {
                try { return !sessionStorage.getItem('hp_maint_closed'); } catch(e) { return true; }
            }

            popup.querySelector('.hp-maintenance-popup-close').addEventListener('click', function() { hide(); });
            popup.querySelector('.hp-maintenance-popup-backdrop').addEventListener('click', function() { hide(); });

            document.addEventListener('click', function(e) {
                var t = e.target;
                while (t && t !== document) {
                    var href = (t.href || '').toString();
                    var text = (t.textContent || '').toLowerCase();
                    var cls = (t.className || '').toLowerCase();
                    var isBookLink = href.indexOf('booking') !== -1 || href.indexOf('/book') !== -1;
                    var isBookText = /book\s*now|book\s*a\s*ride|reserve|schedule\s*ride|book\s*online/i.test(text);
                    var isBookClass = cls.indexOf('book-now') !== -1 || cls.indexOf('hp-book-now') !== -1;
                    var isBookData = t.getAttribute && t.getAttribute('data-hp-book-now') !== null;
                    if (isBookLink || (isBookText && (t.tagName === 'A' || t.tagName === 'BUTTON')) || isBookClass || isBookData) {
                        e.preventDefault();
                        e.stopPropagation();
                        show();
                        sessionStorage.removeItem('hp_maint_closed');
                        return;
                    }
                    t = t.parentElement;
                }
            }, true);

            if (shouldShowOnLoad()) show();
        })();
        </script>
        <?php
    }
}
