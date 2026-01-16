<?php

namespace SRC\Utils;

use SRC\Config\Jwt as JwtConfig;
use SRC\Config\Config;
use WP_Error;

class Jwt
{
    public static function encode(array $payload)
    {
        $header  = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode($payload));

        $signature = hash_hmac(
            'sha256',
            "$header.$payload",
            JwtConfig::SECRET,
            true
        );

        return "$header.$payload." . base64_encode($signature);
    }

    public static function decode($token)
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return new WP_Error('jwt_invalid', 'Invalid token');
        }

        [$header, $payload, $signature] = $parts;

        $valid = base64_encode(hash_hmac(
            'sha256',
            "$header.$payload",
            JwtConfig::SECRET,
            true
        ));

        if (!hash_equals($valid, $signature)) {
            return new WP_Error('jwt_invalid', 'Invalid signature');
        }

        $data = json_decode(base64_decode($payload), true);

        if ($data['exp'] < time()) {
            return new WP_Error('jwt_expired', 'Token expired');
        }

        $email = $data['email'];

        $acceptedUsers = (array) Config::get('ACCEPTED_USERS', []);

        if (!in_array($email, $acceptedUsers, false)) {
            return new WP_Error('Access denied', "You are not allowed to access this service");
        }

        return $data;
    }
}
