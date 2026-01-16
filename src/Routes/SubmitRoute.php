<?php

namespace SRC\Routes;

use SRC\Controllers\SubmitController;
use SRC\Middleware\Auth;

class SubmitRoute
{
    public static function register()
    {
        register_rest_route('cison/v1', '/submit', [
            'methods'  => 'POST',
            'callback' => [SubmitController::class, 'handle'],
            'permission_callback' => [Auth::class, 'api_key'],
        ]);
    }
}
