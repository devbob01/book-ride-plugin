<?php
/**
 * Operating Hours Gate
 * Intercepts #book-ride button clicks when outside operating hours and shows a popup instead of navigating.
 * Operating = availability-based (default unblocked hours, extensions, blocks).
 */

if (!defined('ABSPATH')) {
    exit;
}

class HP_Booking_Operating_Hours_Gate {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_footer', array($this, 'render_gate'), 5);
    }

    /**
     * Output popup markup and script for #book-ride interception.
     * Runs on all frontend pages (not admin).
     */
    public function render_gate() {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }
        ?>
        <div id="hp-operating-hours-popup" class="hp-operating-hours-popup" role="dialog" aria-labelledby="hp-operating-hours-popup-title" aria-modal="true" style="display:none;">
            <div class="hp-operating-hours-popup-backdrop"></div>
            <div class="hp-operating-hours-popup-box">
                <button type="button" class="hp-operating-hours-popup-close" aria-label="Close">&times;</button>
                <div class="hp-operating-hours-popup-content">
                    <h2 id="hp-operating-hours-popup-title" class="hp-operating-hours-popup-title">Handsome Pete</h2>
                    <p class="hp-operating-hours-popup-message"></p>
                    <p class="hp-operating-hours-popup-phone"></p>
                </div>
            </div>
        </div>
        <style>
        .hp-operating-hours-popup { position: fixed; inset: 0; z-index: 999999; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .hp-operating-hours-popup-backdrop { position: absolute; inset: 0; background: rgba(0,0,0,0.5); }
        .hp-operating-hours-popup-box { position: relative; background: #fff; border-radius: 12px; padding: 28px 32px; max-width: 440px; width: 100%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); border: 2px solid #2E4A3E; }
        .hp-operating-hours-popup-close { position: absolute; top: 12px; right: 12px; background: none; border: none; font-size: 28px; line-height: 1; cursor: pointer; color: #666; padding: 4px; }
        .hp-operating-hours-popup-close:hover { color: #2E4A3E; }
        .hp-operating-hours-popup-title { margin: 0 0 16px; font-size: 24px; color: #2E4A3E; }
        .hp-operating-hours-popup-message { margin: 0 0 16px; font-size: 16px; line-height: 1.6; color: #333; }
        .hp-operating-hours-popup-phone { margin: 0; }
        .hp-operating-hours-popup-phone-link { font-size: 20px; font-weight: 600; color: #2E4A3E; text-decoration: none; }
        .hp-operating-hours-popup-phone-link:hover { text-decoration: underline; }
        </style>
        <script>
        (function() {
            var apiUrl = <?php echo json_encode(esc_url_raw(rest_url('hp-booking/v1/operating-status'))); ?>;
            var popup = document.getElementById('hp-operating-hours-popup');
            if (!popup) return;

            function showPopup(message, phone) {
                var msgEl = popup.querySelector('.hp-operating-hours-popup-message');
                var phoneEl = popup.querySelector('.hp-operating-hours-popup-phone');
                if (msgEl) msgEl.textContent = message || '';
                if (phoneEl && phone) {
                    var link = document.createElement('a');
                    link.href = 'tel:' + (phone.replace(/\D/g, ''));
                    link.className = 'hp-operating-hours-popup-phone-link';
                    link.textContent = phone;
                    phoneEl.innerHTML = '';
                    phoneEl.appendChild(link);
                }
                popup.style.display = 'flex';
            }

            function hidePopup() {
                popup.style.display = 'none';
            }

            popup.querySelector('.hp-operating-hours-popup-close').addEventListener('click', hidePopup);
            popup.querySelector('.hp-operating-hours-popup-backdrop').addEventListener('click', hidePopup);

            function handleBookRideClick(e) {
                var target = e.target;
                while (target && target !== document) {
                    if (target.id === 'book-ride') {
                        e.preventDefault();
                        e.stopPropagation();
                        fetch(apiUrl)
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                if (data.operating) {
                                    var el = target.tagName === 'A' ? target : target.closest('a');
                                    var href = (el && el.href) ? el.href : (target.href || (target.querySelector && target.querySelector('a') && target.querySelector('a').href));
                                    if (href) window.location.href = href;
                                } else {
                                    showPopup(data.message || 'Please call to book outside operating hours.', data.phone || '905-746-7547');
                                }
                            })
                            .catch(function() {
                                showPopup('Unable to check availability. Please call 905-746-7547 to book.', '905-746-7547');
                            });
                        return;
                    }
                    target = target.parentElement;
                }
            }

            document.addEventListener('click', handleBookRideClick, true);
        })();
        </script>
        <?php
    }
}
