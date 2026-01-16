<?php

namespace SRC\Routes;

use SRC\Controllers\AuthController;


class AuthRoute
{
    public static function register()
    {
        global $part_a;
        $part_a='cison/v1';
        register_rest_route($part_a, '/auth/api-key', [
            'methods'  => ['POST', 'GET'],
            'callback' => [AuthController::class, 'login'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($part_a, '/auth/register', [
            'methods' => ['POST'],
            'callback' => [AuthController::class, 'create'],
            'permission_callback' => '__return_true',
        ]);
    }
}
