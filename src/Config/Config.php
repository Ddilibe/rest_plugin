<?php
namespace SRC\Config;

class Config
{
    public static function get($key, $default = null)
    {   
        if (defined($key)) {
            return constant($key);
        }

        $env = getenv($key);
        if ($env !== false) {
            return $env;
        }

        return $default;
    }
}
