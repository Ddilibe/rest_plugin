<?php

namespace SRC\Routes;

use SRC\Controllers\HelloController;

class HelloRoute
{
    public static function register()
    {
        register_rest_route('cison/v1', '/hello', [
            'methods'  => 'GET',
            'callback' => [HelloController::class, 'handle'],
            'permission_callback' => '__return_true',
        ]);
    }
}
