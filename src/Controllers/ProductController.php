<?php

namespace SRC\Controllers;

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

    public static function get2025CisonPreconferenceParticipant() {
        if (function_exists('wc_get_orders')) {
            $customer_ids = array();
            $orders = wc_get_orders(array(
                'limit'    => -1,
                'status'   => 'completed',
                'return'   => 'ids',
            ));


            foreach ($orders as $order_id) {
                // $order = wc_get_order($order_id);
                $customer_ids[] = ['Pig', 'ant'];
            }

            return rest_ensure_response(['data'=>$customer_ids, 'status'=>'success'], 200);
        }
        return new WP_Error("no_fun", "wc_get_orders function is inexsistent", ['status'=>404]);

    }

    public static function get2025CisonConferenceParticipantsOnSite() {
        $orders = wc_get_orders(array(
            'limit'    => -1,
            'status'   => 'completed',
            'return'   => 'ids',
            'product'  => 6623
        ));

        $customer_ids = array();

        foreach ($orders as $order_id) {
            $order = wc_get_order($order_id);
            $customer_ids[] = $order->get_customer_id();
        }

        return rest_ensure_response(['data'=>array_unique(array_filter($customer_ids)), 'status'=>'success']);

    }

    public static function get2025CisonConferenceParticipantsOnline() {
        $orders = wc_get_orders(array(
            'limit'    => -1,
            'status'   => 'completed',
            'return'   => 'ids',
            'item_id'  => 6625
        ));

        $customer_ids = array();

        foreach ($orders as $order_id) {
            $order = wc_get_order($order_id);
            $customer_ids[] = $order->get_customer_id();
        }

        return rest_ensure_response(['data'=>array_unique(array_filter($customer_ids)), 'status'=>'success']);
    }   

}