<?php
namespace SRC;

use SRC\Routes\AuthRoute;
use SRC\Routes\HelloRoute;
use SRC\Routes\SubmitRoute;
use SRC\Routes\UserRoute;
use SRC\Routes\ProductRoute;
use SRC\Routes\CertRoute;
use SRC\Routes\DataRoute;


class Loader
{
    public static function init()
    {
        add_action('rest_api_init', [self::class, 'routes']);
    }

    public static function routes()
    {
        AuthRoute::register();
        HelloRoute::register();
        SubmitRoute::register();
        UserRoute::register();
        ProductRoute::register();
        CertRoute::register();
        DataRoute::register();
    }
}

// 12110,12112,12114,12116

// Build payment link (for unpaid ones) - uses ORIGINAL "product_id" if present
