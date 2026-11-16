<?php

namespace SRC\Controllers;

use SRC\Config\Config;
use SRC\Utils\money;
use SRC\Utils\Certificate;

use WP_Error;
use WP_REST_REQUEST;
use WP_User_Query;
use WP_REST_Response;


define('CISON_CURRENT_YEAR', (int) date('Y'));
define('CISON_CERT_TABLE', Config::get('CISON_CERT_TABLE', ''));

class UserController
{
    public static function getAllUsers()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'users';
        $all_data = array();

        $users = $wpdb->get_results("SELECT * FROM {$table_name}", ARRAY_A);

        foreach ($users as $value) {
            $user_id = (int) $value["ID"];
            if (!function_exists('bp_get_profile_field_data')) {
                continue;
            }
            $member_id = bp_get_profile_field_data([
                'field' => 894,
                'user_id' => $user_id,
            ]) ?: '';
            $phone_number = bp_get_profile_field_data([
                'field' => 5,
                'user_id' => $user_id,
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
                "user_id" => $user_id,
                "first_name" => $firstname,
                "middle_name" => $middlename,
                "last_name" => $surname,
                "user_login" => $value["user_login"],
                "user_email" => $value["user_email"],
                "joined_date" => $value["user_registered"],
                "phone_number" => $phone_number,
                "display_name" => $value["display_name"],
                "member_id" => $member_id,
            );
            $all_data[] = $single_data;
        }

        return rest_ensure_response($all_data);
    }

    public static function getTransitingMembers()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bp_xprofile_data';

        $users = $wpdb->get_results("SELECT * FROM {$table_name}", ARRAY_A);

