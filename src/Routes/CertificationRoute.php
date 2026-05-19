<?php

namespace SRC\Routes;

use SRC\Controllers\CertificationController;
use SRC\Middleware\Auth;
use WP_REST_Request;
use WP_Error;
use WP_REST_Response;


class CertificationRoute
{

    public static function register()
    {
        $main_route = "cison/v1/certification";
        register_rest_route(
            $main_route,
            '/create',
            [
                'methods' => 'POST',
                'callback' => function (WP_REST_Request $request) {
                    $cert = new CertificationController();
                    return $cert->handle_create_certificate($request);
                },
                'permission_callback' => [Auth::class, 'jwt']
            ]
        );
        register_rest_route(
            $main_route,
            '/update',
            [
                'methods' => 'PUT',
                'callback' => function (WP_REST_Request $request) {
                    $cert = new CertificationController();
                    return $cert->handle_update_certificate($request);
                },
                'permission_callback' => [Auth::class, 'jwt']
            ]
        );
        register_rest_route(
            $main_route,
            '/delete',
            [
                'methods' => 'DELETE',
                'callback' => function (WP_REST_Request $request) {
                    $cert = new CertificationController();
                    return $cert->handle_delete_certificate($request);
                },
                'permission_callback' => [Auth::class, 'jwt']
            ]
        );
    }
}