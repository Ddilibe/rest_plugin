<?php

namespace SRC\Routes;

use SRC\Controllers\StudentCertificateController;
use SRC\Middleware\Auth;

class StudentCertificateRoute
{
    public static function register()
    {
        $main_route = 'cison/v1/student-certificate';

        register_rest_route($main_route, '/eligible', [
            'methods' => 'GET',
            'callback' => [StudentCertificateController::class, 'list_eligible_students'],
            'permission_callback' => [Auth::class, 'jwt'],
        ]);

        register_rest_route($main_route, '/create', [
            'methods' => 'POST',
            'callback' => [StudentCertificateController::class, 'create_certificate'],
            'permission_callback' => [Auth::class, 'jwt'],
            'args' => [
                'user_id' => [
                    'type' => 'integer',
                ],
                'student_number' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'member_id' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'student_name' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'student_email' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
                'programme' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'certificate_title' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'cert_type' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'file_url' => [
                    'type' => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ],
                'date_expiry' => [
                    'type' => 'string',
                ],
            ],
        ]);

        register_rest_route($main_route, '/delete', [
            'methods' => 'DELETE',
            'callback' => [StudentCertificateController::class, 'delete_certificate'],
            'permission_callback' => [Auth::class, 'jwt'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                ],
                'user_id' => [
                    'type' => 'integer',
                ],
                'student_number' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'member_id' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }
}
