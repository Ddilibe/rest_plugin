<?php

namespace SRC\Controllers;

use SRC\Config\Config;
use SRC\Utils\Money;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class StudentCertificateController
{
    private static function table_name()
    {
        global $wpdb;

        return $wpdb->prefix . 'student_certificates';
    }

    private static function table_exists($table)
    {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
    }

    private static function is_student_member($user_id)
    {
        $user_id = (int) $user_id;

        if ($user_id <= 0) {
            return false;
        }

        $member_type_bp = bp_get_member_type($user_id);
        return $member_type_bp === 'student-member';
    }

    private static function get_member_id($user_id)
    {
        return function_exists('bp_get_profile_field_data')
            ? (string) bp_get_profile_field_data(['field' => 894, 'user_id' => $user_id])
            : '';
    }

    private static function get_student_name($user_id)
    {
        if (!function_exists('bp_get_profile_field_data')) {
            return '';
        }

        $parts = [
            bp_get_profile_field_data(['field' => 1, 'user_id' => $user_id]),
            bp_get_profile_field_data(['field' => 864, 'user_id' => $user_id]),
            bp_get_profile_field_data(['field' => 2, 'user_id' => $user_id]),
        ];

        $parts = array_filter(array_map('trim', $parts), function ($part) {
            return $part !== '';
        });

        return implode(' ', $parts);
    }

    private static function normalize_datetime($value)
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
            return $value;
        }

        $timestamp = strtotime($value);

        return $timestamp ? wp_date('Y-m-d H:i:s', $timestamp) : null;
    }

    public static function list_eligible_students(WP_REST_Request $request)
    {
        global $wpdb;

        $table = self::table_name();
        $existing_user_ids = [];
        $existing_numbers = [];

        if (self::table_exists($table)) {
            $existing_user_ids = array_map('intval', (array) $wpdb->get_col("SELECT user_id FROM {$table}"));
            $existing_numbers = array_map('strval', (array) $wpdb->get_col("SELECT student_number FROM {$table}"));
        }

        $users = get_users(['fields' => 'ID']);
        $eligible = [];

        foreach ($users as $user_id) {
            $user_id = (int) $user_id;

            if (!self::is_student_member($user_id)) {
                continue;
            }

            $member_id = self::get_member_id($user_id);

            if ($member_id === '') {
                continue;
            }

            if (in_array($user_id, $existing_user_ids, true) || in_array($member_id, $existing_numbers, true)) {
                continue;
            }

            $paid = Money::cison_get_paid_fees($user_id);

            if (Money::getArrayCount($paid) !== 1) {
                continue;
            }

            $eligible[] = $member_id;
        }

        return new WP_REST_Response([
            'data' => $eligible,
            'count' => count($eligible),
            'status' => 'success',
        ], 200);
    }

    public static function create_certificate(WP_REST_Request $request)
    {
        global $wpdb;

        $table = self::table_name();

        if (!self::table_exists($table)) {
            return new WP_Error('table_not_found', 'The student certificates table does not exist.', ['status' => 500]);
        }

        $user_id = absint($request->get_param('user_id'));
        $student_number = sanitize_text_field((string) $request->get_param('student_number'));

        if ($student_number === '') {
            $student_number = sanitize_text_field((string) $request->get_param('member_id'));
        }

        if (!$user_id && $student_number !== '') {
            $profile_table = $wpdb->prefix . 'bp_xprofile_data';
            $user_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM {$profile_table} WHERE field_id = %d AND value = %s LIMIT 1",
                894,
                $student_number
            ));
        }

        if (!$user_id) {
            return new WP_Error('invalid_user', 'A valid user_id or student_number is required.', ['status' => 400]);
        }

        if (!get_userdata($user_id)) {
            return new WP_Error('invalid_user', 'The specified user does not exist.', ['status' => 404]);
        }

        if (!self::is_student_member($user_id)) {
            return new WP_Error('not_student_member', 'Certificates can only be created for student members.', ['status' => 400]);
        }

        if ($student_number === '') {
            $student_number = self::get_member_id($user_id);
        }

        if ($student_number === '') {
            return new WP_Error('missing_student_number', 'The student number could not be resolved.', ['status' => 400]);
        }

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE user_id = %d OR student_number = %s LIMIT 1",
            $user_id,
            $student_number
        ));

        if ($existing) {
            return new WP_Error('certificate_exists', 'A certificate already exists for this student.', ['status' => 409]);
        }

        $cert_key = '';

        for ($i = 0; $i < 5; $i++) {
            $candidate = wp_generate_password(32, false, false);
            $key_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE cert_key = %s",
                $candidate
            ));

            if (!$key_exists) {
                $cert_key = $candidate;
                break;
            }
        }

        if ($cert_key === '') {
            return new WP_Error('cert_key_error', 'Could not generate a unique certificate key.', ['status' => 500]);
        }

        $cert_hmac = hash_hmac('sha256', $cert_key, Config::get('SSSECURE_AUTH_KEY', ''));

        $student_name = sanitize_text_field((string) $request->get_param('student_name'));

        if ($student_name === '') {
            $student_name = self::get_student_name($user_id);
        }

        $user = get_userdata($user_id);
        $student_email = sanitize_email((string) $request->get_param('student_email'));

        if ($student_email === '') {
            $student_email = $user ? $user->user_email : '';
        }

        $programme = sanitize_text_field((string) $request->get_param('programme'));
        $certificate_title = sanitize_text_field((string) $request->get_param('certificate_title'));

        if ($certificate_title === '') {
            $certificate_title = 'Student Membership Certificate';
        }

        $cert_type = sanitize_text_field((string) $request->get_param('cert_type'));

        if ($cert_type === '') {
            $cert_type = 'student';
        }

        $file_url = esc_url_raw((string) $request->get_param('file_url'));
        $date_expiry = self::normalize_datetime($request->get_param('date_expiry'));

        $data = [
            'user_id' => $user_id,
            'student_number' => $student_number,
            'student_name' => $student_name,
            'student_email' => $student_email,
            'programme' => $programme,
            'certificate_title' => $certificate_title,
            'cert_type' => $cert_type,
            'cert_key' => $cert_key,
            'cert_hmac' => $cert_hmac,
            'status' => 'issued',
            'date_issued' => current_time('mysql'),
            'date_expiry' => $date_expiry,
            'file_url' => $file_url !== '' ? $file_url : null,
        ];

        $inserted = $wpdb->insert(
            $table,
            $data,
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if (!$inserted) {
            return new WP_Error('db_error', 'Failed to create certificate: ' . $wpdb->last_error, ['status' => 500]);
        }

        $data['id'] = (int) $wpdb->insert_id;

        return new WP_REST_Response([
            'data' => $data,
            'status' => 'success',
        ], 201);
    }

    public static function delete_certificate(WP_REST_Request $request)
    {
        global $wpdb;

        $table = self::table_name();

        if (!self::table_exists($table)) {
            return new WP_Error('table_not_found', 'The student certificates table does not exist.', ['status' => 500]);
        }

        $id = absint($request->get_param('id'));
        $user_id = absint($request->get_param('user_id'));
        $student_number = sanitize_text_field((string) $request->get_param('student_number'));

        if ($student_number === '') {
            $student_number = sanitize_text_field((string) $request->get_param('member_id'));
        }

        if (!$id && !$user_id && $student_number === '') {
            return new WP_Error('invalid_request', 'An id, user_id, or student_number is required.', ['status' => 400]);
        }

        if ($id) {
            $deleted = $wpdb->delete($table, ['id' => $id], ['%d']);
        } elseif ($user_id) {
            $deleted = $wpdb->delete($table, ['user_id' => $user_id], ['%d']);
        } else {
            $deleted = $wpdb->delete($table, ['student_number' => $student_number], ['%s']);
        }

        if (!$deleted) {
            return new WP_Error('not_found', 'Certificate not found or already deleted.', ['status' => 404]);
        }

        return new WP_REST_Response([
            'data' => [
                'deleted' => (int) $deleted,
                'id' => $id ?: null,
                'user_id' => $user_id ?: null,
                'student_number' => $student_number !== '' ? $student_number : null,
            ],
            'status' => 'success',
        ], 200);
    }
}
