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

    public static function getMemebersThatAreTransitingThatHavePaid() {
        global $wpdb;

        $all_data = array();


        $table_name = $wpdb->prefix . 'users';
        
        $members = $wpdb->get_results("SELECT * FROM {$table_name}", ARRAY_A);

        foreach ($members as $key => $value) {
            $user_id = $value["user_id"];
            if (!function_exists('bp_get_profile_field_data')) {
                continue;
            }

            $is_transiting = bp_get_profile_field_data([
                'field'   => 1595,
                'user_id'=> $user_id,
            ]) === 'Yes';

            $member_id = bp_get_profile_field_data([
                'field'   => 894,
                'user_id'=> $user_id,
            ]) ?: '';

            if ($is_transiting) {
                continue;
            }

            $phone_number = bp_get_profile_field_data([
                'field'   => 5,
                'user_id'=> $user_id,
            ]);


            $single_data = 
            array(
                "user_id"=> $value["user_id"],
                "user_login"=> $value["user_login"],
                "user_email"=> $value["user_email"],
                "joined_date" => $value["user_registered"],
                "phone_number"=> $phone_number,
                "member_id"=> $member_id,
            );

            $all_data[] = $single_data;
        }

        $response = ["data"=>$all_data, "status"=>"success"];

        return rest_ensure_response($response,200);
    }
}
