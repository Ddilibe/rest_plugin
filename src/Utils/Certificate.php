<?php

namespace SRC\Utils;

use SRC\Config\Config;
use SRC\Utils\money;


define('CISON_CERT_TABLE', Config::get('CISON_CERT_TABLE', ''));
define('CISON_CURRENT_YEAR', (int) date('Y'));


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

