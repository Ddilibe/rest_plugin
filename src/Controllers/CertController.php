<?php

namespace SRC\Controllers;

use WP_REST_REQUEST;
use WP_Error;

use SRC\Utils\Certificate;



class CertController {
    public static function getNextCertNumber() {
        return rest_ensure_response([
            'next_cert_number' => cison_get_next_cert_number(),
            'status' => 'success'
        ]);
    }

    public static function addNewCertification(WP_REST_REQUEST $request) {
        global $wpdb;
        $body = $request->get_json_params();
        $user_id = isset($body['user_id']) ? sanitize_text_field($body['user_id']) : '';
        $member_id = isset($body['member_id']) ? sanitize_text_field($body['member_id']) : '';

        if (empty($user_id) && empty($member_id)) {
            return new WP_Error('invalid_id', 'User ID or Member ID is required', ['status' => 400]);
        }

        if (empty($user_id) && $member_id) {
            $table_name = $wpdb->prefix . 'bp_xprofile_data';
            $user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM {$table_name} WHERE field_id = %d AND value = %s LIMIT 1",
                894,
                $member_id
            ));
        }

        if (empty($user_id)) {
            return new WP_Error('not_found', "User not found: $user_id Member: $member_id", ['status' => 404]);
        }

        // $preview = cison_preview_user_eligibility($user_id);
        // if (empty($preview['eligible'])) {
        //     return new WP_Error('not_eligible', $preview['reason'], ['status' => 400]);
        // }
        
        $cert_table_name = $wpdb->prefix.'cison_certificates';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$cert_table_name} WHERE user_id = %d LIMIT 1",
            $user_id
        ));

        if (!empty($row)) {
            cison_check_eligibility_and_create_row_if_missing($user_id);
        }

        if (file_exists($row->certificate_path)) {
            return new WP_Error("certificate_exists", "Certificate already exists", ['status' => 400]);
        }

        return rest_ensure_response([
            'user_id' => $user_id,
            'certificate_path' => $row->certificate_path,
            'info' => "User's certificate path has been created",
            'status' => 'success'
        ]);
        
    }
}
