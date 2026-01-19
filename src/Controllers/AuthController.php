<?php

namespace SRC\Controllers;

use SRC\Utils\Jwt;
use SRC\Utils\Response;
use SRC\Config\Jwt as JwtConfig;
use SRC\Config\Config;
use WP_REST_Request;

class AuthController
{
    public static function login(WP_REST_Request $request)
    {
        global $wpdb;

        $body = $request->get_json_params();
        $email = isset($body['email']) ? sanitize_email($body['email']) : '';

        $table = $wpdb->prefix . 'users';

        $acceptedUsers = (array) Config::get('ACCEPTED_USERS', []);

        if (!$email) {
            return Response::error('Email is required', 400);
        }

        if (!in_array($email, $acceptedUsers, false)) {
            return Response::error('Access denied', 403);
        }

        $user = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT ID, user_email FROM {$table} WHERE user_email = %s LIMIT 1",
                $email
            )
        );

        if (!$user) {
            return Response::error('Invalid credentials', 401);
        }

        $payload = [
            'iss' => JwtConfig::ISSUER,
            'sub' => (int) $user->ID,
            'iat' => time(),
            'email'=> $email,
            'exp' => time() + JwtConfig::EXPIRY,
        ];

        return Response::success([
            'token' => Jwt::encode($payload),
        ]);
    }

    public static function create(WP_REST_Request $request)
    {
        global $wpdb;

        $body = $request->get_json_params();
        $table = $wpdb->prefix . 'users';

        $email = isset($body['email']) ? sanitize_email($body['email']) : '';
        $name = isset($body['name']) ? sanitize_text_field($body['name']) : '';

        if (!$email || !$name) {
            return Response::error("Email and name are required. This is what I got $email and $name", 400);
        }

        if (!is_email($email)) {
            return Response::error('Invalid email format', 400);
        }

        $acceptedUsers = (array) Config::get('ACCEPTED_USERS', []);

        if (!in_array($email, $acceptedUsers, false)) {
            return Response::error('Access denied', 403);
        }

        $user = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT ID, user_email FROM {$table} WHERE user_email = %s LIMIT 1",
                $email
            )
        );

        if ($user) {
            return Response::error('User already exists', 409);
        }

        $result = $wpdb->insert(
            $table,
            [
                'user_login' => $email,
                'user_email' => $email,
                'display_name' => $name
            ],
            ['%s', '%s', '%s']
        );

        if ($result === false) {
            return Response::error('Failed to create user', 500);
        }

        return Response::success([
            'name' => $name,
            'email' => $email
        ]);
    }
}