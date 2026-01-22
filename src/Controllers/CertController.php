<?php

namespace SRC\Controllers;

use WP_REST_REQUEST;
use WP_Error;

use SRC\Utils\Certificate;

define('CISON_CERT_TABLE', 'wprx_cison_certificates');

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

    // Uncomment these lines if eligibility check is needed
    // $preview = cison_preview_user_eligibility($user_id);
    // if (empty($preview['eligible'])) {
    //     return new WP_Error('not_eligible', $preview['reason'], ['status' => 400]);
    // }

    $cert_table_name = CISON_CERT_TABLE;
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$cert_table_name} WHERE user_id = %d LIMIT 1",
        $user_id
    ));

    // Check if certificate already exists
    if (!empty($row) && file_exists($row->certificate_path)) {
        return new WP_Error("certificate_exists", "Certificate already exists", ['status' => 400]);
    }

    $is_transiting = function_exists('bp_get_profile_field_data')
        ? (bp_get_profile_field_data(['field' => 1595, 'user_id' => $user_id]) === 'Yes')
        : false;

    $member_type = $is_transiting ? 'transiting' : 'inducted';
    
    // Fix: $preview is not defined unless eligibility check is uncommented
    $applied_cutoff = null; // Set default or retrieve from eligibility check

    $firstname = function_exists('bp_get_profile_field_data')
        ? bp_get_profile_field_data(['field' => 1, 'user_id' => $user_id])
        : '';
    $middlename = function_exists('bp_get_profile_field_data')
        ? bp_get_profile_field_data(['field' => 864, 'user_id' => $user_id])
        : '';
    $surname = function_exists('bp_get_profile_field_data')
        ? bp_get_profile_field_data(['field' => 2, 'user_id' => $user_id])
        : '';
    $email = get_userdata($user_id) ? get_userdata($user_id)->user_email : '';

    $date_now = date('Y-m-d H:i:s');
    $date_issued_unix = strtotime($date_now);

    // Fix: $existing is not defined
    $cert_id = CISON_CURRENT_YEAR . '-' . sprintf('%05d', cison_get_next_cert_number());
    $cert_path = CISON_CERTIFICATE_DIR . "certificate_{$cert_id}.pdf";
    $secret_token = wp_generate_password(12, false);

    $cutoff_date_to_store = ($member_type === 'transiting')
        ? date('Y-m-d', $date_issued_unix)
        : $applied_cutoff;
    
    // Fix: $table is not defined, should be $cert_table_name
    $ins = $wpdb->insert(
        $cert_table_name,
        [
            'user_id'         => $user_id,
            'member_id'       => $member_id,
            'cert_id'         => $cert_id,
            'certificate_path'=> $cert_path,
            'date_issued'     => $date_issued_unix,
            'secret_token'    => $secret_token,
            'last_updated'    => time(),
            'firstname'       => $firstname,
            'middlename'      => $middlename,
            'surname'         => $surname,
            'email'           => $email,
            'member_type'     => $member_type,
            'cutoff_date'     => $cutoff_date_to_store,
        ],
        ['%d','%s','%s','%s','%d','%s','%d','%s','%s','%s','%s','%s','%s']
    );
    
    if (!$ins) {
        error_log("CISON: failed to insert certificate row for user {$user_id}: " . $wpdb->last_error);
        return new WP_Error("failed_db", "Failed to insert certificate row", ['status' => 500]);
    }

    // Fix: Return the newly created cert_path, not $row->certificate_path
    return rest_ensure_response([
        'user_id' => $user_id,
        'certificate_path' => $cert_path,
        'cert_id' => $cert_id,
        'info' => "User's certificate has been created",
        'status' => 'success'
    ]);
}
}
