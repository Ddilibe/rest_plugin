<?php

namespace SRC\Routes;

use SRC\Controllers\LearnController;
use SRC\Middleware\Auth;

class LearnRoute
{
    public static function register()
    {
        $api_root = "cison/v1/learn";
        register_rest_route(
            $api_root,
            "/registration/",
            [
                "methods" => "GET",
                "callback" => [
                    LearnController::class,
                    'get_user_details_from_memberid'
                ],
                "permission_callback" => [Auth::class, "jwt"]
            ]
        );
    }
}