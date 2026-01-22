<?php

namespace SRC\Utils;

use SRC\Config\Config;
use SRC\Utils\money;

define('CISON_CURRENT_YEAR', (int) date('Y'));
define('CISON_PRIVATE_DIR', WP_CONTENT_DIR . '/private/');
define('CISON_CERTIFICATE_DIR', CISON_PRIVATE_DIR . 'certificates/');
define('CISON_CERTIFICATE_URL', content_url('/private/certificates/'));
define('CISON_CERT_TABLE', Config::get('CISON_CERT_TABLE', ''));



function cison_get_next_cert_number() {
    global $wpdb;
    $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . CISON_CERT_TABLE);
    if ($wpdb->last_error) {
        error_log("CISON: Error counting certificates: " . $wpdb->last_error);
        return 1;
    }
    return $count + 1;
}


/* ======================================================
 * ELIGIBILITY PREVIEW
 * ====================================================== */

/**
 * Returns:
 *   [
 *     'eligible'       => bool,
 *     'reason'         => string,
 *     'applied_cutoff' => 'YYYY-mm-dd'|null
 *   ]
 */
function cison_preview_user_eligibility($user_id) {
    $user_id = (int) $user_id;

    $member_id = function_exists('bp_get_profile_field_data')
        ? bp_get_profile_field_data(['field' => 894, 'user_id' => $user_id])
        : '';

    if (empty($member_id)) {
        return [
            'eligible'       => false,
            'reason'         => 'Member ID not set',
            'applied_cutoff' => null,
        ];
    }

    $is_transiting = function_exists('bp_get_profile_field_data')
        ? (bp_get_profile_field_data(['field' => 1595, 'user_id' => $user_id]) === 'Yes')
        : false;

    $reg_year = $is_transiting
        ? 2023
        : max(2024, min((int) substr($member_id, 0, 4), CISON_CURRENT_YEAR));

    $required_fees = cison_get_required_fees($is_transiting, $reg_year);
    $paid_fees     = cison_get_paid_fees($user_id);
    $unpaid_fees   = cison_get_unpaid_fees($required_fees, $paid_fees);

    if ($is_transiting) {
        $eligible = empty($unpaid_fees) ||
            (count($unpaid_fees) === 1 && isset($unpaid_fees["dev_levy_" . CISON_CURRENT_YEAR]));

        return [
            'eligible'       => (bool) $eligible,
            'reason'         => $eligible ? 'Transiting & all fees paid (or only current dev levy unpaid)' : 'Transiting but fees unpaid',
            'applied_cutoff' => $eligible ? date('Y-m-d') : null,
        ];
    }

    // Non-transiting
    if (!empty($unpaid_fees)) {
        return [
            'eligible'       => false,
            'reason'         => 'Non-transiting: fees unpaid',
            'applied_cutoff' => null,
        ];
    }

    $last_payment = cison_get_last_payment_date($user_id);
    $last_payment_norm = $last_payment ? date('Y-m-d', strtotime($last_payment)) : null;

    $cutoffs      = cison_get_cutoffs_option();
    $active_cutoff = $cutoffs['active'] ?? null;

    if (!$active_cutoff) {
        return [
            'eligible'       => false,
            'reason'         => 'No active cutoff configured for inducted members',
            'applied_cutoff' => null,
        ];
    }

    if (!$last_payment_norm) {
        return [
            'eligible'       => false,
            'reason'         => 'Non-transiting: no payment date found',
            'applied_cutoff' => $active_cutoff,
        ];
    }

    if ($last_payment_norm <= $active_cutoff) {
        return [
            'eligible'       => true,
            'reason'         => "Non-transiting: last payment {$last_payment_norm} <= cutoff {$active_cutoff}",
            'applied_cutoff' => $active_cutoff,
        ];
    }

    return [
        'eligible'       => false,
        'reason'         => "Non-transiting: last payment {$last_payment_norm} > cutoff {$active_cutoff}",
        'applied_cutoff' => $active_cutoff,
    ];
}