        return rest_ensure_response($users);
    }

    public static function getGroupMembers()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'bp_groups_members';

        $members = $wpdb->get_results("SELECT * FROM {$table_name}", ARRAY_A);

        return rest_ensure_response($members);
    }

    public static function getMemebersThatAreTransitingThatHavePaid()
    {
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
                'field' => 1595,
                'user_id' => $user_id,
            ]) === 'Yes';

            $has_certificate = array_filter($certificates, function ($certificate) {
                return $certificate["user_id"] === $user_id;
            });

            $member_id = bp_get_profile_field_data([
                'field' => 894,
                'user_id' => $user_id,
            ]) ?: '';

            if (!$is_transiting) {
                continue;
            }

            $phone_number = bp_get_profile_field_data([
                'field' => 5,
                'user_id' => $user_id,
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
            $paid_fees = Money::cison_get_paid_fees($user_id);

            $single_data = array(
                "user_id" => $user_id,
                "first_name" => $firstname,
                "middle_name" => $middlename,
                "last_name" => $surname,
                "user_login" => $value["user_login"],
                "user_email" => $value["user_email"],
                "joined_date" => $value["user_registered"],
                "phone_number" => $phone_number,
                "display_name" => $value["display_name"],
                "member_id" => $member_id,
                "certificate_validity" => cison_preview_user_eligibility($user_id),
                "has_certificate" => $has_certificate,
                "paid_fees" => $paid_fees,
                "is_transiting" => $is_transiting ? true : false,
            );

            $all_data[] = $single_data;
        }

        $response = ["data" => $all_data, "status" => "success"];

        return rest_ensure_response($response, 200);
    }

    public static function getMembersThatHaveCertificate()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'users';

        $all_data = array();

        $all_users = $wpdb->get_results("SELECT * FROM {$table_name}", ARRAY_A);
        $certificates = $wpdb->get_results("SELECT * FROM wprx_cison_certificates", ARRAY_A);

        foreach ($certificates as $value) {
            $user_id = (int) $value["user_id"];

            $firstname = function_exists('bp_get_profile_field_data')
                ? bp_get_profile_field_data(['field' => 1, 'user_id' => $user_id])
                : '';
            $middlename = function_exists('bp_get_profile_field_data')
                ? bp_get_profile_field_data(['field' => 864, 'user_id' => $user_id])
                : '';
            $surname = function_exists('bp_get_profile_field_data')
                ? bp_get_profile_field_data(['field' => 2, 'user_id' => $user_id])
                : '';
            $phone_number = function_exists('bp_get_profile_field_data') ? bp_get_profile_field_data([
                'field' => 5,
                'user_id' => $user_id,
            ]) : '';

            $single_data = array(
                "user_id" => $user_id,
                "first_name" => $firstname,
                "middle_name" => $middlename,
                "last_name" => $surname,
                "phone_number" => $phone_number,
                "has_certificate" => $has_certificate,
                "certificate_validity" => $certificate_validity
            );
            $all_data[] = $single_data;
        }

        return rest_ensure_response(["data" => $all_data, "status" => "success"], 200);
    }

    public static function getMembersThatDoNotHaveCertificate()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'users';

        $all_data = array();

        $all_users = $wpdb->get_results("SELECT * FROM {$table_name}", ARRAY_A);
        $certificates = $wpdb->get_results("SELECT * FROM {$CISON_CERT_TABLE}", ARRAY_A);

        foreach ($all_users as $value) {
            $user_id = (int) $value["ID"];

            $certificate_validity = cison_preview_user_eligibility($user_id);

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
                'field' => 1595,
                'user_id' => $user_id,
            ]) === 'Yes';

            $paid_fees = cison_get_paid_fees($user_id);

            $single_data = array(
                "user_id" => $user_id,
                "first_name" => $firstname,
                "middle_name" => $middlename,
                "last_name" => $surname,
                "has_certificate" => $has_certificate,
                "certificate_validity" => $certificate_validity,
                "paid_fees" => $paid_fees,
                "is_transiting" => $is_transiting,
            );
            $all_data[] = $single_data;
        }

        return rest_ensure_response(["data" => $all_data, "status" => "success"], 200);
    }


    public static function allCertificate()
    {
        global $wpdb;
        $certificates = $wpdb->get_results("SELECT * FROM wprx_cison_certificates", ARRAY_A);
        return rest_ensure_response(["data" => $certificates, "status" => "success"]);
    }

    public static function getUserWithUserId(WP_REST_REQUEST $request)
    {
        global $wpdb;

        $body = $request->get_json_params();

        $user_id = isset($body['user_id']) ? sanitize_text_field($body['user_id']) : '';
        if (!$user_id) {
            return new WP_Error("invalid_id", "user ID is required", ['status' => 400]);
        }

        $certificate_validity = cison_preview_user_eligibility($user_id);

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
            'field' => 1595,
            'user_id' => $user_id,
        ]) === 'Yes';

        $member_id = bp_get_profile_field_data([
            'field' => 894,
            'user_id' => $user_id,
        ]) ?: '';

        $paid_fees = cison_get_paid_fees($user_id);

        $custom_orders = array();

        if (function_exists('wc_get_orders')) {
            $orders = wc_get_orders([
                'customer_id' => $user_id,
                'status' => ['completed', 'processing'],
                'limit' => -1,
                'return' => 'objects',
                'orderby' => 'date_completed',
            ]);
            foreach ($orders as $order) {
                foreach ($order->get_items() as $item) {
                    $pid = (int) $item->get_product_id();
                    $prod = wc_get_product($pid);
                    $custom_orders[] = ["product_id" => $pid, "product_name" => $prod->get_name];
                }
            }
        }
        $user_info = get_userdata($user_id);

        $single_data = array(
            "user_id" => $user_id,
            "first_name" => $firstname,
            "middle_name" => $middlename,
            "last_name" => $surname,
            "has_certificate" => $has_certificate,
            "certificate_validity" => $certificate_validity,
            "Joined" => $user_info->user_registered,
            "paid_fees" => $paid_fees,
            "member_id" => $member_id,
            "is_transiting" => $is_transiting,
            "orders" => $custom_orders,
        );

        return rest_ensure_response(['data' => $single_data, 'status' => 'success'], 200);
    }

    public static function getUserProfile(WP_REST_REQUEST $request)
    {
        $body = $request->get_json_params();
        $user_id = isset($body['user_id']) ? $body['user_id'] : $request->get_param('user_id');
        $user_id = is_scalar($user_id) ? absint($user_id) : 0;

        if (!$user_id) {
            return new WP_Error("invalid_id", "user ID is required", ['status' => 400]);
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_Error("not_found", "No user found with that ID", ['status' => 404]);
        }

        $professional_qualification = self::getQualificationValue($user_id, 'professional', 0);
        if ($professional_qualification === '') {
            $professional_qualification = self::getProfileValue($user_id, 1614);
        }
        if ($professional_qualification === '') {
            $professional_qualification = self::getProfileValue($user_id, 1425);
        }

        $profile = array(
            "user_id" => $user_id,
            "title" => self::getProfileValue($user_id, 538),
            "first_name" => self::getProfileValue($user_id, 1),
            "last_name" => self::getProfileValue($user_id, 2),
            "middle_name" => self::getProfileValue($user_id, 864),
            "email" => $user->user_email,
            "phone_number" => self::getProfileValue($user_id, 5),
            "address" => array(
                "address_1" => self::getProfileValue($user_id, 22),
                "address_2" => self::getProfileValue($user_id, 23),
                "city" => self::getProfileValue($user_id, 24),
                "state" => self::getProfileValue($user_id, 276),
                "postcode" => self::getProfileValue($user_id, 25),
                "country" => self::getProfileValue($user_id, 917),
            ),
            "professional_qualification" => $professional_qualification,
            "academic_qualification" => self::getQualificationValue($user_id, 'academic', 859),
            "gender" => self::normalizeGender(self::getProfileValue($user_id, 557)),
            "cison_number" => self::getProfileValue($user_id, 894),
        );

        return rest_ensure_response(['data' => $profile, 'status' => 'success'], 200);
    }

    public static function getUserIdsByBpType(WP_REST_REQUEST $request)
    {
        $body = $request->get_json_params();
        $bp_type = isset($body['bp_type']) ? $body['bp_type'] : $request->get_param('bp_type');
        $bp_type = is_scalar($bp_type) ? sanitize_key($bp_type) : '';

        if ($bp_type === '') {
            return new WP_Error("invalid_bp_type", "bp_type is required", ['status' => 400]);
        }

        $term = get_term_by('slug', $bp_type, 'bp_member_type');
        if (!$term) {
            return new WP_Error("not_found", "No BP member type found with that slug", ['status' => 404]);
        }

        $user_ids = get_objects_in_term((int) $term->term_id, 'bp_member_type');
        $user_ids = array_values(array_unique(array_map('intval', (array) $user_ids)));
        sort($user_ids);

        return rest_ensure_response([
            'data' => $user_ids,
            'bp_type' => $bp_type,
            'status' => 'success',
            'count' => count($user_ids),
        ], 200);
    }

    private static function getProfileValue($user_id, $field_id)
    {
        if (!function_exists('bp_get_profile_field_data')) {
            return '';
        }

        return self::normalizeProfileValue(bp_get_profile_field_data([
            'field' => $field_id,
            'user_id' => $user_id,
        ]));
    }

    private static function normalizeProfileValue($value)
    {
        if (is_array($value)) {
            $value = array_filter(array_map(function ($item) {
                return is_scalar($item) ? trim((string) $item) : '';
            }, $value), function ($item) {
                return $item !== '';
            });

            return implode(', ', $value);
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function getQualificationValue($user_id, $type, $fallback_field_id)
    {
        if (function_exists('bp_xprofile_get_groups')) {
            $groups = bp_xprofile_get_groups([
                'user_id' => $user_id,
                'fetch_fields' => true,
                'populate_fields' => true,
            ]);

            foreach ((array) $groups as $group) {
                $group_name = isset($group->name) ? strtolower($group->name) : '';

                foreach ((array) $group->fields as $field) {
                    $field_name = isset($field->name) ? strtolower($field->name) : '';
                    if (strpos($field_name, 'qualification') === false) {
                        continue;
                    }

                    $type_matches = strpos($field_name, $type) !== false
                        || strpos($group_name, $type) !== false
                        || ($type === 'academic' && strpos($group_name, 'education') !== false);

                    if (!$type_matches || !isset($field->id)) {
                        continue;
                    }

                    $value = self::getProfileValue($user_id, $field->id);
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return $fallback_field_id ? self::getProfileValue($user_id, $fallback_field_id) : '';
    }

    private static function normalizeGender($value)
    {
        $value = strtolower(trim($value));

        if ($value === 'his_male' || $value === 'male') {
            return 'Male';
        }

        if ($value === 'her_female' || $value === 'female') {
            return 'Female';
        }

        return $value;
    }

    public static function getUserIDWithMemberId(WP_REST_REQUEST $request)
    {
        global $wpdb;

        $body = $request->get_json_params();
        $member_id = isset($body['member_id']) ? sanitize_text_field($body['member_id']) : '';

        if (empty($member_id)) {
            return new WP_Error("invalid_id", "Member ID is required", ['status' => 400]);
        }

        $table_name = $wpdb->prefix . 'bp_xprofile_data';
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$table_name} WHERE field_id = %d AND value = %s LIMIT 1",
            894,
            $member_id
        ));

        if ($user_id) {
            return rest_ensure_response([
                'user_id' => (int) $user_id,
                'status' => 'success'
            ], 200);
        }

        return new WP_Error("not_found", "No user found with that Member ID", ['status' => 404]);
    }
    public static function getValidUsers()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'users';
        $cert_table = CISON_CERT_TABLE;

        $all_data = array();

        // Fix: Added error handling for query
        $members = $wpdb->get_results(
            "SELECT u.*, c.* 
            FROM {$table_name} u 
            INNER JOIN {$cert_table} c ON u.ID = c.user_id",
            ARRAY_A  // Return as associative array
        );

        if ($wpdb->last_error) {
            error_log("CISON: Database error in getValidUsers: " . $wpdb->last_error);
            return new WP_Error('db_error', 'Database query failed', ['status' => 500]);
        }

        foreach ($members as $value) {
            $user_id = (int) $value["ID"];

            if (!function_exists('bp_get_profile_field_data')) {
                continue;
            }

            $is_transiting = bp_get_profile_field_data([
                'field' => 1595,
                'user_id' => $user_id,
            ]) === 'Yes';

            $member_id = bp_get_profile_field_data([
                'field' => 894,
                'user_id' => $user_id,
            ]) ?: '';

            $phone_number = bp_get_profile_field_data([
                'field' => 5,
                'user_id' => $user_id,
            ]) ?: '';

            $firstname = bp_get_profile_field_data([
                'field' => 1,
                'user_id' => $user_id
            ]) ?: '';

            $middlename = bp_get_profile_field_data([
                'field' => 864,
                'user_id' => $user_id
            ]) ?: '';

            $surname = bp_get_profile_field_data([
                'field' => 2,
                'user_id' => $user_id
            ]) ?: '';

            $single_data = array(
                "user_id" => $user_id,
                "first_name" => $firstname,
                "middle_name" => $middlename,
                "last_name" => $surname,
                "user_login" => $value["user_login"],
                "user_email" => $value["user_email"],
                "joined_date" => $value["user_registered"],
                "phone_number" => $phone_number,
                "display_name" => $value["display_name"],
                "member_id" => $member_id,
                "is_transiting" => $is_transiting,
            );

            $all_data[] = $single_data;
        }

        return rest_ensure_response([
            "data" => $all_data,
            "status" => "success",
            "count" => count($all_data)
        ], 200);
    }

    public static function getInvalidUsers()
    {  // Fixed typo: "Inalid" -> "Invalid"
        global $wpdb;
        $table_name = $wpdb->prefix . 'users';
        $cert_table = CISON_CERT_TABLE;

        $all_data = array();

        // Fix: Improved query formatting and added error handling
        $members = $wpdb->get_results(
            "SELECT u.*, c.user_id as cert_user_id 
            FROM {$table_name} u 
            LEFT JOIN {$cert_table} c ON u.ID = c.user_id 
            WHERE c.user_id IS NULL",
            ARRAY_A  // Return as associative array
        );

        if ($wpdb->last_error) {
            error_log("CISON: Database error in getInvalidUsers: " . $wpdb->last_error);
            return new WP_Error('db_error', 'Database query failed', ['status' => 500]);
        }

        foreach ($members as $value) {
            $user_id = (int) $value["ID"];

            if (!function_exists('bp_get_profile_field_data')) {
                continue;
            }

            $is_transiting = bp_get_profile_field_data([
                'field' => 1595,
                'user_id' => $user_id,
            ]) === 'Yes';

            $member_id = bp_get_profile_field_data([
                'field' => 894,
                'user_id' => $user_id,
            ]) ?: '';

            $phone_number = bp_get_profile_field_data([
                'field' => 5,
                'user_id' => $user_id,
            ]) ?: '';

            $firstname = bp_get_profile_field_data([
                'field' => 1,
                'user_id' => $user_id
            ]) ?: '';

            $middlename = bp_get_profile_field_data([
                'field' => 864,
                'user_id' => $user_id
            ]) ?: '';

            $surname = bp_get_profile_field_data([
                'field' => 2,
                'user_id' => $user_id
            ]) ?: '';

            $single_data = array(
                "user_id" => $user_id,
                "first_name" => $firstname,
                "middle_name" => $middlename,
                "last_name" => $surname,
                "user_login" => $value["user_login"],
                "user_email" => $value["user_email"],
                "joined_date" => $value["user_registered"],
                "phone_number" => $phone_number,
                "display_name" => $value["display_name"],
                "member_id" => $member_id,
                "is_transiting" => $is_transiting,
            );

            $all_data[] = $single_data;
        }

        return rest_ensure_response([
            "data" => $all_data,
            "status" => "success",
            "count" => count($all_data)
        ], 200);
    }

    static function get_registered_unsigned_users()
    {
        $users_with_type = get_objects_in_term(
            get_terms(
                array('taxonomy' => 'bp_member_type', 'fields' => 'ids', 'hide_empty' => false)
            ),
            'bp_member_type',
        );

        $user_args = array(
            'fields' => array('ID', 'user_login', 'user_email', 'display_name'),
            'exclude' => !empty($users_with_type) ? $users_with_type : array(),
            'number' => -1
        );

        $user_query = new WP_User_Query($user_args);
        $unassigned_users = $user_query->get_results();

        if (empty($unassigned_users)) {
            return new WP_REST_Response(array('message' => 'All users have a profile type assigned.'), 200);
        }

        $response_data = array();
        foreach ($unassigned_users as $user) {
            $userID = (int) $user->ID;
            $member_id = function_exists('bp_get_profile_field_data')
                ? bp_get_profile_field_data(['field' => 894, 'user_id' => $userID]) : '';
            $response_data[] = array(
                'id' => (int) $user->ID,
                'username' => $user->user_login,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'member_id' => $member_id,
            );
        }

        return new WP_REST_Response($response_data, 200);
    }

    static function handle_statistician_upgrade_endpoint(WP_REST_Request $request)
    {
        $user_id = absint($request->get_param('user_id'));

        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_Error('invalid_user', 'The specified user does not exist.', array('status' => 404));
        }

        if ($user_id === 1) {
            return new WP_Error('protected_user', 'Action denied: Cannot downgrade the primary administrator.', array('status' => 403));
        }

        $bb_type = 'registered-statistician';
        // $wp_role = 'registered_statistician';

        if (function_exists('bp_set_member_type')) {
            bp_set_member_type($user_id, $bb_type);
            update_user_meta($user_id, 'bp_profile_type', $bb_type);
        } else {
            error_log('Endpoint Warning: BuddyBoss component not active during user upgrade.');
        }


        clean_user_cache($user_id);

        return new WP_REST_Response(array(
            'success' => true,
            'message' => "User ID {$user_id} successfully converted to registered statistician.",
        ), 200);
    }

    static function handle_fellow_upgrade_endpoint(WP_REST_Request $request)
    {
        $user_id = absint($request->get_param('user_id'));

        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_Error('invalid_user', 'The specified user does not exist.', array('status' => 404));
        }

        if ($user_id === 1) {
            return new WP_Error('protected_user', 'Action denied: Cannot downgrade the primary administrator.', array('status' => 403));
        }

        $bb_type = 'fellow';

        if (function_exists('bp_set_member_type')) {
            bp_set_member_type($user_id, $bb_type);
            update_user_meta($user_id, 'bp_profile_type', $bb_type);
        } else {
            error_log('Endpoint Warning: BuddyBoss component not active during user upgrade.');
        }

        clean_user_cache($user_id);

        return new WP_REST_Response(array(
            'success' => true,
            'message' => "User ID {$user_id} successfully converted to fellow.",
        ), 200);
    }
}
