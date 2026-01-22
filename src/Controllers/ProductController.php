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
            'member_id' => $member_id,
            'has_bought' => $has_bought,
            'status' => 'success'
        ], 200);

    }
}