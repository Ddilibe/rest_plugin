<?php

namespace SRC\Utils;

define('CISON_CURRENT_YEAR', (int) date('Y'));

/**
 * Return all product IDs (Regular / Retired / Student) that count as
 * "Annual dues" for a given year.
 */
function cison_get_annual_dues_product_ids($year) {
    $year = (int) $year;

    static $map = [
        // year  => [regular, retired, student]
        2024 => [317, 624, 623],
        2025 => [5035, 5983, 5980],
        2026 => [12110, 12112, 12114], // add when ready
    ];

    return $map[$year] ?? [];
}

function cison_get_dev_levy_product_ids($year) {
    $year = (int) $year;

    static $map = [
        2024 => [368],
        2025 => [5063],
        2026 => [12116], // add when ready
    ];

    return $map[$year] ?? [];
}

function cison_get_paid_fees($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }

    // Cache to transient to avoid hammering WC queries
    $cache_key = 'cison_paid_fees_' . $user_id;
    $cached    = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    // Determine member context
    $member_id = function_exists('bp_get_profile_field_data')
        ? bp_get_profile_field_data(['field' => 894, 'user_id' => $user_id])
        : '';

    $is_transiting = function_exists('bp_get_profile_field_data')
        ? (bp_get_profile_field_data(['field' => 1595, 'user_id' => $user_id]) === 'Yes')
        : false;

    $reg_year = $is_transiting
        ? 2023
        : ($member_id ? max(2024, min((int) substr($member_id, 0, 4), CISON_CURRENT_YEAR)) : CISON_CURRENT_YEAR);

    $required_fees = cison_get_required_fees($is_transiting, $reg_year);

    // Initialise all as unpaid
    $paid_fees = [];
    foreach ($required_fees as $key => $_fee) {
        $paid_fees[$key] = false;
    }

    // Build product lookup: product_id => [fee_key1, fee_key2, ...]
    $fee_lookup = [];
    foreach ($required_fees as $key => $fee) {
        $ids = [];

        if (isset($fee['product_ids'])) {
            $ids = (array) $fee['product_ids'];
        } elseif (isset($fee['product_id'])) {
            $ids = [(int) $fee['product_id']];
        }

        foreach ($ids as $pid) {
            $pid = (int) $pid;
            if ($pid <= 0) {
                continue;
            }
            if (!isset($fee_lookup[$pid])) {
                $fee_lookup[$pid] = [];
            }
            $fee_lookup[$pid][] = $key;
        }
    }

    if (empty($fee_lookup)) {
        set_transient($cache_key, $paid_fees, 15 * MINUTE_IN_SECONDS);
        return $paid_fees;
    }

    // Helper closure for matching product/variation/parent
    $mark_paid = function ($product_id) use (&$paid_fees, $fee_lookup) {
        $product_id = (int) $product_id;
        if ($product_id <= 0) {
            return;
        }
        if (isset($fee_lookup[$product_id])) {
            foreach ($fee_lookup[$product_id] as $fee_key) {
                $paid_fees[$fee_key] = true;
            }
        }
    };

    // 1) Subscriptions
    if (function_exists('wcs_get_users_subscriptions')) {
        $subscriptions = wcs_get_users_subscriptions($user_id);
        foreach ($subscriptions as $subscription) {
            if (!in_array($subscription->get_status(), ['active', 'pending-cancel'], true)) {
                continue;
            }

            foreach ($subscription->get_items() as $item) {
                $pid = (int) $item->get_product_id();
                $vid = (int) $item->get_variation_id();

                $mark_paid($pid);
                $mark_paid($vid);

                if (function_exists('wc_get_product')) {
                    $prod = wc_get_product($pid);
                    if ($prod) {
                        $parent_id = (int) $prod->get_parent_id();
                        $mark_paid($parent_id);
                    }
                }
            }
        }
    }

    // 2) Standard orders
    if (function_exists('wc_get_orders')) {
        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'status'      => ['completed', 'processing'],
            'limit'       => -1,
            'return'      => 'objects',
        ]);

        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $pid = (int) $item->get_product_id();
                $vid = (int) $item->get_variation_id();

                $mark_paid($pid);
                $mark_paid($vid);

                if (function_exists('wc_get_product')) {
                    $prod = wc_get_product($pid);
                    if ($prod) {
                        $parent_id = (int) $prod->get_parent_id();
                        $mark_paid($parent_id);
                    }
                }
            }
        }
    }

    set_transient($cache_key, $paid_fees, 15 * MINUTE_IN_SECONDS);

    return $paid_fees;
}



/**
 * Build the list of required fees for a member.
 *
 * Each fee entry is:
 *   key => [
 *      'name'        => '2024 Annual Dues/Subscription',
 *      'product_ids' => [id1, id2, id3]  // ANY of these counts
 *   ]
 */
function cison_get_required_fees($is_transiting, $reg_year) {
    $current_year = CISON_CURRENT_YEAR;
    $required     = [];

     if ($is_transiting) {
        // Base transiting fees
        $required = [
            'nsa_dues' => [
                'name'        => '2023 NSA Membership Dues',
                'product_ids' => [885],
            ],
            'transition_fee' => [
                'name'        => 'NSA to CISON Transition Fee',
                'product_ids' => [366],
            ],
        ];

        // Track ALL years from 2024 up to current year
        for ($year = 2024; $year <= $current_year; $year++) {

            $annual_ids = cison_get_annual_dues_product_ids($year);
            if (!empty($annual_ids)) {
                $required["annual_dues_{$year}"] = [
                    'name'        => "{$year} Annual Dues/Subscription",
                    'product_ids' => $annual_ids,
                ];
            }

            $dev_ids = cison_get_dev_levy_product_ids($year);
            if (!empty($dev_ids)) {
                $required["dev_levy_{$year}"] = [
                    'name'        => "{$year} Development Levy",
                    'product_ids' => $dev_ids,
                ];
            }
        }

        return $required;
	 }

    // Non-transiting (new/regular members)
    $required['new_member_fee'] = [
        'name'        => 'New Member Registration Fee',
        'product_ids' => [320],
    ];

    $reg_year = (int) $reg_year;
    if ($reg_year < 2024) {
        $reg_year = 2024;
    }
    if ($reg_year > $current_year) {
        $reg_year = $current_year;
    }

    for ($year = $reg_year; $year <= $current_year; $year++) {
        $required["annual_dues_{$year}"] = [
            'name'        => "{$year} Annual Dues/Subscription",
            'product_ids' => cison_get_annual_dues_product_ids($year),
        ];

        $required["dev_levy_{$year}"] = [
            'name'        => "{$year} Development Levy",
            'product_ids' => cison_get_dev_levy_product_ids($year),
//'product_ids' => ($year === 2024) ? [368] : [5063],
        ];
    }

    return $required;
}


/**
 * Helper: compute unpaid fees from required + paid_fees array.
 *
 * $paid_fees is an array keyed like $required_fees with boolean values.
 */
function cison_get_unpaid_fees($required_fees, $paid_fees) {
    $unpaid = [];

    foreach ($required_fees as $key => $fee) {
        if (empty($paid_fees[$key])) {
            $unpaid[$key] = $fee;
        }
    }

    return $unpaid;
}
