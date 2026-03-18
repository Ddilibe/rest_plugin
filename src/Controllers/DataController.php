<?php

namespace SRC\Controllers;

use SRC\Config\Config;
use SRC\Utils\Money;
use SRC\Utils\Certificate;

use WP_Error;
use WP_REST_REQUEST;


define('CISON_CURRENT_YEAR', (int) date('Y'));
define('CISON_CERT_TABLE', Config::get('CISON_CERT_TABLE'));

class DataController
{

    public static function get_userdata($user_id)
    {
        $userID = (int) $user_id;

        $is_transiting = function_exists('bp_get_profile_field_data')
            ? (bp_get_profile_field_data(['field' => 1595, 'user_id' => $userID]) === 'Yes')
            : false;
        $member_id = function_exists('bp_get_profile_field_data')
            ? bp_get_profile_field_data(['field' => 894, 'user_id' => $userID])
            : '';
        $phone_number = function_exists('bp_get_profile_field_data') ? bp_get_profile_field_data([
            'field' => 5,
            'user_id' => $userID,
        ]) : '';

        $firstname = function_exists('bp_get_profile_field_data')
            ? bp_get_profile_field_data(['field' => 1, 'user_id' => $userID])
            : '';
        $middlename = function_exists('bp_get_profile_field_data')
            ? bp_get_profile_field_data(['field' => 864, 'user_id' => $userID])
            : '';
        $surname = function_exists('bp_get_profile_field_data')
            ? bp_get_profile_field_data(['field' => 2, 'user_id' => $userID])
            : '';
        return [
            "user_id" => $userID,
            "member_id" => $member_id,
            "is_transiting" => $is_transiting,
            "first_name" => $firstname,
            "middle_name" => $middlename,
            "last_name" => $surname,
            "phone_number" => $phone_number,
        ];

    }
    public static function allUsers()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'users';


        $users = $wpdb->get_results("SELECT * FROM {$table_name}", ARRAY_A);
        $toSend = array();

        foreach ($users as $user) {
            $userID = (int) $user['ID'];

            if (!function_exists('bp_get_profile_field_data')) {
                continue;
            }

            $single_data = array();
            $customID = array(1, 3, 873, 877, 6, 557, 561, 5, 917, 22, 888, 21, 276, 25, 24, 23, 1425, 859, 840, 839, 836, 835, 2, 538, 864, 894, 876, 874, 875, 863, 862, 860, 838, 861, 575, 578, 579, 574, 576, 891, 907, 903, 581, 582, 580, 1433, 1434, 889, 890, 893, 1435, 1436, 1437, 892, 1488, 1489, 1490, 1491, 1492, 1595, 1494, 1495, 1498, 1497, 1496, 908, 1432, 1429, 1430, 1431, 1438, 1439, 1440, 1441, 1442, 1443, 1444, 1445, 1446, 1599, 1597, 1450, 1447, 1448, 1449, 1451, 1452, 1453, 1454, 1609, 1610, 1500, 1501, 1502, 1503, 1504, 1506, 1507, 1508, 1509, 1510, 1455, 1512, 1513, 1514, 1515, 1516, 1518, 1519, 1520, 1521, 1522, 1524, 1525, 1526, 1527, 1528, 1530, 1531, 1532, 1533, 1534, 1547, 1537, 1540, 1543, 1546, 1535, 1536, 1548, 1487, 1562, 1563, 1549, 1552, 1555, 1558, 1561, 1565, 1566, 1567, 1564, 1568, 1569, 1571, 1572, 1570, 1574, 1575, 1573, 1577, 1578, 1576, 1580, 1581, 1582, 1579, 1583, 1584, 1589, 1590, 1585, 1588, 1591, 1538, 1539, 1541, 1542, 1544, 1545, 1493);

            foreach ($customID as $id) {
                $name = bp_get_profile_field_data([
                    'field' => $id,
                    'user_id' => $userID
                ]);
                $single_data[$id] = $name;
            }

            $toSend[] = $single_data;
        }

