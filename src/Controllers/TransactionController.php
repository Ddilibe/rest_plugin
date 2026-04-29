<?php

namespace SRC\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_REST_Server;
use WC_Order;
use WC_Order_Item;
use WC_Customer;
use WC_DateTime;
use DateTime;
use Exception;
class TransactionController
{
    /**
     * Define and validate query parameters.
     */
    static function get_endpoint_args()
    {
        return [
            'startdate' => [
                'required' => false,
                'type' => 'string',
                'description' => 'Start date in YYYY-MM-DD format.',
                'validate_callback' => [TransactionController::class, 'validate_date'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'enddate' => [
                'required' => false,
                'type' => 'string',
                'description' => 'End date in YYYY-MM-DD format.',
                'validate_callback' => [TransactionController::class, 'validate_date'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'status' => [
                'required' => false,
                'type' => 'string',
                'description' => 'Comma-separated WooCommerce order statuses (e.g. completed,processing).',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'per_page' => [
                'required' => false,
                'type' => 'integer',
                'default' => 100,
                'minimum' => 1,
                'maximum' => 500,
                'description' => 'Number of transactions per page.',
                'sanitize_callback' => 'absint',
            ],
            'page' => [
                'required' => false,
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1,
                'description' => 'Page number.',
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    /**
     * Validate YYYY-MM-DD date format.
     */
    public function validate_date($value)
    {
        if (empty($value)) {
            return true;
        }
        $d = DateTime::createFromFormat('Y-m-d', $value);
        return $d && $d->format('Y-m-d') === $value;
    }

    /**
     * Main callback — fetch and return transactions.
     */
    public static function get_transactions(WP_REST_Request $request)
    {
        if (!class_exists('WooCommerce')) {
            return new WP_Error(
                'woocommerce_not_active',
                __('WooCommerce is not active.', 'wc-transaction-api'),
                ['status' => 500]
            );
        }

        $startdate = $request->get_param('startdate');
        $enddate = $request->get_param('enddate');
        $status = $request->get_param('status');
        $per_page = $request->get_param('per_page') ?: 100;
        $page = $request->get_param('page') ?: 1;

        // Build statuses array
        $valid_statuses = wc_get_order_statuses(); // e.g. ['wc-pending' => 'Pending', ...]
        $statuses_to_query = [];

        if (!empty($status)) {
            $requested = array_map('trim', explode(',', $status));
            foreach ($requested as $s) {
                $s = trim($s);

                // Correctly handle both 'completed' and 'wc-completed'
                if (str_starts_with($s, 'wc-')) {
                    $prefixed = $s;           // already has prefix
                } else {
                    $prefixed = 'wc-' . $s;  // add prefix
                }

                if (array_key_exists($prefixed, $valid_statuses)) {
                    $statuses_to_query[] = $prefixed;
                }
            }

            if (empty($statuses_to_query)) {
                return new WP_Error(
                    'invalid_status',
                    __('None of the provided statuses are valid WooCommerce order statuses.', 'wc-transaction-api'),
                    ['status' => 400]
                );
            }
        } else {
            // Default: return all statuses
            $statuses_to_query = array_keys($valid_statuses);
        }

        // Build WC_Order_Query args
        $query_args = [
            'limit' => $per_page,
            'page' => $page,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => $statuses_to_query,
            'paginate' => true,
        ];

        if (!empty($startdate)) {
            $query_args['date_created'] = '>=' . $startdate . ' 00:00:00';
        }

        if (!empty($enddate)) {
            // If both startdate and enddate given, use range
            if (!empty($startdate)) {
                $query_args['date_created'] = $startdate . ' 00:00:00...' . $enddate . ' 23:59:59';
            } else {
                $query_args['date_created'] = '<=' . $enddate . ' 23:59:59';
            }
        }

        $results = wc_get_orders($query_args);
        $orders = $results->orders ?? [];
        $total = $results->total ?? 0;

        $transactions = [];
        foreach ($orders as $order) {
            $transactions[] = (new TransactionController())->format_transaction($order);
        }

        $response = rest_ensure_response([
            'success' => true,
            'total' => (int) $total,
            'page' => (int) $page,
            'per_page' => (int) $per_page,
            'total_pages' => (int) ceil($total / $per_page),
            'transactions' => $transactions,
        ]);

        // Expose pagination headers
        $response->header('X-WP-Total', $total);
        $response->header('X-WP-TotalPages', ceil($total / $per_page));

        return $response;
    }

    /**
     * Format a single WC_Order into a comprehensive transaction object.
     */
    private function format_transaction(WC_Order $order)
    {

        // ── Core order info ──────────────────────────────────────────
        $transaction = [
            'id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'status' => $order->get_status(),
            'status_label' => wc_get_order_status_name($order->get_status()),
            'currency' => $order->get_currency(),
            'currency_symbol' => get_woocommerce_currency_symbol($order->get_currency()),

            // ── Dates ────────────────────────────────────────────────
            'dates' => [
                'created' => $this->format_datetime($order->get_date_created()),
                'modified' => $this->format_datetime($order->get_date_modified()),
                'completed' => $this->format_datetime($order->get_date_completed()),
                'paid' => $this->format_datetime($order->get_date_paid()),
            ],

            // ── Financials ───────────────────────────────────────────
            'financials' => [
                'subtotal' => $order->get_subtotal(),
                'total' => $order->get_total(),
                'total_tax' => $order->get_total_tax(),
                'total_discount' => $order->get_total_discount(),
                'shipping_total' => $order->get_shipping_total(),
                'shipping_tax' => $order->get_shipping_tax(),
                'cart_tax' => $order->get_cart_tax(),
                'discount_total' => $order->get_discount_total(),
                'discount_tax' => $order->get_discount_tax(),
                'total_refunded' => $order->get_total_refunded(),
                'amount_due' => $order->get_total() - $order->get_total_refunded(),
                'formatted_total' => $order->get_formatted_order_total(),
            ],

            // ── Payment ──────────────────────────────────────────────
            'payment' => [
                'method' => $order->get_payment_method(),
                'method_title' => $order->get_payment_method_title(),
                'transaction_id' => $order->get_transaction_id(),
            ],

            // ── Billing ──────────────────────────────────────────────
            'billing' => [
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'full_name' => $order->get_formatted_billing_full_name(),
                'company' => $order->get_billing_company(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
                'address_1' => $order->get_billing_address_1(),
                'address_2' => $order->get_billing_address_2(),
                'city' => $order->get_billing_city(),
                'state' => $order->get_billing_state(),
                'postcode' => $order->get_billing_postcode(),
                'country' => $order->get_billing_country(),
                'country_name' => WC()->countries->countries[$order->get_billing_country()] ?? $order->get_billing_country(),
                'address_formatted' => $order->get_formatted_billing_address(),
            ],

            // ── Shipping ─────────────────────────────────────────────
            'shipping' => [
                'first_name' => $order->get_shipping_first_name(),
                'last_name' => $order->get_shipping_last_name(),
                'full_name' => $order->get_formatted_shipping_full_name(),
                'company' => $order->get_shipping_company(),
                'address_1' => $order->get_shipping_address_1(),
                'address_2' => $order->get_shipping_address_2(),
                'city' => $order->get_shipping_city(),
                'state' => $order->get_shipping_state(),
                'postcode' => $order->get_shipping_postcode(),
                'country' => $order->get_shipping_country(),
                'country_name' => WC()->countries->countries[$order->get_shipping_country()] ?? $order->get_shipping_country(),
                'address_formatted' => $order->get_formatted_shipping_address(),
                'phone' => $order->get_shipping_phone(),
            ],

            // ── Customer ─────────────────────────────────────────────
            'customer' => $this->get_customer_data($order),

            // ── Line items ───────────────────────────────────────────
            'line_items' => $this->get_line_items($order),

            // ── Shipping lines ───────────────────────────────────────
            'shipping_lines' => $this->get_shipping_lines($order),

            // ── Tax lines ────────────────────────────────────────────
            'tax_lines' => $this->get_tax_lines($order),

            // ── Fee lines ────────────────────────────────────────────
            'fee_lines' => $this->get_fee_lines($order),

            // ── Coupon lines ─────────────────────────────────────────
            'coupon_lines' => $this->get_coupon_lines($order),

            // ── Refunds ──────────────────────────────────────────────
            'refunds' => $this->get_refunds($order),

            // ── Notes / History ──────────────────────────────────────
            'notes' => $this->get_order_notes($order),

            // ── Meta data ────────────────────────────────────────────
            'meta_data' => $this->get_meta_data($order),

            // ── BuddyBoss / BuddyPress member data ───────────────────
            'buddyboss' => $this->get_buddyboss_data($order),

            // ── Misc ─────────────────────────────────────────────────
            'customer_note' => $order->get_customer_note(),
            'cart_hash' => $order->get_cart_hash(),
            'order_key' => $order->get_order_key(),
            'created_via' => $order->get_created_via(),
            'customer_ip_address' => $order->get_customer_ip_address(),
            'customer_user_agent' => $order->get_customer_user_agent(),
            'checkout_payment_url' => $order->get_checkout_payment_url(),
            'checkout_order_received_url' => $order->get_checkout_order_received_url(),
            'view_order_url' => $order->get_view_order_url(),
            'admin_url' => get_admin_url(null, 'post.php?post=' . $order->get_id() . '&action=edit'),
        ];

        return $transaction;
    }

    // ── Helper: format WC_DateTime ────────────────────────────────────
    private function format_datetime($dt)
    {
        if (!$dt instanceof WC_DateTime) {
            return null;
        }
        return [
            'date' => $dt->date('Y-m-d'),
            'time' => $dt->date('H:i:s'),
            'datetime' => $dt->date('Y-m-d H:i:s'),
            'timestamp' => $dt->getTimestamp(),
            'human' => $dt->date(get_option('date_format') . ' ' . get_option('time_format')),
        ];
    }

    // ── Helper: customer data ─────────────────────────────────────────
    private function get_customer_data(WC_Order $order)
    {
        $customer_id = $order->get_customer_id();
        $data = [
            'id' => $customer_id,
            'is_guest' => ($customer_id === 0),
            'username' => '',
            'display_name' => '',
            'avatar_url' => '',
            'registered_date' => '',
            'total_orders' => 0,
            'total_spent' => 0,
        ];

        if ($customer_id) {
            $wp_user = get_userdata($customer_id);
            if ($wp_user) {
                $data['username'] = $wp_user->user_login;
                $data['display_name'] = $wp_user->display_name;
                $data['registered_date'] = $wp_user->user_registered;
                $data['avatar_url'] = get_avatar_url($customer_id, ['size' => 96]);
            }

            // WooCommerce customer stats
            try {
                $wc_customer = new WC_Customer($customer_id);
                $data['total_orders'] = $wc_customer->get_order_count();
                $data['total_spent'] = $wc_customer->get_total_spent();
            } catch (Exception $e) {
                // Silently skip if customer not found
            }
        }

        return $data;
    }

    // ── Helper: line items ────────────────────────────────────────────
    private function get_line_items(WC_Order $order)
    {
        $items = [];
        foreach ($order->get_items() as $item_id => $item) {
            /** @var WC_Order_Item_Product $item */
            $product = $item->get_product();
            $product_id = $item->get_product_id();
            $variation = $item->get_variation_id();

            $items[] = [
                'id' => $item_id,
                'name' => $item->get_name(),
                'product_id' => $product_id,
                'variation_id' => $variation ?: null,
                'sku' => $product ? $product->get_sku() : '',
                'quantity' => $item->get_quantity(),
                'subtotal' => $item->get_subtotal(),
                'subtotal_tax' => $item->get_subtotal_tax(),
                'total' => $item->get_total(),
                'total_tax' => $item->get_total_tax(),
                'taxes' => $item->get_taxes(),
                'meta_data' => $this->item_meta($item),
                'product' => $product ? [
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'type' => $product->get_type(),
                    'sku' => $product->get_sku(),
                    'price' => $product->get_price(),
                    'regular_price' => $product->get_regular_price(),
                    'sale_price' => $product->get_sale_price(),
                    'stock_status' => $product->get_stock_status(),
                    'categories' => wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']),
                    'tags' => wp_get_post_terms($product_id, 'product_tag', ['fields' => 'names']),
                    'image_url' => wp_get_attachment_url($product->get_image_id()) ?: wc_placeholder_img_src(),
                    'permalink' => get_permalink($product_id),
                ] : null,
            ];
        }
        return $items;
    }

    // ── Helper: shipping lines ────────────────────────────────────────
    private function get_shipping_lines(WC_Order $order)
    {
        $lines = [];
        foreach ($order->get_items('shipping') as $item_id => $item) {
            /** @var WC_Order_Item_Shipping $item */
            $lines[] = [
                'id' => $item_id,
                'method_title' => $item->get_method_title(),
                'method_id' => $item->get_method_id(),
                'instance_id' => $item->get_instance_id(),
                'total' => $item->get_total(),
                'total_tax' => $item->get_total_tax(),
                'taxes' => $item->get_taxes(),
                'meta_data' => $this->item_meta($item),
            ];
        }
        return $lines;
    }

    // ── Helper: tax lines ─────────────────────────────────────────────
    private function get_tax_lines(WC_Order $order)
    {
        $lines = [];
        foreach ($order->get_items('tax') as $item_id => $item) {
            /** @var WC_Order_Item_Tax $item */
            $lines[] = [
                'id' => $item_id,
                'rate_code' => $item->get_rate_code(),
                'rate_id' => $item->get_rate_id(),
                'label' => $item->get_label(),
                'compound' => $item->get_compound(),
                'tax_total' => $item->get_tax_total(),
                'shipping_tax_total' => $item->get_shipping_tax_total(),
                'rate_percent' => $item->get_rate_percent(),
                'meta_data' => $this->item_meta($item),
            ];
        }
        return $lines;
    }

    // ── Helper: fee lines ─────────────────────────────────────────────
    private function get_fee_lines(WC_Order $order)
    {
        $lines = [];
        foreach ($order->get_items('fee') as $item_id => $item) {
            /** @var WC_Order_Item_Fee $item */
            $lines[] = [
                'id' => $item_id,
                'name' => $item->get_name(),
                'total' => $item->get_total(),
                'total_tax' => $item->get_total_tax(),
                'taxes' => $item->get_taxes(),
                'taxable' => $item->get_tax_status() === 'taxable',
                'tax_class' => $item->get_tax_class(),
                'meta_data' => $this->item_meta($item),
            ];
        }
        return $lines;
    }

    // ── Helper: coupon lines ──────────────────────────────────────────
    private function get_coupon_lines(WC_Order $order)
    {
        $lines = [];
        foreach ($order->get_items('coupon') as $item_id => $item) {
            /** @var WC_Order_Item_Coupon $item */
            $lines[] = [
                'id' => $item_id,
                'code' => $item->get_code(),
                'discount' => $item->get_discount(),
                'discount_tax' => $item->get_discount_tax(),
                'meta_data' => $this->item_meta($item),
            ];
        }
        return $lines;
    }

    // ── Helper: refunds ───────────────────────────────────────────────
    private function get_refunds(WC_Order $order)
    {
        $refunds = [];
        foreach ($order->get_refunds() as $refund) {
            $refunds[] = [
                'id' => $refund->get_id(),
                'date' => $this->format_datetime($refund->get_date_created()),
                'amount' => $refund->get_amount(),
                'reason' => $refund->get_reason(),
                'refunded_by' => $refund->get_refunded_by(),
                'meta_data' => $this->get_meta_data($refund),
            ];
        }
        return $refunds;
    }

    // ── Helper: order notes ───────────────────────────────────────────
    private function get_order_notes(WC_Order $order)
    {
        $notes = [];
        $raw = wc_get_order_notes(['order_id' => $order->get_id()]);
        foreach ($raw as $note) {
            $notes[] = [
                'id' => $note->comment_ID,
                'date' => $note->comment_date,
                'content' => $note->comment_content,
                'customer_note' => (bool) $note->customer_note,
                'added_by' => $note->added_by,
            ];
        }
        return $notes;
    }

    // ── Helper: order meta data ───────────────────────────────────────
    private function get_meta_data($object)
    {
        $meta = [];
        $raw = $object->get_meta_data();
        // Filter out internal WooCommerce keys that start with underscore
        foreach ($raw as $meta_item) {
            $data = $meta_item->get_data();
            if (strpos($data['key'], '_') !== 0) {
                $meta[] = [
                    'id' => $data['id'],
                    'key' => $data['key'],
                    'value' => $data['value'],
                ];
            }
        }
        return $meta;
    }

    // ── Helper: item-level meta ───────────────────────────────────────
    private function item_meta(WC_Order_Item $item)
    {
        $meta = [];
        foreach ($item->get_formatted_meta_data('_', true) as $meta_id => $meta_item) {
            $meta[] = [
                'id' => $meta_id,
                'key' => $meta_item->key,
                'value' => $meta_item->value,
                'display_key' => $meta_item->display_key,
                'display_value' => wp_strip_all_tags($meta_item->display_value),
            ];
        }
        return $meta;
    }

    // ── Helper: BuddyBoss / BuddyPress data ──────────────────────────
    private function get_buddyboss_data(WC_Order $order)
    {
        $data = [];
        $customer_id = $order->get_customer_id();

        if (!$customer_id) {
            return $data;
        }

        // BuddyPress / BuddyBoss extended profile fields
        if (function_exists('bp_get_profile_field_data')) {
            // Fetch all xprofile groups and fields
            if (function_exists('bp_xprofile_get_groups')) {
                $groups = bp_xprofile_get_groups([
                    'fetch_fields' => true,
                    'fetch_field_data' => true,
                    'user_id' => $customer_id,
                ]);

                foreach ($groups as $group) {
                    $group_data = [
                        'group_id' => $group->id,
                        'group_name' => $group->name,
                        'fields' => [],
                    ];

                    if (!empty($group->fields)) {
                        foreach ($group->fields as $field) {
                            $group_data['fields'][] = [
                                'field_id' => $field->id,
                                'field_name' => $field->name,
                                'value' => isset($field->data->value)
                                    ? maybe_unserialize($field->data->value)
                                    : '',
                            ];
                        }
                    }

                    $data['xprofile_groups'][] = $group_data;
                }
            }
        }

        // BuddyBoss member type
        if (function_exists('bp_get_member_type')) {
            $member_type = bp_get_member_type($customer_id);
            $data['member_type'] = $member_type ?: null;
        }

        // BuddyBoss profile URL
        if (function_exists('bp_core_get_user_domain')) {
            $data['profile_url'] = bp_core_get_user_domain($customer_id);
        }

        // BuddyBoss profile photo
        if (function_exists('bb_attachments_get_profile_photo_url')) {
            $data['profile_photo'] = bb_attachments_get_profile_photo_url([
                'user_id' => $customer_id,
                'type' => 'thumb',
            ]);
        }

        // BuddyBoss connected groups (if BuddyPress Groups is active)
        if (function_exists('groups_get_user_groups')) {
            $user_groups = groups_get_user_groups($customer_id);
            $data['groups'] = [];

            if (!empty($user_groups['groups'])) {
                foreach ($user_groups['groups'] as $group_id) {
                    $group = groups_get_group($group_id);
                    $data['groups'][] = [
                        'id' => $group_id,
                        'name' => $group->name,
                        'slug' => $group->slug,
                    ];
                }
            }
        }

        // BuddyBoss followers count (if BuddyBoss Connections is active)
        if (function_exists('bp_get_follower_ids')) {
            $data['followers_count'] = count(bp_get_follower_ids(['user_id' => $customer_id]));
        }

        return $data;
    }
}
