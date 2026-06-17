<?php

namespace SRC\Controllers;


use WP_REST_REQUEST;
use WP_Error;
use SRC\Utils\Certificate;
use SRC\Models\CISON_Conference_Model_2025;
use SRC\Models\CISON_PreConference_Model_2025;
use SRC\Utils\Money;


define('CISON_CERT_TABLE', 'wprx_cison_certificates');

class CertController
{

    public static function getNextCertNumber()
    {
        return rest_ensure_response([
            'next_cert_number' => cison_get_next_cert_number(),
            'status' => 'success'
        ]);
    }

    public static function addNewCertification(WP_REST_REQUEST $request)
    {
        global $wpdb;

        $body = $request->get_json_params();
        $user_id = isset($body['user_id']) ? sanitize_text_field($body['user_id']) : '';
        $member_id = isset($body['member_id']) ? sanitize_text_field($body['member_id']) : '';

        // Validate input
        if (empty($user_id) && empty($member_id)) {
            return new WP_Error('invalid_id', 'User ID or Member ID is required', ['status' => 400]);
        }

        // Resolve user_id from member_id if needed
        if (empty($user_id) && !empty($member_id)) {
            $table_name = $wpdb->prefix . 'bp_xprofile_data';
            $user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM {$table_name} WHERE field_id = %d AND value = %s LIMIT 1",
                894,
                $member_id
            ));
        }

        if (empty($user_id)) {
            return new WP_Error('not_found', "User not found for Member ID: {$member_id}", ['status' => 404]);
        }

        // Check if a valid certificate already exists
        $cert_table_name = CISON_CERT_TABLE;
        $existing_cert = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$cert_table_name} WHERE user_id = %d LIMIT 1",
            $user_id
        ));

        if (!empty($existing_cert) && file_exists($existing_cert->certificate_path)) {
            return new WP_Error(
                'certificate_exists',
                'Certificate already exists for this user',
                ['status' => 400]
            );
        }

        // Fetch user profile data
        $is_transiting = function_exists('bp_get_profile_field_data')
            ? (bp_get_profile_field_data(['field' => 1595, 'user_id' => $user_id]) === 'Yes')
            : false;

        $member_type = $is_transiting ? 'transiting' : 'inducted';

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

        if (empty($email)) {
            return new WP_Error('invalid_user', 'User email not found', ['status' => 400]);
        }

        // Acquire a named MySQL advisory lock so only one process can
        // read-then-insert the cert_id sequence at a time (timeout: 10s).
        $lock_acquired = $wpdb->get_var("SELECT GET_LOCK('cison_cert_id_lock', 10)");

        if ($lock_acquired != 1) {
            return new WP_Error('lock_timeout', 'Could not acquire certificate ID lock. Please try again.', ['status' => 503]);
        }

        // Build certificate metadata
        $date_now = date('Y-m-d H:i:s');
        $date_issued_unix = strtotime($date_now);
        $secret_token = wp_generate_password(12, false);
        $cutoff_date = $is_transiting ? date('Y-m-d', $date_issued_unix) : null;

        // Derive the next cert sequence number atomically while the lock is held.
        // MAX() on the numeric suffix is reliable regardless of insertion order.
        $max_seq = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(CAST(SUBSTRING_INDEX(cert_id, '-', -1) AS UNSIGNED))
         FROM {$cert_table_name}
         WHERE cert_id LIKE %s",
            CISON_CURRENT_YEAR . '-%'
        ));

        $next_seq = $max_seq ? (int) $max_seq + 1 : 1;

        // Cycle forward until we find a cert_id that does not already exist in the table.
        $max_attempts = 100;
        $attempt = 0;
        do {
            if ($attempt >= $max_attempts) {
                $wpdb->query("SELECT RELEASE_LOCK('cison_cert_id_lock')");
                return new WP_Error('cert_id_exhausted', 'Could not find a free certificate ID after ' . $max_attempts . ' attempts.', ['status' => 500]);
            }

            $cert_id_formatted = CISON_CURRENT_YEAR . '-' . sprintf('%05d', $next_seq);

            $id_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$cert_table_name} WHERE cert_id = %s",
                $cert_id_formatted
            ));

            if ($id_exists) {
                $next_seq++;
            }

            $attempt++;
        } while ($id_exists);

        $cert_path = CISON_CERTIFICATE_DIR . "certificate_{$cert_id_formatted}.pdf";

        // Insert certificate record (still inside the lock window)
        $inserted = $wpdb->insert(
            $cert_table_name,
            [
                'user_id' => $user_id,
                'member_id' => $member_id,
                'cert_id' => $cert_id_formatted,
                'certificate_path' => $cert_path,
                'date_issued' => $date_issued_unix,
                'secret_token' => $secret_token,
                'last_updated' => time(),
                'firstname' => $firstname,
                'middlename' => $middlename,
                'surname' => $surname,
                'email' => $email,
                'member_type' => $member_type,
                'cutoff_date' => $cutoff_date,
            ],
            ['%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        // Always release the lock before returning
        $wpdb->query("SELECT RELEASE_LOCK('cison_cert_id_lock')");

        if (!$inserted) {
            error_log("CISON: Failed to insert certificate for user {$user_id}: " . $wpdb->last_error);
            return new WP_Error('db_error', 'Failed to create certificate record: ' . $wpdb->last_error, ['status' => 500]);
        }

        return rest_ensure_response([
            'user_id' => $user_id,
            'cert_id' => $cert_id_formatted,
            'certificate_path' => $cert_path,
            'status' => 'success',
            'message' => 'Certificate record created successfully',
        ]);
    }

    public static function singleCertificate(WP_REST_REQUEST $request)
    {
        global $wpdb;
        $params = $request->get_params();
        $user_id = isset($params['user_id']) ? sanitize_text_field($params['user_id']) : '';
        $member_id = isset($params['member_id']) ? sanitize_text_field($params['member_id']) : '';

        if (empty($user_id) && empty($member_id)) {
            return new WP_Error('invalid_id', 'User ID or Member ID is required', ['status' => 400]);
        }

        // Get user_id from member_id if needed
        if (empty($user_id) && !empty($member_id)) {
            $table_name = $wpdb->prefix . 'bp_xprofile_data';
            $user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM {$table_name} WHERE field_id = %d AND value = %s LIMIT 1",
                894,
                $member_id
            ));
        }

        if (empty($user_id)) {
            return new WP_Error('not_found', 'User not found', ['status' => 404]);
        }

        // Get certificate
        $cert_table_name = CISON_CERT_TABLE;
        $certificate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$cert_table_name} WHERE user_id = %d LIMIT 1",
            $user_id
        ));

        if (empty($certificate)) {
            return new WP_Error('not_found', 'Certificate not found for this user', ['status' => 404]);
        }

        return rest_ensure_response([
            'data' => $certificate,
            'status' => 'success'
        ], 200);
    }

    public static function add2025Conference(WP_REST_REQUEST $request)
    {
        global $wpdb;
        $body = $request->get_json_params();

        // Validate required fields
        if (empty($body['email'])) {
            return new WP_Error('invalid_data', 'Email is required', ['status' => 400]);
        }
        $email = sanitize_email($body['email']);
        $table_name = $wpdb->prefix . 'cison_conference_2025';

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT email FROM {$table_name} WHERE email = %s LIMIT 1",
                $email
            )
        );

        if (!empty($existing)) {
            return new WP_Error('already_exists', 'Record already exists for this email', ['status' => 400]);
        }


        if (!empty($existing)) {
            return new WP_Error('already_exists', 'Record already exists for this email', ['status' => 400]);
        }

        $filename = $body['cert_name'];
        $file_url = content_url('private/conference/' . $filename);
        // $file_path = WP_CONTENT_DIR . '/private/preconference/' . $filename;

        $cert_id = uniqid('cert-', true);

        $file_path = WP_CONTENT_DIR . '/private/conference/' . $filename;

        $cert_url = rest_url('api/v1/certificate/' . $cert_id);

        $saved = $wpdb->insert(
            $table_name,
            [
                'order_id' => $body['order_id'],
                'member_id' => $body['member_id'],
                'first_name' => $body['first_name'],
                'last_name' => $body['surname'],
                'item_name' => $body['item_name'],
                'item_price' => $body['item_price'],
                'order_total' => $body['order_total'],
                'status' => $body['status'],
                'paid_date' => $body['paid_date'],
                'email' => $body['email'],
                'phone' => $body['phone'],
                'payment_method' => $body['payment_method'],
                'transaction_id' => $body['transaction_id'],
                'order_link' => $body['order_link'],
                'billing_state' => $body['billing_state'],
                'cert_url' => $file_url,
                'last_updated' => time(),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($saved === false) {
            return new WP_Error('save_failed', 'Failed to save pre-conference record', ['status' => 500]);
        }

        return rest_ensure_response([
            'status' => 'success',
            'message' => 'Conference registration saved successfully'
        ]);
    }

    public static function add2025PreConference(WP_REST_REQUEST $request)
    {
        global $wpdb;
        $body = $request->get_json_params();

        // Validate required fields
        if (empty($body['email'])) {
            return new WP_Error('invalid_data', 'Email is required', ['status' => 400]);
        }

        $email = sanitize_email($body['email']);
        $table_name = $wpdb->prefix . 'cison_preconference_2025';

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT email FROM {$table_name} WHERE email = %s LIMIT 1",
                $email
            )
        );

        if (!empty($existing)) {
            return new WP_Error('already_exists', 'Record already exists for this email', ['status' => 400]);
        }

        $filename = $body['cert_name'];
        $file_url = content_url('private/preconference/' . $filename);
        // $file_path = WP_CONTENT_DIR . '/private/preconference/' . $filename;

        $saved = $wpdb->insert(
            $table_name,
            [
                'order_id' => $body['order_id'],
                'member_id' => $body['member_id'],
                'first_name' => $body['first_name'],
                'last_name' => $body['surname'],
                'item_name' => $body['item_name'],
                'item_price' => $body['item_price'],
                'order_total' => $body['order_total'],
                'status' => $body['status'],
                'paid_date' => $body['paid_date'],
                'email' => $body['email'],
                'phone' => $body['phone'],
                'payment_method' => $body['payment_method'],
                'transaction_id' => $body['transaction_id'],
                'order_link' => $body['order_link'],
                'billing_state' => $body['billing_state'],
                'cert_url' => $file_url,
                'last_updated' => time(),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($saved === false) {

            return new WP_Error('save_failed', 'DB Error: ' . $wpdb->last_error, ['status' => 500]);
        }

        return rest_ensure_response([
            'status' => 'success',
            'message' => 'Pre-conference registration saved successfully'
        ]);
    }

    public static function get2025Preconference()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cison_preconference_2025';

        $response = $wpdb->get_results("SELECT id, first_name, last_name, email, cert_url from {$table_name};");

        return rest_ensure_response($response);
    }

    public static function get2025Conference()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cison_conference_2025';

        $response = $wpdb->get_results("SELECT id, first_name, last_name, email, cert_url from {$table_name};");

        return rest_ensure_response($response);
    }
    public static function dropTables()
    {
        global $wpdb;

        $preconf_table = $wpdb->prefix . 'cison_preconference_2025';
        $conf_table = $wpdb->prefix . 'cison_conference_2025';

        $wpdb->query("DROP TABLE IF EXISTS {$preconf_table}");
        $wpdb->query("DROP TABLE IF EXISTS {$conf_table}");

        return rest_ensure_response(['message' => 'Tables dropped successfully']);
    }
    public static function remove_certificate(WP_REST_REQUEST $request)
    {
        global $wpdb;
        $params = $request->get_params();
        $user_id = isset($params['user_id']) ? sanitize_text_field($params['user_id']) : '';
        $member_id = isset($params['member_id']) ? sanitize_text_field($params['member_id']) : '';

        if (empty($user_id) && empty($member_id)) {
            return new WP_Error('invalid_id', 'User ID or Member ID is required', ['status' => 400]);
        }

        // Get user_id from member_id if needed
        if (empty($user_id) && !empty($member_id)) {
            $table_name = $wpdb->prefix . 'bp_xprofile_data';
            $user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM {$table_name} WHERE field_id = %d AND value = %s LIMIT 1",
                894,
                $member_id
            ));
        }

        if (empty($user_id)) {
            return new WP_Error('not_found', 'User not found', ['status' => 404]);
        }

        // Get certificate
        $cert_table_name = CISON_CERT_TABLE;
        $certificate = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$cert_table_name} WHERE user_id = %d LIMIT 1",
            $user_id
        ));

        if (empty($certificate)) {
            return new WP_Error('not_found', "Certificate not found for this user {$user_id} {$member_id}", ['status' => 404]);
        }

        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$cert_table_name} WHERE user_id = %d",
            $user_id
        ));


        return rest_ensure_response([
            'data' => "Successfully removed certificate",
            'status' => 'success'
        ], 200);
    }

    public static function list_certificates(WP_REST_REQUEST $request)
    {
        global $wpdb;
        $cert_table_name = CISON_CERT_TABLE;
        $certificate = $wpdb->get_results(
            "SELECT * FROM {$cert_table_name}",
            ARRAY_A
        );
        return rest_ensure_response([
            'data' => $certificate,
            'status' => 'success'
        ], 200);
    }

    public static function get_qualified_certificates(WP_REST_REQUEST $request)
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

            if (!$is_transiting)
                continue;

            $member_id = function_exists('bp_get_profile_field_data')
                ? bp_get_profile_field_data(['field' => 894, 'user_id' => $userID]) : '';

            $reg_year = $is_transiting
                ? 2023
                : ($member_id ? max(2024, min((int) substr($member_id, 0, 4), 2025)) : 2025);

            $required = cison_get_required_fees($is_transiting, $reg_year);
            $paid = cison_get_paid_fees($userID);
            $unpaid = cison_get_unpaid_fees($required, $paid);
            $profile_type = bp_get_member_type($userID, true);

            if (Money::getArrayCount($paid) === 1) {
                $cert_table_name = CISON_CERT_TABLE;
                $certificate = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$cert_table_name} WHERE user_id = %d LIMIT 1",
                    $userID
                ));

                if (empty($certificate)) {
                    $user_data = DataController::get_userdata($userID);
                    if ($user_data) {
                        $user_data["user_email"] = $user['user_email'];
                        $user_data['profile_type'] = $profile_type;
                    }
                    $toSend[] = $user_data;
                }
            }
        }
        return rest_ensure_response([
            "data" => $toSend,
            "status" => "success"
        ], 200);
    }
}