        return rest_ensure_response([
            "data" => $toSend,
            "status" => "success"
        ], 200);
    }

    public static function users_with_cleared_payments_2025_limit()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'users';


        $users = $wpdb->get_results("SELECT * FROM {$table_name}", ARRAY_A);
        $toSend = array();

        foreach ($users as $user) {
            $userID = (int) $user['ID'];
            $is_transiting = function_exists('bp_get_profile_field_data')
                ? (bp_get_profile_field_data(['field' => 1595, 'user_id' => $userID]) === 'Yes')
                : false;
            $member_id = function_exists('bp_get_profile_field_data')
                ? bp_get_profile_field_data(['field' => 894, 'user_id' => $userID])
                : '';
            $reg_year = $is_transiting
                ? 2023
                : ($member_id ? max(2024, min((int) substr($member_id, 0, 4), CISON_CURRENT_YEAR)) : CISON_CURRENT_YEAR);

            $required = Money::cison_get_required_fees($is_transiting, $reg_year);
            $paid = Money::cison_get_paid_fees($userID);
            $unpaid = Money::cison_get_unpaid_fees($required, $paid);

            if (count($unpaid) === 0) {
                $user_data = DataController::get_userdata($userID);
                if ($user_data) {
                    $user_data["user_email"] = $user['user_email'];
                }
            }
        }
        return rest_ensure_response([
            "data" => $toSend,
            "status" => "success"
        ], 200);

    }
    public static function users_with_partial_payments_2025_limit()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'users';


        $users = $wpdb->get_results("SELECT * FROM {$table_name}", ARRAY_A);
        $toSend = array();

        foreach ($users as $user) {
            $userID = (int) $user['ID'];
            $is_transiting = function_exists('bp_get_profile_field_data')
                ? (bp_get_profile_field_data(['field' => 1595, 'user_id' => $userID]) === 'Yes')
                : false;
            $member_id = function_exists('bp_get_profile_field_data')
                ? bp_get_profile_field_data(['field' => 894, 'user_id' => $userID])
                : '';
            $reg_year = $is_transiting
                ? 2023
                : ($member_id ? max(2024, min((int) substr($member_id, 0, 4), CISON_CURRENT_YEAR)) : CISON_CURRENT_YEAR);

            $required = Money::cison_get_required_fees($is_transiting, $reg_year);
            $paid = Money::cison_get_paid_fees($userID);
            $unpaid = Money::cison_get_unpaid_fees($required, $paid);

            if (count($unpaid) !== 0 && count($paid) >= 1) {
                $user_data = DataController::get_userdata($userID);
                if ($user_data) {
                    $user_data["user_email"] = $user['user_email'];
                }
            }
        }
        return rest_ensure_response([
            "data" => $toSend,
            "status" => "success"
        ], 200);

    }
    public static function users_without_payments_2025_limit()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'users';


        $users = $wpdb->get_results("SELECT * FROM {$table_name}", ARRAY_A);
        $toSend = array();

        foreach ($users as $user) {
            $userID = (int) $user['ID'];
            $is_transiting = function_exists('bp_get_profile_field_data')
                ? (bp_get_profile_field_data(['field' => 1595, 'user_id' => $userID]) === 'Yes')
                : false;
            $member_id = function_exists('bp_get_profile_field_data')
                ? bp_get_profile_field_data(['field' => 894, 'user_id' => $userID])
                : '';
            $reg_year = $is_transiting
                ? 2023
                : ($member_id ? max(2024, min((int) substr($member_id, 0, 4), CISON_CURRENT_YEAR)) : CISON_CURRENT_YEAR);

            $required = Money::cison_get_required_fees($is_transiting, $reg_year);
            $paid = Money::cison_get_paid_fees($userID);
            $unpaid = Money::cison_get_unpaid_fees($required, $paid);

            if (count($required) == count($unpaid)) {
                $user_data = DataController::get_userdata($userID);
                if ($user_data) {
                    $user_data["user_email"] = $user['user_email'];
                }
            }
        }
        return rest_ensure_response([
            "data" => $toSend,
            "status" => "success"
        ], 200);

    }
}