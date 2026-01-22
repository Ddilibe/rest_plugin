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
        register_rest_route($part_a, '/2025-cison-preconference-participants', [
            "methods" => "GET",
            "callback" => [ProductController::class, 'get2025CisonPreconferenceParticipant'],
            "permission_callback" => [Auth::class, 'jwt']
        ]);
        register_rest_route($part_a, '/2025-cison-conference-participants-onsite', [
            "methods" => "GET",
            "callback" => [ProductController::class, 'get2025CisonConferenceParticipantsOnSite'],
            "permission_callback" => [Auth::class, 'jwt']
        ]);
        register_rest_route($part_a, '/2025-cison-conference-participants-online', [
            "methods"=>"GET",
            'callback' => [ProductController::class, 'get2025CisonConferenceParticipantsOnline'],
            "permission_callback" => [Auth::class, 'jwt']
        ]);
    }
}