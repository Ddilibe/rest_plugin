<?php

namespace SRC\Controllers;

class ProductController {

    public static function getAllProducts() {
        $args = [
            'limit' => -1,
            'status' => 'publish',
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

}