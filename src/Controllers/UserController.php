<?php

namespace SRC\Controllers;

use SRC\Config\Config;
use SRC\Utils\money;
use SRC\Utils\Certificate;

use WP_Error;
use WP_REST_REQUEST;


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
            $paid_fees = cison_get_paid_fees($user_id);

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
                "has_certificate" => $has_certificate,
                "paid_fees" => $paid_fees,
                "is_transiting" => $is_transiting ? true : false,
            );

            $all_data[] = $single_data;
        }

        $response = ["data"=>$all_data, "status"=>"success"];

        return rest_ensure_response($response,200);
    }

    public static function getMembersThatHaveCertificate() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'users';

        $all_data = array();

        $all_users = $wpdb->get_results("SELECT * FROM {$table_name}", ARRAY_A);
        $certificates = $wpdb->get_results("SELECT * FROM {$CISON_CERT_TABLE}", ARRAY_A);

        foreach ($all_users as $value) {
            $user_id = (int) $value["ID"];
            
            $certificate_validity=cison_preview_user_eligibility($user_id);

            $has_certificate = array_filter($certificates, function ($certificate) {
                return $certificate["user_id"] === $user_id;
            });

            if ($has_certificate === 0) {
                continue;
            }

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
                    "has_certificate" => $has_certificate,
                    "certificate_validity" => $certificate_validity
                );
            $all_data[] = $single_data;
        }

        return rest_ensure_response(["data"=>$all_data, "status"=>"success"],200);
    }

    public static function getMembersThatDoNotHaveCertificate() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'users';

        $all_data = array();

        $all_users = $wpdb->get_results("SELECT * FROM {$table_name}", ARRAY_A);
        $certificates = $wpdb->get_results("SELECT * FROM {$CISON_CERT_TABLE}", ARRAY_A);

        foreach ($all_users as $value) {
            $user_id = (int) $value["ID"];
            
            $certificate_validity=cison_preview_user_eligibility($user_id);

            $has_certificate = array_filter($certificates, function ($certificate) {
                return $certificate["user_id"] === $user_id;
            });

            if (count($has_certificate) !== 0) {
                continue;
            }

            $firstname = function_exists('bp_get_profile_field_data')
                ? bp_get_profile_field_data(['field' => 1, 'user_id' => $user_id])
                : '';
            $middlename = function_exists('bp_get_profile_field_data')
                ? bp_get_profile_field_data(['field' => 864, 'user_id' => $user_id])
                : '';
            $surname = function_exists('bp_get_profile_field_data')
                ? bp_get_profile_field_data(['field' => 2, 'user_id' => $user_id])
                : '';

            $is_transiting = bp_get_profile_field_data([
                'field'   => 1595,
                'user_id'=> $user_id,
                ]) === 'Yes';

            $paid_fees = cison_get_paid_fees($user_id);

            $single_data = array(
                    "user_id"=> $user_id,
                    "first_name"=> $firstname,
                    "middle_name"=> $middlename,
                    "last_name"=> $surname,
                    "has_certificate" => $has_certificate,
                    "certificate_validity" => $certificate_validity,
                    "paid_fees" => $paid_fees,
                    "is_transiting" => $is_transiting,
                );
            $all_data[] = $single_data;
        }

        return rest_ensure_response(["data"=>$all_data, "status"=>"success"],200);
    }


    public static function allCertificate() {
        global $wpdb;
        $certificates = $wpdb->get_results("SELECT * FROM wprx_cison_certificates", ARRAY_A);
        return rest_ensure_response(["data"=>$certificates, "status"=>"success"]);
    }

    public static function getUserWithUserId(WP_REST_REQUEST $request) {
        global $wpdb;

        $body = $request->get_json_params();
        
        $user_id = isset($body['user_id']) ? sanitize_text_field($body['user_id']) : '';
        if (!$user_id) {
            return new WP_Error("invalid_id", "user ID is required", ['status' => 400]);
        }

        $certificate_validity=cison_preview_user_eligibility($user_id);

        $certificates = $wpdb->get_results("SELECT * FROM {$CISON_CERT_TABLE}", ARRAY_A);

        $has_certificate = array_filter($certificates, function ($certificate) {
            return $certificate["user_id"] === $user_id;
        });

        $firstname = function_exists('bp_get_profile_field_data') ? bp_get_profile_field_data(['field' => 1, 'user_id' => $user_id])
            : '';
        $middlename = function_exists('bp_get_profile_field_data') ? bp_get_profile_field_data(['field' => 864, 'user_id' => $user_id])
            : '';
        $surname = function_exists('bp_get_profile_field_data') ? bp_get_profile_field_data(['field' => 2, 'user_id' => $user_id])
            : '';

        $is_transiting = bp_get_profile_field_data([
            'field'   => 1595,
            'user_id'=> $user_id,
            ]) === 'Yes';
        
        $member_id = bp_get_profile_field_data([
                'field'   => 894,
                'user_id'=> $user_id,
            ]) ?: '';

        $paid_fees = cison_get_paid_fees($user_id);

        $custom_orders = array();

        if (function_exists('wc_get_orders')) {
            $orders = wc_get_orders([
                'customer_id' => $user_id,
                'status'      => ['completed', 'processing'],
                'limit'       => -1,
                'return'      => 'objects',
                'orderby'     => 'date_completed',
            ]);
            foreach ($orders as $order) {
                foreach ($order->get_items() as $item) {
                    $pid = (int) $item->get_product_id();
                    $prod = wc_get_product($pid);
                    $custom_orders[] = ["product_id"=>$pid, "product_name" => $prod->get_name];
                }
            }
        }
        $user_info = get_userdata($user_id);

        

        $single_data = array(
            "user_id"=> $user_id,
            "first_name"=> $firstname,
            "middle_name"=> $middlename,
            "last_name"=> $surname,
            "has_certificate" => $has_certificate,
            "certificate_validity" => $certificate_validity,
            "Joined" => $user_info->user_registered,
            "paid_fees" => $paid_fees,
            "member_id" => $member_id,
            "is_transiting" => $is_transiting,
            "orders" => $custom_orders,
        );

        return rest_ensure_response(['data'=>$single_data, 'status'=>'success'], 200);
    }


}
