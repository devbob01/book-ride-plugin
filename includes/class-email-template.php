<?php
/**
 * Email Template for Handsome Pete Booking System
 * 
 * Generates branded HTML confirmation emails.
 */

if (!defined('ABSPATH')) {
    exit;
}

class HP_Booking_Email_Template {

    /**
     * Logo URL
     */
    private static $logo_url = 'http://handsomepete.ca/wp-content/uploads/2026/01/CONCEPT_03_PNG-scaled.png';

    /**
     * Brand colors
     */
    private static $colors = array(
        'primary'    => '#2E4A3E',
        'accent'     => '#86A98D',
        'gold'       => '#C9A227',
        'bg'         => '#F5F7F5',
        'white'      => '#ffffff',
        'text'       => '#2E4A3E',
        'text_light' => '#5c7268',
        'border'     => '#d4ddd8',
    );

    /**
     * Generate the full HTML confirmation email
     */
    public static function render_confirmation_email($booking) {
        $c = self::$colors;
        $logo = self::$logo_url;

        $pickup_date = date('l, F j, Y', strtotime($booking->pickup_datetime));
        $pickup_time = date('g:i A', strtotime($booking->pickup_datetime));
        $price = number_format((float)$booking->price, 2);
        $distance = number_format((float)$booking->distance_km, 2);
        $booking_ref = esc_html($booking->booking_reference);
        $pickup_addr = esc_html($booking->pickup_address);
        $dest_addr = esc_html($booking->destination_address);
        $customer_name = !empty($booking->customer_name) ? esc_html($booking->customer_name) : 'Valued Customer';
        $notes = !empty($booking->notes) ? esc_html($booking->notes) : '';

        // Payment status
        if ($booking->payment_status === 'paid_online') {
            $payment_method = 'Paid Online (Credit/Debit Card)';
            $payment_icon = '&#10003;';
            $payment_color = '#27ae60';
        } elseif ($booking->payment_status === 'paid_tap') {
            $payment_method = 'Pay on Pickup (Tap to Pay)';
            $payment_icon = '&#10003;';
            $payment_color = '#27ae60';
        } elseif ($booking->payment_status === 'paid_cash') {
            $payment_method = 'Paid Cash on Pickup';
            $payment_icon = '&#10003;';
            $payment_color = '#27ae60';
        } else {
            $payment_method = 'Pay on Pickup (Tap to Pay)';
            $payment_icon = '&#9201;';
            $payment_color = $c['gold'];
        }

        $current_year = date('Y');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation - {$booking_ref}</title>
</head>
<body style="margin:0;padding:0;background-color:{$c['bg']};font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;">
    
    <!-- Wrapper Table -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:{$c['bg']};">
        <tr>
            <td align="center" style="padding:30px 15px;">
                
                <!-- Main Container -->
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:{$c['white']};border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
                    
                    <!-- Header with Logo -->
                    <tr>
                        <td style="background-color:{$c['primary']};padding:35px 40px;text-align:center;">
                            <img src="{$logo}" alt="Handsome Pete" width="200" style="max-width:200px;height:auto;display:inline-block;" />
                        </td>
                    </tr>
                    
                    <!-- Confirmation Banner -->
                    <tr>
                        <td style="background:linear-gradient(135deg, #2E4A3E 0%, #404D24 100%);padding:25px 40px;text-align:center;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="text-align:center;">
                                        <div style="width:54px;height:54px;border-radius:50%;background-color:rgba(255,255,255,0.18);display:inline-block;line-height:54px;font-size:26px;color:#ffffff;margin-bottom:10px;">&#10003;</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align:center;">
                                        <h1 style="margin:0;font-size:22px;font-weight:700;color:#ffffff;letter-spacing:0.5px;">Booking Confirmed!</h1>
                                        <p style="margin:8px 0 0;font-size:14px;color:rgba(255,255,255,0.85);">Reference: <strong>{$booking_ref}</strong></p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Greeting -->
                    <tr>
                        <td style="padding:30px 40px 10px;">
                            <p style="margin:0;font-size:16px;color:{$c['text']};line-height:1.6;">
                                Hi <strong>{$customer_name}</strong>,
                            </p>
                            <p style="margin:10px 0 0;font-size:15px;color:{$c['text_light']};line-height:1.6;">
                                Thank you for booking with <strong>Handsome Pete</strong>! Your ride has been confirmed. Here are your trip details:
                            </p>
                        </td>
                    </tr>

                    <!-- Trip Details Card -->
                    <tr>
                        <td style="padding:15px 40px 25px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:{$c['bg']};border-radius:10px;border:1px solid {$c['border']};">
                                
                                <!-- Date & Time -->
                                <tr>
                                    <td style="padding:20px 25px;border-bottom:1px solid {$c['border']};">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td width="40" valign="top" style="padding-right:12px;">
                                                    <div style="width:36px;height:36px;border-radius:8px;background-color:#2E4A3E;text-align:center;line-height:36px;font-size:16px;color:#fff;">&#128197;</div>
                                                </td>
                                                <td valign="top">
                                                    <p style="margin:0;font-size:12px;text-transform:uppercase;letter-spacing:1px;color:{$c['text_light']};font-weight:600;">Date &amp; Time</p>
                                                    <p style="margin:4px 0 0;font-size:15px;color:{$c['text']};font-weight:600;">{$pickup_date}</p>
                                                    <p style="margin:2px 0 0;font-size:14px;color:{$c['text_light']};">{$pickup_time}</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <!-- Pickup -->
                                <tr>
                                    <td style="padding:18px 25px;border-bottom:1px solid {$c['border']};">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td width="40" valign="top" style="padding-right:12px;">
                                                    <div style="width:36px;height:36px;border-radius:8px;background-color:#27ae60;text-align:center;line-height:36px;font-size:14px;color:#fff;">&#9650;</div>
                                                </td>
                                                <td valign="top">
                                                    <p style="margin:0;font-size:12px;text-transform:uppercase;letter-spacing:1px;color:{$c['text_light']};font-weight:600;">Pickup Location</p>
                                                    <p style="margin:4px 0 0;font-size:14px;color:{$c['text']};line-height:1.4;">{$pickup_addr}</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <!-- Destination -->
                                <tr>
                                    <td style="padding:18px 25px;border-bottom:1px solid {$c['border']};">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td width="40" valign="top" style="padding-right:12px;">
                                                    <div style="width:36px;height:36px;border-radius:8px;background-color:#404D24;text-align:center;line-height:36px;font-size:14px;color:#fff;">&#9660;</div>
                                                </td>
                                                <td valign="top">
                                                    <p style="margin:0;font-size:12px;text-transform:uppercase;letter-spacing:1px;color:{$c['text_light']};font-weight:600;">Destination</p>
                                                    <p style="margin:4px 0 0;font-size:14px;color:{$c['text']};line-height:1.4;">{$dest_addr}</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <!-- Distance -->
                                <tr>
                                    <td style="padding:18px 25px;border-bottom:1px solid {$c['border']};">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td width="40" valign="top" style="padding-right:12px;">
                                                    <div style="width:36px;height:36px;border-radius:8px;background-color:{$c['primary']};text-align:center;line-height:36px;font-size:14px;color:#fff;">&#128663;</div>
                                                </td>
                                                <td valign="top">
                                                    <p style="margin:0;font-size:12px;text-transform:uppercase;letter-spacing:1px;color:{$c['text_light']};font-weight:600;">Distance</p>
                                                    <p style="margin:4px 0 0;font-size:15px;color:{$c['text']};font-weight:600;">{$distance} km</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <!-- Price -->
                                <tr>
                                    <td style="padding:18px 25px;border-bottom:1px solid {$c['border']};">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td width="40" valign="top" style="padding-right:12px;">
                                                    <div style="width:36px;height:36px;border-radius:8px;background-color:{$c['gold']};text-align:center;line-height:36px;font-size:16px;color:#fff;">$</div>
                                                </td>
                                                <td valign="top">
                                                    <p style="margin:0;font-size:12px;text-transform:uppercase;letter-spacing:1px;color:{$c['text_light']};font-weight:600;">Total Price</p>
                                                    <p style="margin:4px 0 0;font-size:22px;color:{$c['text']};font-weight:700;">\${$price} <span style="font-size:13px;font-weight:400;color:{$c['text_light']};">CAD</span></p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <!-- Payment Status -->
                                <tr>
                                    <td style="padding:18px 25px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td width="40" valign="top" style="padding-right:12px;">
                                                    <div style="width:36px;height:36px;border-radius:8px;background-color:{$payment_color};text-align:center;line-height:36px;font-size:16px;color:#fff;">{$payment_icon}</div>
                                                </td>
                                                <td valign="top">
                                                    <p style="margin:0;font-size:12px;text-transform:uppercase;letter-spacing:1px;color:{$c['text_light']};font-weight:600;">Payment</p>
                                                    <p style="margin:4px 0 0;font-size:14px;color:{$payment_color};font-weight:600;">{$payment_method}</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                            </table>
                        </td>
                    </tr>

                    <!-- PDF Receipt Note -->
                    <tr>
                        <td style="padding:0 40px 20px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef6ff;border-radius:8px;border:1px solid #c8e0ff;">
                                <tr>
                                    <td style="padding:15px 20px;">
                                        <p style="margin:0;font-size:13px;color:#2c5aa0;line-height:1.5;">
                                            &#128206; <strong>Your receipt is attached</strong> to this email as a PDF for your records.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

HTML;

        // Append customer notes block if present
        if (!empty($notes)) {
            $html .= <<<HTML
                    <!-- Customer Notes -->
                    <tr>
                        <td style="padding:0 40px 20px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#EEF4F0;border-radius:8px;border-left:4px solid #2E4A3E;">
                                <tr>
                                    <td style="padding:15px 20px;">
                                        <p style="margin:0 0 6px;font-size:12px;text-transform:uppercase;letter-spacing:1px;color:#5c7268;font-weight:600;">&#128221; Customer Notes</p>
                                        <p style="margin:0;font-size:14px;color:#2E4A3E;line-height:1.5;font-style:italic;">{$notes}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
HTML;
        }

        $html .= <<<HTML

                    <!-- Important Info -->
                    <tr>
                        <td style="padding:0 40px 25px;">
                            <h3 style="margin:0 0 12px;font-size:15px;color:{$c['text']};font-weight:700;">Important Information</h3>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding:6px 0;font-size:13px;color:{$c['text_light']};line-height:1.5;">
                                        &#8226; Please be ready at the pickup location 5 minutes before your scheduled time.
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0;font-size:13px;color:{$c['text_light']};line-height:1.5;">
                                        &#8226; If you need to cancel or modify your booking, please contact us as soon as possible.
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:6px 0;font-size:13px;color:{$c['text_light']};line-height:1.5;">
                                        &#8226; Save your booking reference <strong>{$booking_ref}</strong> for your records.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- CTA / Contact -->
                    <tr>
                        <td style="padding:0 40px 30px;text-align:center;">
                            <a href="https://handsomepete.ca" style="display:inline-block;padding:14px 36px;background-color:#2E4A3E;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;border-radius:8px;letter-spacing:0.5px;">Visit Our Website</a>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color:{$c['primary']};padding:30px 40px;text-align:center;">
                            <p style="margin:0 0 8px;font-size:14px;color:{$c['gold']};font-weight:600;">Handsome Pete Transport</p>
                            <p style="margin:0 0 5px;font-size:12px;color:rgba(255,255,255,0.6);">We'll see you soon!</p>
                            <hr style="border:none;border-top:1px solid rgba(255,255,255,0.1);margin:15px 0;" />
                            <p style="margin:0;font-size:11px;color:rgba(255,255,255,0.4);line-height:1.5;">
                                &copy; {$current_year} Handsome Pete. All rights reserved.<br/>
                                This is an automated confirmation email. Please do not reply directly.
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
HTML;

        return $html;
    }

    /**
     * Set wp_mail to send HTML and set From name/email
     */
    public static function set_html_mail_headers() {
        return array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Handsome Pete <bookings@handsomepete.ca>',
        );
    }
}
