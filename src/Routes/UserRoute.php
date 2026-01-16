<?php

namespace SRC\Routes;

use SRC\Config\Config;
use SRC\Controllers\UserController;
use SRC\Middleware\Auth;


class UserRoute {

    public static function register() {
        register_rest_route('cison/v1', '/all-users', [
            'methods' => 'GET',
            'callback' => [UserController::class, 'getAllUsers'],
            'permission_callback' => [Auth::class, 'jwt'],
        ]);
    }
}