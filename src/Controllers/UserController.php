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

    public static function getTransitingMembers() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bp_xprofile_data';

        $users = $wpdb->get_results("SELECT * FROM {$table_name}",ARRAY_A);

        return rest_ensure_response($users);
    }

    public static function getGroupMembers() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'bp_groups_members';
        
        $members = $wpdb->get_results("SELECT * FROM {$table_name}", ARRAY_A);

        return rest_ensure_response($members);
    }
}