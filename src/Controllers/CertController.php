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

        if (!is_array($body)) {
            $body = [];
        }

        // Validate required fields
        if (empty($body['email'])) {
            return new WP_Error('invalid_data', 'Email is required', ['status' => 400]);
        }

        // A registration without a name is not a registration. Enforced in the
        // handler as well as in the route schema so a direct call cannot write
        // one.
        $names = self::getRequiredNames($body);

        if (is_wp_error($names)) {
            return $names;
        }

        [$first_name, $surname] = $names;

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

        $filename = '';

        if (isset($body['cert_name']) && $body['cert_name'] !== '') {
            $name_check = self::validateCertificateFileName($body['cert_name']);

            if (is_wp_error($name_check)) {
                return new WP_Error('invalid_data', $name_check->get_error_message(), ['status' => 400]);
            }

            $filename = self::sanitizeCertificateFileName($body['cert_name']);
        }

        $file_url = $filename !== '' ? content_url('private/conference/' . $filename) : '';
        // $file_path = WP_CONTENT_DIR . '/private/preconference/' . $filename;

        $cert_id = uniqid('cert-', true);

        $file_path = WP_CONTENT_DIR . '/private/conference/' . $filename;

        $cert_url = rest_url('api/v1/certificate/' . $cert_id);

        // Every field is guarded: the route schema advertises these as optional,
        // so an omitted field must not raise "Undefined array key" here.
        $saved = $wpdb->insert(
            $table_name,
            [
                'order_id' => isset($body['order_id']) ? absint($body['order_id']) : 0,
                'member_id' => isset($body['member_id']) ? sanitize_text_field($body['member_id']) : '',
                'first_name' => $first_name,
                'last_name' => $surname,
                'item_name' => isset($body['item_name']) ? sanitize_text_field($body['item_name']) : '',
                'item_price' => isset($body['item_price']) ? (float) $body['item_price'] : 0.00,
                'order_total' => isset($body['order_total']) ? (float) $body['order_total'] : 0.00,
                'status' => isset($body['status']) ? sanitize_text_field($body['status']) : '',
                'paid_date' => self::toMySqlDateTime(isset($body['paid_date']) ? $body['paid_date'] : ''),
                'email' => $email,
                'phone' => isset($body['phone']) ? sanitize_text_field($body['phone']) : '',
                'payment_method' => isset($body['payment_method']) ? sanitize_text_field($body['payment_method']) : '',
                'transaction_id' => isset($body['transaction_id']) ? sanitize_text_field($body['transaction_id']) : '',
                'order_link' => isset($body['order_link']) ? esc_url_raw($body['order_link']) : '',
                'billing_state' => isset($body['billing_state']) ? sanitize_text_field($body['billing_state']) : '',
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

    /**
     * Body schema shared by POST /cert/add-2025-preconference and
     * POST /cert/add-2025-conference.
     *
     * The two endpoints record the same payload into structurally identical
     * tables, so they share one schema. That is deliberate: when each route
     * carried its own (or none), the endpoints silently drifted and the
     * conference one was left accepting nameless, unsanitised registrants.
     *
     * Returned as a plain map keyed by parameter name. register_rest_route()
     * only applies a schema in that form: WP_REST_Request::has_valid_params()
     * iterates the map as $key => $arg, so a [Class, 'method'] callable is
     * silently ignored and nothing gets validated.
     *
     * 'email', 'first_name' and 'surname' are required. Every other field is
     * optional and defaults to a safe empty value, which keeps the request
     * contract as narrow as the data it records while guaranteeing no field
     * reaches the database uncast.
     */
    public static function get2025RegistrationArgs()
    {
        $text = [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ];

        // The names are mandatory, so they get a sanitiser that also rejects a
        // value which is blank once trimmed. 'required' alone is not enough:
        // has_valid_params() runs before sanitize_params(), so "   " satisfies
        // required and only sanitises down to '' afterwards.
        $name = [
            'type' => 'string',
            'required' => true,
            'sanitize_callback' => [self::class, 'sanitizeRequiredText'],
        ];

        return [
            'email' => [
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_email',
            ],
            'order_id' => [
                'type' => 'integer',
            ],
            'cert_name' => [
                'type' => 'string',
                'sanitize_callback' => [self::class, 'sanitizeCertificateFileName'],
                'validate_callback' => [self::class, 'validateCertificateFileName'],
            ],
            'item_price' => [
                'type' => 'number',
            ],
            'order_total' => [
                'type' => 'number',
            ],
            'order_link' => [
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
            ],
            'paid_date' => [
                'type' => 'string',
                'validate_callback' => [self::class, 'validateDateTimeParam'],
            ],
            'member_id' => $text,
            'first_name' => $name,
            'surname' => $name,
            'item_name' => $text,
            'status' => $text,
            'phone' => $text,
            'payment_method' => $text,
            'transaction_id' => $text,
            'billing_state' => $text,
        ];
    }

    /**
     * Sanitise a mandatory free-text field, rejecting a value that trims to empty.
     *
     * Returning a WP_Error from a sanitize_callback is supported: WP_REST_Request
     * collects it into the 400 rest_invalid_param response for that parameter.
     */
    public static function sanitizeRequiredText($value, $request = null, $key = null)
    {
        $clean = sanitize_text_field($value);

        if ($clean === '') {
            return new WP_Error(
                'rest_invalid_param',
                sprintf('%s is required and must not be blank.', $key),
                ['status' => 400]
            );
        }

        return $clean;
    }

    /**
     * Sanitise the mandatory name fields.
     *
     * Enforced in the handlers as well as in the route schema so a direct call
     * cannot write a nameless registrant.
     *
     * @param array $body Decoded JSON body.
     * @return array|WP_Error `[$first_name, $surname]`, or the first failure.
     */
    public static function getRequiredNames($body)
    {
        $first_name = isset($body['first_name']) ? sanitize_text_field($body['first_name']) : '';
        $surname = isset($body['surname']) ? sanitize_text_field($body['surname']) : '';

        foreach (['first_name' => $first_name, 'surname' => $surname] as $field => $value) {
            if ($value === '') {
                // The param name, not a prose label: this error code carries no
                // 'params' map, so the message is the only place the caller can
                // learn which field was rejected.
                return new WP_Error('invalid_data', $field . ' is required', ['status' => 400]);
            }
        }

        return [$first_name, $surname];
    }

    /**
     * Reduce a caller-supplied certificate name to a bare file name.
     *
     * sanitize_file_name() strips directory separators and every character
     * outside [A-Za-z0-9._-], so path traversal cannot survive.
     */
    public static function sanitizeCertificateFileName($value)
    {
        return sanitize_file_name((string) $value);
    }

    /**
     * Reject anything that is not a bare file name.
     *
     * The raw value is checked, not the sanitised one: sanitize_file_name()
     * would silently turn "../../evil.pdf" into "evil.pdf", hiding the caller's
     * bug and returning a cert_url for a different file than the one asked
     * for. A name, not a path, is the contract.
     */
    public static function validateCertificateFileName($value, $request = null, $key = null)
    {
        $raw = (string) $value;

        if (strpos($raw, '..') !== false) {
            return new WP_Error('rest_invalid_param', 'cert_name must not contain path traversal sequences.', ['status' => 400]);
        }

        if (strpbrk($raw, '/\\') !== false) {
            return new WP_Error('rest_invalid_param', 'cert_name must be a file name, not a path.', ['status' => 400]);
        }

        if (self::sanitizeCertificateFileName($raw) === '') {
            return new WP_Error('rest_invalid_param', 'cert_name must contain a file name.', ['status' => 400]);
        }

        return true;
    }

    public static function validateDateTimeParam($value, $request = null, $key = null)
    {
        if ($value === null || trim((string) $value) === '') {
            return true;
        }

        if (self::toMySqlDateTime($value) !== null) {
            return true;
        }

        return new WP_Error('rest_invalid_param', 'Could not parse value as a date/time.', ['status' => 400]);
    }

    /**
     * Normalise a caller-supplied date into `Y-m-d H:i:s` for a DATETIME column.
     *
     * Values already in MySQL format pass through untouched so existing
     * callers' timestamps are not shifted by a timezone conversion. Returns
     * null when the value is empty or cannot be parsed.
     */
    public static function toMySqlDateTime($value)
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

    public static function add2025PreConference(WP_REST_Request $request)
    {
        global $wpdb;

        $body = $request->get_json_params();

        if (!is_array($body)) {
            $body = [];
        }

        // Sanitise once, then use the sanitised value for both the duplicate
        // check and the insert. Previously the lookup used the sanitised value
        // while the raw request body was written to the row.
        $email = isset($body['email']) ? sanitize_email($body['email']) : '';

        if (empty($email)) {
            return new WP_Error('invalid_data', 'Email is required', ['status' => 400]);
        }

        // Enforced again here, not just in the route schema, so a direct call
        // cannot write a nameless registrant.
        $names = self::getRequiredNames($body);

        if (is_wp_error($names)) {
            return $names;
        }

        [$first_name, $surname] = $names;

        $table_name = $wpdb->prefix . 'cison_preconference_2025';

        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE email = %s LIMIT 1",
                $email
            )
        );

        if (!empty($existing)) {
            return new WP_Error('already_exists', 'Record already exists for this email', ['status' => 400]);
        }

        $filename = '';

        if (isset($body['cert_name']) && $body['cert_name'] !== '') {
            $name_check = self::validateCertificateFileName($body['cert_name']);

            if (is_wp_error($name_check)) {
                return new WP_Error('invalid_data', $name_check->get_error_message(), ['status' => 400]);
            }

            $filename = self::sanitizeCertificateFileName($body['cert_name']);
        }

        $data = [
            'order_id' => isset($body['order_id']) ? absint($body['order_id']) : 0,
            'member_id' => isset($body['member_id']) ? sanitize_text_field($body['member_id']) : '',
            'first_name' => $first_name,
            'last_name' => $surname,
            'item_name' => isset($body['item_name']) ? sanitize_text_field($body['item_name']) : '',
            'item_price' => isset($body['item_price']) ? (float) $body['item_price'] : 0.00,
            'order_total' => isset($body['order_total']) ? (float) $body['order_total'] : 0.00,
            'status' => isset($body['status']) ? sanitize_text_field($body['status']) : '',
            'paid_date' => self::toMySqlDateTime(isset($body['paid_date']) ? $body['paid_date'] : ''),
            'email' => $email,
            'phone' => isset($body['phone']) ? sanitize_text_field($body['phone']) : '',
            'payment_method' => isset($body['payment_method']) ? sanitize_text_field($body['payment_method']) : '',
            'transaction_id' => isset($body['transaction_id']) ? sanitize_text_field($body['transaction_id']) : '',
            'order_link' => isset($body['order_link']) ? esc_url_raw($body['order_link']) : '',
            'billing_state' => isset($body['billing_state']) ? sanitize_text_field($body['billing_state']) : '',
            'cert_url' => $filename !== '' ? content_url('private/preconference/' . $filename) : '',
        ];

        // Positional, one entry per key in $data. "%f" for the money columns:
        // "%d" previously truncated 150.50 to 150.
        //
        // last_updated is deliberately omitted. The column is
        // TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, and
        // writing a Unix epoch integer into it is an invalid datetime that
        // fails outright under MySQL strict mode.
        $formats = ['%d', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];

        $saved = $wpdb->insert($table_name, $data, $formats);

        if ($saved === false) {
            error_log('CISON: Failed to save pre-conference record: ' . $wpdb->last_error);

            return new WP_Error('save_failed', 'Failed to save pre-conference record', ['status' => 500]);
        }

        return rest_ensure_response([
            'status' => 'success',
            'message' => 'Pre-conference registration saved successfully',
            'id' => (int) $wpdb->insert_id,
            'cert_url' => $data['cert_url'],
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

            $required = cison_get_required_fees($is_transiting, $reg_year, False);
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