<?php
/**
 * PDF Receipt Generator for Handsome Pete Booking System
 *
 * Generates PDF receipts using pure PHP — no external libraries required.
 * Creates valid PDF 1.4 documents with formatted booking receipt content.
 */

if (!defined('ABSPATH')) {
    exit;
}

class HP_Booking_PDF_Receipt {

    private $objects = array();
    private $object_id = 0;
    private $pages = array();
    private $current_page_content = '';
    private $fonts = array();
    private $y_position = 0;
    private $page_height = 842; // A4 height in points
    private $page_width = 595;  // A4 width in points
    private $margin_left = 50;
    private $margin_right = 50;
    private $content_width = 495;

    /**
     * Generate a PDF receipt for a booking and return the file path.
     *
     * @param object $booking The booking object from the database.
     * @return string|false Path to the generated PDF file, or false on failure.
     */
    public static function generate($booking) {
        try {
            $generator = new self();
            return $generator->create_receipt($booking);
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[HP Booking] PDF generation failed: ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Create the receipt PDF.
     */
    private function create_receipt($booking) {
        $this->objects = array();
        $this->object_id = 0;
        $this->fonts = array();

        // Format data
        $ref          = $booking->booking_reference;
        $customer     = !empty($booking->customer_name) ? $booking->customer_name : 'Valued Customer';
        $email        = !empty($booking->customer_email) ? $booking->customer_email : '';
        $phone        = !empty($booking->customer_phone) ? $booking->customer_phone : '';
        $pickup       = $booking->pickup_address;
        $destination  = $booking->destination_address;
        $date         = date('l, F j, Y', strtotime($booking->pickup_datetime));
        $time         = date('g:i A', strtotime($booking->pickup_datetime));
        $distance     = number_format((float)$booking->distance_km, 2) . ' km';
        $price        = '$' . number_format((float)$booking->price, 2) . ' CAD';
        $created      = !empty($booking->created_at) ? date('M j, Y g:i A', strtotime($booking->created_at)) : date('M j, Y g:i A');

        if ($booking->payment_status === 'paid_online') {
            $payment = 'Paid Online (Credit/Debit Card)';
            $payment_status = 'PAID';
        } elseif ($booking->payment_status === 'paid_tap') {
            $payment = 'Pay on Pickup (Tap to Pay)';
            $payment_status = 'PAID';
        } elseif ($booking->payment_status === 'paid_cash') {
            $payment = 'Paid Cash on Pickup';
            $payment_status = 'PAID';
        } else {
            $payment = 'Pay on Pickup (Tap to Pay)';
            $payment_status = 'PENDING';
        }

        // Build page content
        $this->y_position = $this->page_height - 50;
        $this->current_page_content = '';

        // ---- Header background ----
        $this->add_rect(0, $this->page_height - 140, $this->page_width, 140, '26 26 46');

        // Company name
        $this->y_position = $this->page_height - 55;
        $this->add_text_centered('HANDSOME PETE', 22, true, '201 165 110');
        $this->y_position -= 22;
        $this->add_text_centered('TRANSPORT SERVICE', 10, false, '255 255 255');

        // ---- Title area ----
        $this->y_position = $this->page_height - 170;
        $this->add_text_centered('BOOKING RECEIPT', 18, true, '51 51 51');
        $this->y_position -= 18;
        $this->add_text_centered('Reference: ' . $ref, 10, false, '102 102 102');

        // Divider
        $this->y_position -= 20;
        $this->add_line($this->margin_left, $this->y_position, $this->page_width - $this->margin_right, $this->y_position, '224 224 224');

        // ---- Customer Information ----
        $this->y_position -= 25;
        $this->add_section_header('CUSTOMER INFORMATION');
        $this->y_position -= 22;
        $this->add_detail_row('Name', $customer);
        if ($email) {
            $this->y_position -= 18;
            $this->add_detail_row('Email', $email);
        }
        if ($phone) {
            $this->y_position -= 18;
            $this->add_detail_row('Phone', $phone);
        }

        // Divider
        $this->y_position -= 25;
        $this->add_line($this->margin_left, $this->y_position, $this->page_width - $this->margin_right, $this->y_position, '224 224 224');

        // ---- Trip Details ----
        $this->y_position -= 25;
        $this->add_section_header('TRIP DETAILS');

        $this->y_position -= 22;
        $this->add_detail_row('Date', $date);
        $this->y_position -= 18;
        $this->add_detail_row('Time', $time);
        $this->y_position -= 18;
        $this->add_detail_row('Pickup', $pickup);
        $this->y_position -= 18;
        $this->add_detail_row('Destination', $destination);
        $this->y_position -= 18;
        $this->add_detail_row('Distance', $distance);

        // Divider
        $this->y_position -= 25;
        $this->add_line($this->margin_left, $this->y_position, $this->page_width - $this->margin_right, $this->y_position, '224 224 224');

        // ---- Payment Summary ----
        $this->y_position -= 25;
        $this->add_section_header('PAYMENT SUMMARY');

        $this->y_position -= 22;
        $this->add_detail_row('Payment Method', $payment);
        $this->y_position -= 18;
        $this->add_detail_row('Status', $payment_status);

        // Total price highlight
        $this->y_position -= 30;
        $this->add_rect($this->margin_left, $this->y_position - 10, $this->content_width, 40, '244 244 248');
        $this->add_text($this->margin_left + 15, $this->y_position + 12, 'TOTAL', 12, true, '102 102 102');
        $this->add_text_right($this->page_width - $this->margin_right - 15, $this->y_position + 12, $price, 16, true, '26 26 46');

        // ---- Footer ----
        $this->y_position -= 50;
        $this->add_line($this->margin_left, $this->y_position, $this->page_width - $this->margin_right, $this->y_position, '224 224 224');
        $this->y_position -= 20;
        $this->add_text_centered('Issued: ' . $created, 9, false, '153 153 153');
        $this->y_position -= 14;
        $this->add_text_centered('Handsome Pete Transport | handsomepete.ca', 9, false, '153 153 153');
        $this->y_position -= 14;
        $this->add_text_centered('Thank you for choosing Handsome Pete!', 9, true, '201 165 110');

        // Build the PDF
        $pdf_content = $this->build_pdf();

        // Save to temp file
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/hp-receipts';
        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
            // Protect directory
            file_put_contents($pdf_dir . '/.htaccess', 'deny from all');
            file_put_contents($pdf_dir . '/index.php', '<?php // Silence is golden.');
        }

        $filename = 'receipt-' . sanitize_file_name($ref) . '.pdf';
        $filepath = $pdf_dir . '/' . $filename;

        file_put_contents($filepath, $pdf_content);

        return $filepath;
    }

    // ========================================================================
    // PDF Content Helpers
    // ========================================================================

    private function add_section_header($text) {
        $this->add_text($this->margin_left, $this->y_position, $text, 11, true, '233 69 96');
    }

    private function add_detail_row($label, $value) {
        // Truncate long values for PDF display
        if (strlen($value) > 65) {
            $value = substr($value, 0, 62) . '...';
        }
        $this->add_text($this->margin_left + 10, $this->y_position, $label . ':', 9, true, '102 102 102');
        $this->add_text($this->margin_left + 110, $this->y_position, $value, 9, false, '51 51 51');
    }

    private function add_text($x, $y, $text, $size = 12, $bold = false, $color = '0 0 0') {
        $font = $bold ? '/Helvetica-Bold' : '/Helvetica';
        $text = $this->escape_pdf_string($text);
        $rgb = $this->parse_color($color);
        $this->current_page_content .= sprintf(
            "%s rg\nBT\n%s %d Tf\n%d %d Td\n(%s) Tj\nET\n",
            $rgb, $font, $size, $x, $y, $text
        );
    }

    private function add_text_centered($text, $size = 12, $bold = false, $color = '0 0 0') {
        // Approximate center — Helvetica is ~0.52 * size per char
        $char_width = $size * ($bold ? 0.58 : 0.52);
        $text_width = strlen($text) * $char_width;
        $x = ($this->page_width - $text_width) / 2;
        $this->add_text(max($x, $this->margin_left), $this->y_position, $text, $size, $bold, $color);
    }

    private function add_text_right($x, $y, $text, $size = 12, $bold = false, $color = '0 0 0') {
        $char_width = $size * ($bold ? 0.58 : 0.52);
        $text_width = strlen($text) * $char_width;
        $this->add_text($x - $text_width, $y, $text, $size, $bold, $color);
    }

    private function add_rect($x, $y, $w, $h, $color = '240 240 240') {
        $rgb = $this->parse_color($color);
        $this->current_page_content .= sprintf(
            "%s rg\n%d %d %d %d re\nf\n",
            $rgb, $x, $y, $w, $h
        );
    }

    private function add_line($x1, $y1, $x2, $y2, $color = '200 200 200') {
        $rgb = $this->parse_color($color);
        $this->current_page_content .= sprintf(
            "%s RG\n0.5 w\n%d %d m\n%d %d l\nS\n",
            $rgb, $x1, $y1, $x2, $y2
        );
    }

    private function parse_color($color_string) {
        $parts = explode(' ', $color_string);
        if (count($parts) === 3) {
            return sprintf('%.3f %.3f %.3f', $parts[0] / 255, $parts[1] / 255, $parts[2] / 255);
        }
        return '0 0 0';
    }

    private function escape_pdf_string($str) {
        $str = str_replace('\\', '\\\\', $str);
        $str = str_replace('(', '\\(', $str);
        $str = str_replace(')', '\\)', $str);
        // Replace non-ASCII with space for safety
        $str = preg_replace('/[^\x20-\x7E]/', ' ', $str);
        return $str;
    }

    // ========================================================================
    // Low-level PDF Builder
    // ========================================================================

    private function new_object() {
        $this->object_id++;
        return $this->object_id;
    }

    private function build_pdf() {
        $offsets = array();
        $output = "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";

        // Object 1: Catalog
        $catalog_id = $this->new_object();
        $offsets[$catalog_id] = strlen($output);
        $output .= "{$catalog_id} 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        // Object 2: Pages
        $pages_id = $this->new_object();
        $offsets[$pages_id] = strlen($output);
        $output .= "{$pages_id} 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";

        // Object 3: Page
        $page_id = $this->new_object();
        $offsets[$page_id] = strlen($output);
        $output .= "{$page_id} 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->page_width} {$this->page_height}] ";
        $output .= "/Contents 6 0 R /Resources << /Font << ";
        $output .= "/Helvetica 4 0 R /Helvetica-Bold 5 0 R ";
        $output .= ">> >> >>\nendobj\n";

        // Object 4: Helvetica font
        $font1_id = $this->new_object();
        $offsets[$font1_id] = strlen($output);
        $output .= "{$font1_id} 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";

        // Object 5: Helvetica-Bold font
        $font2_id = $this->new_object();
        $offsets[$font2_id] = strlen($output);
        $output .= "{$font2_id} 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n";

        // Object 6: Page content stream
        $stream = $this->current_page_content;
        $stream_length = strlen($stream);
        $content_id = $this->new_object();
        $offsets[$content_id] = strlen($output);
        $output .= "{$content_id} 0 obj\n<< /Length {$stream_length} >>\nstream\n{$stream}\nendstream\nendobj\n";

        // Cross-reference table
        $xref_offset = strlen($output);
        $num_objects = $this->object_id + 1;
        $output .= "xref\n0 {$num_objects}\n";
        $output .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $this->object_id; $i++) {
            $output .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        // Trailer
        $output .= "trailer\n<< /Size {$num_objects} /Root 1 0 R >>\n";
        $output .= "startxref\n{$xref_offset}\n%%EOF";

        return $output;
    }

    /**
     * Cleanup old receipt files (call periodically).
     */
    public static function cleanup_old_receipts($days = 7) {
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/hp-receipts';

        if (!is_dir($pdf_dir)) {
            return;
        }

        $files = glob($pdf_dir . '/receipt-*.pdf');
        $threshold = time() - ($days * 86400);

        foreach ($files as $file) {
            if (filemtime($file) < $threshold) {
                @unlink($file);
            }
        }
    }
}