function cison_check_eligibility_and_create_row_if_missing($user_id) {
    global $wpdb;
    $table = CISON_CERT_TABLE;

    $user_id = (int) $user_id;

    $member_id = function_exists('bp_get_profile_field_data')
        ? bp_get_profile_field_data(['field' => 894, 'user_id' => $user_id])
        : '';

    if (empty($member_id)) {
        return false;
    }

    // If row exists & file exists, nothing to do
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE user_id = %d LIMIT 1",
        $user_id
    ));

    if ($existing && !empty($existing->certificate_path) && file_exists($existing->certificate_path)) {
        return true;
    }

    // Check eligibility
    $preview = cison_preview_user_eligibility($user_id);
    if (empty($preview['eligible'])) {
        return false;
    }

    $is_transiting = function_exists('bp_get_profile_field_data')
        ? (bp_get_profile_field_data(['field' => 1595, 'user_id' => $user_id]) === 'Yes')
        : false;

    $member_type   = $is_transiting ? 'transiting' : 'inducted';
    $applied_cutoff = $preview['applied_cutoff'] ?? null;

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

    $date_now        = date('Y-m-d H:i:s');
    $date_issued_unix = strtotime($date_now);

    $cert_id   = $existing ? $existing->cert_id : (CISON_CURRENT_YEAR . '-' . sprintf('%05d', cison_get_next_cert_number()));
    $cert_path = CISON_CERTIFICATE_DIR . "certificate_{$cert_id}.pdf";
    $secret_token = wp_generate_password(12, false);

    $cutoff_date_to_store = ($member_type === 'transiting')
        ? date('Y-m-d', $date_issued_unix)
        : $applied_cutoff;

    if ($existing) {
        $upd = $wpdb->update(
            $table,
            [
                'certificate_path' => $cert_path,
                'date_issued'      => $date_issued_unix,
                'secret_token'     => $secret_token,
                'last_updated'     => time(),
                'firstname'        => $firstname,
                'middlename'       => $middlename,
                'surname'          => $surname,
                'email'            => $email,
                'member_type'      => $member_type,
                'cutoff_date'      => $cutoff_date_to_store,
            ],
            ['user_id' => $user_id],
            ['%s','%d','%s','%d','%s','%s','%s','%s','%s','%s'],
            ['%d']
        );
        if ($upd === false) {
            error_log("CISON: failed to update certificate row for user {$user_id}: " . $wpdb->last_error);
            return false;
        }
    } else {
        $ins = $wpdb->insert(
            $table,
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
        if ($ins === false) {
            error_log("CISON: failed to insert certificate row for user {$user_id}: " . $wpdb->last_error);
            return false;
        }
    }

    // NOTE: This only updates DB. PDF creation is handled by your existing process.

    return true;
}

function cison_create_row_for_certification($user_id) {
    global $wpdb;
    $table = CISON_CERT_TABLE;

    $user_id = (int) $user_id;

    $member_id = function_exists('bp_get_profile_field_data')
        ? bp_get_profile_field_data(['field' => 894, 'user_id' => $user_id])
        : '';

    
    $is_transiting = function_exists('bp_get_profile_field_data')
        ? (bp_get_profile_field_data(['field' => 1595, 'user_id' => $user_id]) === 'Yes')
        : false;

    $member_type   = $is_transiting ? 'transiting' : 'inducted';
    $applied_cutoff = $preview['applied_cutoff'] ?? null;

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

    $date_now        = date('Y-m-d H:i:s');
    $date_issued_unix = strtotime($date_now);

    $cert_id   = $existing ? $existing->cert_id : (CISON_CURRENT_YEAR . '-' . sprintf('%05d', cison_get_next_cert_number()));
    $cert_path = CISON_CERTIFICATE_DIR . "certificate_{$cert_id}.pdf";
    $secret_token = wp_generate_password(12, false);

    $cutoff_date_to_store = ($member_type === 'transiting')
        ? date('Y-m-d', $date_issued_unix)
        : $applied_cutoff;
    
    $ins = $wpdb->insert(
        $table,
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
    if ($ins === false) {
        error_log("CISON: failed to insert certificate row for user {$user_id}: " . $wpdb->last_error);
        return false;
    }
    return true;
    }
