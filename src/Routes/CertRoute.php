<?php

namespace SRC\Routes;

use SRC\Controllers\CertController;
use SRC\Middleware\Auth;

class CertRoute {
    public static function register() {
        global $part_a;
        $part_a='cison/v1';

        register_rest_route($part_a, '/cert/next-number', [
            'methods' => 'GET',
            'callback' => [CertController::class, 'getNextCertNumber'],
            'permission_callback' => [Auth::class, 'jwt'],
        ]);
    }
}