<?php

namespace SRC\Utils;

use WP_REST_Response;
use WP_Error;

class Response
{
    public static function success($data = [], $status = 200)
    {
        return new WP_REST_Response([
            'status' => 'success',
            'data'   => $data
        ], $status);
    }

    public static function error($message, $status = 400)
    {
        return new WP_Error(
            'api_error',
            $message,
            ['status' => $status]
        );
    }
}
