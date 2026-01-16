<?php

namespace SRC\Middleware;

use WP_Error;

class RateLimit
{
    public static function limit($request, $max = 60)
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'rate_' . md5($ip);

        $count = (int) get_transient($key);

        if ($count >= $max) {
            return new WP_Error(
                'rate_limited',
                'Too many requests',
                ['status' => 429]
            );
        }

        set_transient($key, $count + 1, 60);
        return true;
    }
}
