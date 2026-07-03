<?php

namespace SRC\Controllers;

use WP_REST_REQUEST;
use WP_Error;

class LearnController
{
    static function get_user_details_from_memberid(WP_REST_Request $request)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bp_xprofile_data';
        $body = $request->get_json_params();

        $member_id = isset($body['member_id']) ? sanitize_text_field($body['member_id']) : '';

        if (empty($member_id)) {
            return new WP_Error("MemberID is not Valid");
        }

        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$table_name} WHERE field_id = %d AND value = %s LIMIT 1",
            894,
            $member_id
        ));

        if (empty($user_id)) {
            return new WP_Error('not_found', "User not found for Member ID: {$member_id}", ['status' => 404]);
        }

        $firstname = function_exists('bp_get_profile_field_data')
            ? (bp_get_profile_field_data(['field' => 1, 'user_id' => $user_id]) ?: '')
            : '';

        $middlename = function_exists('bp_get_profile_field_data')
            ? (bp_get_profile_field_data(['field' => 864, 'user_id' => $user_id]) ?: '')
            : '';

        $surname = function_exists('bp_get_profile_field_data')
            ? (bp_get_profile_field_data(['field' => 2, 'user_id' => $user_id]) ?: '')
            : '';

        $user_data = get_userdata($user_id);
        $email = $user_data ? $user_data->user_email : '';

        return rest_ensure_response([
            'user_id' => $user_id,
            'first_name' => $firstname,
            'last_name' => $surname,
            'middle_name' => $middlename,
            'member_id' => $member_id,
            'email' => $email,
            'username' => $user_data->user_login,
        ]);
    }
}