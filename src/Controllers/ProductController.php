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
        );

        $orders = wc_get_orders($args);
        $data   = array();

        foreach ($orders as $order) {
            
            $purchased_product_ids = array();
            foreach ($order->get_items() as $item_id => $item) {
                $purchased_product_ids[] = $item->get_product_id();
            }

            $data[] = array(
                'order_id'     => $order->get_id(),
                'first_name'   => $order->get_billing_first_name(),
                'surname'      => $order->get_billing_last_name(),
                'email'        => $order->get_billing_email(),
                'product_ids'  => $purchased_product_ids,
                'total'        => $order->get_total(),
                'date_paid'    => $order->get_date_paid() ? $order->get_date_paid()->date('Y-m-d') : 'N/A',
            );
        }

        return rest_ensure_response($data);
    }
}