<?php

namespace SRC\Controllers;

use SRC\Config\Config;
use SRC\Utils\money;
use SRC\Utils\Certificate;


define('CISON_CURRENT_YEAR', (int) date('Y'));
define('CISON_CERT_TABLE', Config::get('CISON_CERT_TABLE', ''));

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

        $certificates = $wpdb->get_results("SELECT * FROM {$CISON_CERT_TABLE}", ARRAY_A);

        
        
        foreach ($members as $value) {
            $user_id = (int) $value["ID"];
            if (!function_exists('bp_get_profile_field_data')) {
                continue;
            }
            
            $is_transiting = bp_get_profile_field_data([
                'field'   => 1595,
                'user_id'=> $user_id,
                ]) === 'Yes';
                
            $has_certificate = array_filter($certificates, function($certificate){
                return $certificate["user_id"] === $user_id;
            });

            $member_id = bp_get_profile_field_data([
                'field'   => 894,
                'user_id'=> $user_id,
            ]) ?: '';

            if (!$is_transiting) {
                continue;
            }

            $phone_number = bp_get_profile_field_data([
                'field'   => 5,
                'user_id'=> $user_id,
            ]);

            $firstname = function_exists('bp_get_profile_field_data')
                ? bp_get_profile_field_data(['field' => 1, 'user_id' => $user_id])
                : '';
            $middlename = function_exists('bp_get_profile_field_data')
                ? bp_get_profile_field_data(['field' => 864, 'user_id' => $user_id])
                : '';
            $surname = function_exists('bp_get_profile_field_data')
                ? bp_get_profile_field_data(['field' => 2, 'user_id' => $user_id])
                : '';


            $single_data = array(
                "user_id"=> $user_id,
                "first_name"=> $firstname,
                "middle_name"=> $middlename,
                "last_name"=> $surname,
                "user_login"=> $value["user_login"],
                "user_email"=> $value["user_email"],
                "joined_date" => $value["user_registered"],
                "phone_number"=> $phone_number,
                "display_name"=> $value["display_name"],
                "member_id"=> $member_id,
                "certificate_validity" => cison_preview_user_eligibility($user_id),
                "has_certificate" => $has_certificate[0] ? "Yes" : "No",

            );

            $all_data[] = $single_data;
        }

        $response = ["data"=>$all_data, "status"=>"success"];

        return rest_ensure_response($response,200);
    }

}
