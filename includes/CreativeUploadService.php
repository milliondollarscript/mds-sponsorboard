<?php

namespace MillionDollarScript\Extensions\SponsorBoard;

if (!defined('ABSPATH')) {
    exit;
}

final class CreativeUploadService {
    /** @return array|\WP_Error|null */
    public function upload(array $file, array $booking, array $slot) {
        if (empty($file['name']) || UPLOAD_ERR_NO_FILE === absint($file['error'] ?? UPLOAD_ERR_NO_FILE)) {
            return null;
        }
        $error = absint($file['error'] ?? UPLOAD_ERR_OK);
        if (UPLOAD_ERR_OK !== $error) {
            return new \WP_Error('mds_sponsorboard_upload_error', $this->upload_error_message($error));
        }
        if (!in_array(sanitize_key((string) ($slot['creative_type'] ?? 'image')), ['image', 'logo'], true)) {
            return new \WP_Error('mds_sponsorboard_upload_type_unavailable', __('Uploads are available for image and logo sponsor slots.', 'mds-sponsorboard'));
        }

        $tmp_name = (string) ($file['tmp_name'] ?? '');
        if ('' === $tmp_name || !is_uploaded_file($tmp_name)) {
            return new \WP_Error('mds_sponsorboard_upload_invalid', __('The uploaded creative could not be verified.', 'mds-sponsorboard'));
        }
        $max_bytes = $this->max_bytes();
        if (!empty($file['size']) && (int) $file['size'] > $max_bytes) {
            return new \WP_Error('mds_sponsorboard_upload_too_large', sprintf(
                /* translators: %s: maximum file size. */
                __('Creative files must be %s or smaller.', 'mds-sponsorboard'),
                size_format($max_bytes)
            ));
        }

        $mimes = $this->mimes();
        $checked = wp_check_filetype_and_ext($tmp_name, (string) ($file['name'] ?? ''), $mimes);
        $mime = sanitize_mime_type((string) ($checked['type'] ?? ''));
        if (!$mime || !in_array($mime, array_values($mimes), true)) {
            return new \WP_Error('mds_sponsorboard_upload_invalid_type', __('Upload a JPG, PNG, GIF, or WebP creative.', 'mds-sponsorboard'));
        }
        $dimensions = wp_getimagesize($tmp_name);
        if (!is_array($dimensions) || empty($dimensions[0]) || empty($dimensions[1])) {
            return new \WP_Error('mds_sponsorboard_upload_invalid_image', __('The uploaded creative is not a readable image.', 'mds-sponsorboard'));
        }
        $target = $this->dimension_limits((string) ($slot['dimensions'] ?? ''));
        if ($target && ((int) $dimensions[0] > $target['width'] || (int) $dimensions[1] > $target['height'])) {
            return new \WP_Error('mds_sponsorboard_upload_dimensions', sprintf(
                /* translators: 1: width, 2: height. */
                __('Creative image dimensions must be no larger than %1$d x %2$d pixels for this slot.', 'mds-sponsorboard'),
                $target['width'],
                $target['height']
            ));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $upload = wp_handle_upload($file, ['test_form' => false, 'mimes' => $mimes]);
        if (!is_array($upload) || !empty($upload['error']) || empty($upload['file']) || empty($upload['url'])) {
            return new \WP_Error('mds_sponsorboard_upload_failed', (string) ($upload['error'] ?? __('The creative could not be uploaded.', 'mds-sponsorboard')));
        }

        $filename = sanitize_file_name((string) ($file['name'] ?? basename((string) $upload['file'])));
        $attachment_id = wp_insert_attachment([
            'post_mime_type' => sanitize_mime_type((string) ($upload['type'] ?? $mime)),
            'post_title' => sanitize_text_field(pathinfo($filename, PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit',
        ], (string) $upload['file']);
        if (is_wp_error($attachment_id) || !$attachment_id) {
            wp_delete_file((string) $upload['file']);
            return new \WP_Error('mds_sponsorboard_upload_attachment_failed', __('The creative was uploaded but could not be saved to the media library.', 'mds-sponsorboard'));
        }

        $attachment_id = absint($attachment_id);
        wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, (string) $upload['file']));
        update_post_meta($attachment_id, '_mds_sponsorboard_booking_id', absint($booking['id'] ?? 0));
        update_post_meta($attachment_id, '_mds_sponsorboard_slot_id', absint($slot['id'] ?? 0));

        return [
            'attachment_id' => $attachment_id,
            'creative_url' => esc_url_raw((string) $upload['url']),
            'creative_mime' => sanitize_mime_type((string) ($upload['type'] ?? $mime)),
            'creative_filename' => $filename,
            'creative_width' => (int) $dimensions[0],
            'creative_height' => (int) $dimensions[1],
            'creative_status' => 'pending_review',
            'creative_uploaded_at' => current_time('mysql'),
        ];
    }

    private function max_bytes(): int {
        $default = 2 * MB_IN_BYTES;
        $wp_max = function_exists('wp_max_upload_size') ? (int) wp_max_upload_size() : $default;
        return max(1, (int) \MillionDollarScript\Core\Hooks::apply_compat(
            'million-dollar-script/extensions/sponsorboard/upload/max/bytes',
            ['mds_sponsorboard_upload_max_bytes'],
            min($default, max(1, $wp_max))
        ));
    }

    private function mimes(): array {
        $mimes = ['jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
        $filtered = \MillionDollarScript\Core\Hooks::apply_compat('million-dollar-script/extensions/sponsorboard/allowed/image/mimes', ['mds_sponsorboard_allowed_image_mimes'], $mimes);
        return is_array($filtered) ? $filtered : $mimes;
    }

    private function dimension_limits(string $dimensions): ?array {
        if (!preg_match('/^\s*(\d{2,5})\s*x\s*(\d{2,5})\s*$/i', $dimensions, $matches)) {
            return null;
        }
        return ['width' => max(1, absint($matches[1])), 'height' => max(1, absint($matches[2]))];
    }

    private function upload_error_message(int $code): string {
        if (in_array($code, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return __('The creative file is too large for this site.', 'mds-sponsorboard');
        }
        if (UPLOAD_ERR_PARTIAL === $code) {
            return __('The creative upload did not finish. Try again.', 'mds-sponsorboard');
        }
        if (in_array($code, [UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION], true)) {
            return __('The server could not store the uploaded creative.', 'mds-sponsorboard');
        }
        return __('The creative could not be uploaded.', 'mds-sponsorboard');
    }
}
