<?php

namespace SRC\Controllers;

use SRC\Utils\Response;

class HelloController
{
    public static function handle($request)
    {
        return Response::success([
            'message' => 'Hello from modular WordPress API'
        ]);
    }
}
