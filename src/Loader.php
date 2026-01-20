<?php
namespace SRC;

use SRC\Routes\AuthRoute;
use SRC\Routes\HelloRoute;
use SRC\Routes\SubmitRoute;
use SRC\Routes\UserRoute;
use SRC\Routes\ProductRoute;


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
    }
}
