<?php

namespace SRC\Controllers;

use SRC\Utils\Response;
use WP_REST_Request;

class SubmitController
{
    public static function handle(WP_REST_Request $request)
    {
        $name  = sanitize_text_field($request->get_param('name'));
        $email = sanitize_email($request->get_param('email'));

        if (!$name || !$email) {
            return Response::error('Name and email are required', 400);
        }

        return Response::success([
            'name'  => $name,
            'email' => $email,
        ]);
    }
}
