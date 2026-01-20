<?php

namespace SRC\Routes;

use SRC\Controllers\ProductController;
use SRC\Middleware\Auth;


class ProductRoute {

    public static function register() {
        global $part_a;
        $part_a='cison/v1';

        register_rest_route($part_a, '/all-products', [
            "methods" => "GET",
            "callback" => [ProductController::class, 'getAllProducts'],
            "permission_callback" => [Auth::class, 'jwt']
        ]);
    }
}