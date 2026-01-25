<?php

namespace SRC\Controllers;

use WP_REST_REQUEST;
use WP_Error;


class ProductController {

    public static function getAllProducts() {
        $args = [
            'limit' => -1,
            'status' => array('publish', 'draft', 'pending', 'private'),
            'return' => 'objects',
        ];

        $products = wc_get_products($args);
        $data = [];

        foreach ($products as $product) {
            $data[] = [
                'id'    => $product->get_id(),
                'name'  => $product->get_name(),
                'price' => $product->get_price(),
                'sku'   => $product->get_sku(),
                'image' => wp_get_attachment_image_src($product->get_image_id(), 'full')[0] ?? '',
            ];
        }

        return rest_ensure_response($data);

    }

    public static function checkWhetherProductWasPurchasedByUser(WP_REST_REQUEST $request) {

        $body = $request->get_json_params();
        $user_id = isset($body['user_id']) ? sanitize_text_field($body['user_id']) : '';
        $product_id = isset($body['product_id']) ?intval($body['product_id']) : 0;

        if (!$user_id | $product_id === 0) {
            return new WP_Error('not_found', 'Member ID not found', ['status' => 404]);
        }
        $has_bought = wc_customer_bought_product('', $user_id, $product_id);

        return rest_ensure_response([
            'member_id' => $user_id,
            'product_id' => $product_id,
            'has_bought' => $has_bought,
            'status' => 'success'
        ], 200);

    }

    public static function getOrders() {
        $args = array(
            'limit'  => -1,
            'status' => array('wc-processing', 'wc-completed'), 
            'return' => 'objects',
            'type'   => 'shop_order', // This excludes refunds!
        );

        $orders = wc_get_orders($args);
        $data   = array();

        foreach ($orders as $order) {
            // Double-check it's not a refund
            if ($order instanceof \WC_Order_Refund) {
                continue;
            }
            
            $items = array();
            foreach ($order->get_items() as $item_id => $item) {
                $items[] = array(
                    'product_id'   => $item->get_product_id(),
                    'product_name' => $item->get_name(),
                    'quantity'     => $item->get_quantity(),
                    'total'        => $item->get_total(),
                );
            }

            $date_paid = null;
            if ($order->get_date_paid()) {
                $date_paid = $order->get_date_paid()->format('Y-m-d H:i:s');
            }

            $data[] = array(
                'order_id'        => $order->get_id(),
                'first_name'      => $order->get_billing_first_name(),
                'surname'         => $order->get_billing_last_name(),
                'email'           => $order->get_billing_email(),
                'phone'           => $order->get_billing_phone(),
                'items'           => $items,
                'total'           => $order->get_total(),
                'status'          => $order->get_status(),
                'date_paid'       => $date_paid,
                'payment_method'  => $order->get_payment_method_title(),
                'transaction_id'  => $order->get_transaction_id(),
                'billing_state'   => $order->get_billing_state(),
            );
        }

        return rest_ensure_response($data);
    }
}