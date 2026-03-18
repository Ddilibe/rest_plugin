<?php

namespace SRC\Routes;

use SRC\Middleware\Auth;
use SRC\Controllers\DataController;

class DataRoute
{

    public static function register()
    {
        global $root_api;
        $root_api = 'cison/v1/data';

        register_rest_route($root_api, '/users/complete-payment', [
            "methods" => "GET",
            "callback" => [DataController::class, "users_with_cleared_payments_2025_limit"],
            "permission_callback" => [Auth::class, "jwt"]
        ]);

        register_rest_route($root_api, '/users/partial-payment', [
            "methods" => "GET",
            "callback" => [DataController::class, "users_with_partial_payments_2025_limit"],
            "permission_callback" => [Auth::class, "jwt"]
        ]);

        register_rest_route($root_api, '/users/no-payment', [
            "methods" => "GET",
            "callback" => [DataController::class, "users_without_payments_2025_limit"],
            "permission_callback" => [Auth::class, "jwt"]
        ]);
    }
}