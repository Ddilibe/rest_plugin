<?php

namespace SRC\Routes;

use SRC\Controllers\CertController;
use SRC\Middleware\Auth;

class CertRoute {
    public static function register() {
        global $part_a;
        $part_a='cison/v1/cert';

        register_rest_route($part_a, '/next-number', [
            'methods' => 'GET',
            'callback' => [CertController::class, 'getNextCertNumber'],
            'permission_callback' => [Auth::class, 'jwt'],
        ]);

        register_rest_route($part_a, '/add-new', [
            'methods' => 'POST',
            'callback' => [CertController::class, 'addNewCertification'],
            'permission_callback' => [Auth::class, 'jwt'],
        ]);
    }
}