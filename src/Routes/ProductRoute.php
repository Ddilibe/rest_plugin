<?php

namespace SRC\Routes;

use SRC\Controllers\ProductController;
use SRC\Middleware\Auth;


class ProductRoute {

    public static function register() {
        global $part_a;
        $part_a='cison/v1/prod';

        register_rest_route($part_a, '/all-products', [
            "methods" => "GET",
            "callback" => [ProductController::class, 'getAllProducts'],
            "permission_callback" => [Auth::class, 'jwt']
        ]);
        register_rest_route($part_a, '/bought-product', [
            "methods" => "GET",
            "callback" => [ProductController::class, 'checkWhetherProductWasPurchasedByUser'],
            "permission_callback" => [Auth::class, 'jwt']
        ]);
        register_rest_route($part_a, '/all-orders', [
            "methods" => "GET",
            "callback" => [ProductController::class, 'getOrders'],
            "permission_callback" => [Auth::class, 'jwt']
        ]);
    }
}