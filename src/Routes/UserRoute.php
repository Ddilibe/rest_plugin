<?php

namespace SRC\Routes;

use SRC\Config\Config;
use SRC\Controllers\UserController;
use SRC\Middleware\Auth;


class UserRoute {

    public static function register() {
        global $part_a;
        $part_a='cison/v1';


        register_rest_route('cison/v1', '/all-users', [
            'methods' => 'GET',
            'callback' => [UserController::class, 'getAllUsers'],
            'permission_callback' => [Auth::class, 'jwt'],
        ]);
        register_rest_route('cison/v1', '/members', [
            'methods'=>'GET',
            'callback' => [UserController::class, 'getGroupMembers'],
            'permission_callback' => [Auth::class, 'jwt'],
        ]);
        register_rest_route('cison/v1', '/transiting', [
            'methods'=>'GET',
            'callback' => [UserController::class, 'getTransitingMembers'],
            'permission_callback' => [Auth::class, 'jwt'],
        ]);
        register_rest_route('cison/v1', '/validtransiting', [
            'methods'=>'GET',
            'callback' => [UserController::class, 'getMemebersThatAreTransitingThatHavePaid'],
            'permission_callback' => [Auth::class, 'jwt'],
        ]);
        register_rest_route('cison/v1', '/hascertificate', [
            'methods' => 'GET',
            'callback' => [UserController::class, 'getMembersThatHaveCertificate'],
            'permission_callback' => [Auth::class, 'jwt'],
        ]);
        register_rest_route('cison/v1', '/nocertificate', [
            'methods'=>'GET',
            'callback' => [UserController::class, 'getMembersThatDoNotHaveCertificate'],
            'permission_callback' => [Auth::class, 'jwt'],
        ]);
        register_rest_route('cison/v1', '/certificate', [
            'methods'=>'GET',
            'callback' => [UserController::class, 'allCertificate'],
            'permission_callback' => [Auth::class, 'jwt'],
        ]);
        register_rest_route('cison/v1', '/user', [
            'methods'=>'GET',
            'callback' => [UserController::class, 'getUserWithUserId'],
            'permission_callback' => [Auth::class, 'jwt'],
        ]);
        register_rest_route('cison/v1', '/user_id', [
            'methods' => 'GET',
            'callback' => [UserController::class, 'getUserIDWithMemberId'],
            'permission_callback' => [Auth::class, 'jwt']
        ]);
        register_rest_route('cison/v1', )
    }
}