<?php

namespace SRC\Routes;

use SRC\Controllers\ConferenceRegistrationController;
use SRC\Middleware\Auth;

class ConferenceRegistrationRoute
{
    public static function register()
    {
        register_rest_route('cison/v1', '/conference-registrations', [
            'methods'  => 'GET',
            'callback' => [ConferenceRegistrationController::class, 'get_transactions'],
            'permission_callback' => [Auth::class, 'jwt'],
            'args' => [ConferenceRegistrationController::class, 'get_endpoint_args'],
        ]);
    }
}