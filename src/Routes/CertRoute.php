<?php

namespace SRC\Routes;

use SRC\Controllers\DataController;
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

        register_rest_route($part_a, '/single-certificate',[
            'methods' => 'GET',
            'callback' => [CertController::class, 'singleCertificate'],
            'permission_callback' => [Auth::class, 'jwt'],
        ]);

        register_rest_route($part_a, '/add-2025-conference', [
            'methods' => 'POST',
            'callback' => [CertController::class, 'add2025Conference'],
            'permission_callback' => [Auth::class, 'jwt'],
        ]);

        register_rest_route($part_a, '/add-2025-preconference', [
            'methods' => 'POST',
            'callback' => [CertController::class, 'add2025PreConference'],
            'permission_callback' => [Auth::class, 'jwt'],
        ]);

        register_rest_route($part_a, '/get-2025-preconference', [
            'methods' => 'GET',
            'callback' => [CertController::class, 'get2025Preconference'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($part_a, '/get-2025-conference', [
            'methods' => 'GET',
            'callback' => [CertController::class, 'get2025Conference'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($part_a, '/drop-conference-tables', [
            'methods' => 'GET',
            'callback' => [CertController::class, 'dropTables'],
            'permission_callback' => [Auth::class, 'jwt'],
        ]);

        register_rest_route('cison/v1/data', '/get-all', [
            'methods' => 'GET',
            'callback' => [DataController::class, 'allUsers'],
            'permission_callback' => [Auth::class, 'jwt'],
        ]);
    }
}