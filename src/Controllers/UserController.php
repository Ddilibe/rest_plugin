<?php

namespace SRC\Controllers;

use SRC\Config\Config;

class UserController
{
    public static function getAllUsers()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'users';

        $users = $wpdb->get_results("SELECT * FROM {$table_name}", ARRAY_A);

        return rest_ensure_response($users);
    }
}