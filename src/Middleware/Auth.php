<?php

namespace SRC\Middleware;

use SRC\Utils\Jwt;

class Auth
{
    public static function jwt($request)
    {
        $auth = $request->get_header('authorization');
        if (!$auth || !str_starts_with($auth, 'Bearer ')) {
            return false;
        }

        $token = trim(str_replace('Bearer', '', $auth));
        return !is_wp_error(Jwt::decode($token));
    }
}
