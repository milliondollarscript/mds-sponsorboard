<?php
/**
 * Sponsor creative upload validation and media-library storage.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

namespace MillionDollarScript\Extensions\SponsorBoard\Concerns;

if (!defined('ABSPATH')) {
    exit;
}

trait HandlesSponsorCreativeUploads {

    private function creative_upload_result(array $booking, array $slot) {
        if (empty($_FILES['creative_file']) || !is_array($_FILES['creative_file'])) {
            return null;
        }
        return (new \MillionDollarScript\Extensions\SponsorBoard\CreativeUploadService())->upload($_FILES['creative_file'], $booking, $slot);
    }

    private function upload_error_code($error) {
        if (!is_wp_error($error)) {
            return '';
        }

        return sanitize_key((string) $error->get_error_code());
    }

}
