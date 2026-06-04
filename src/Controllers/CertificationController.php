<?php

namespace SRC\Controllers;
use SRC\Config\Config;
use WP_REST_Request;
use WP_Error;
use WP_REST_Response;


class CertificationController
{
    /**
     * Delete a certificate entry.
     */
    public function handle_delete_certificate(WP_REST_Request $request)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cert_registry';
        $id = intval($request['id']);

        $deleted = $wpdb->delete($table, array('id' => $id), array('%d'));

        if (!$deleted) {
            return new WP_Error('not_found', 'Certificate not found or already deleted.', array('status' => 404));
        }

        return new WP_REST_Response(array('deleted' => true, 'id' => $id), 200);
    }

    /**
     * Update an existing certificate record or its file.
     */
    public function handle_update_certificate(WP_REST_Request $request)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cert_registry';
        $id = intval($request['id']);

        $current_cert = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        if (!$current_cert) {
            return new WP_Error('not_found', 'Certificate registry record not found.', array('status' => 404));
        }

        $update_data = array();

        // Check for file replacement
        if (!empty($_FILES['certificate_file'])) {
            $attachment_id = $this->upload_file('certificate_file', $current_cert->cert_name);
            if (is_wp_error($attachment_id)) {
                return $attachment_id;
            }

            $new_file_url = wp_get_attachment_url($attachment_id);
            $update_data['cert_hmac'] = wp_hash($current_cert->cert_key . '|' . $current_cert->user_id . '|' . $new_file_url, 'nonce');
        }

        if ($request->get_param('is_main') !== null) {
            $update_data['is_main'] = intval($request->get_param('is_main'));
        }
        if ($request->get_param('date_expiry') !== null) {
            $update_data['date_expiry'] = sanitize_text_field($request->get_param('date_expiry'));
        }

        if (empty($update_data)) {
            return new WP_Error('bad_request', 'No data or file changes provided.', array('status' => 400));
        }

        $wpdb->update($table, $update_data, array('id' => $id));

        return new WP_REST_Response(array('success' => true, 'updated_id' => $id), 200);
    }

    /**
     * Create a certificate with an attached file.
     */
    public function handle_create_certificate(WP_REST_Request $request)
    {
        global $wpdb;
        $wpdb->show_errors();
        $table = $wpdb->prefix . 'cert_registry';

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($table))) === $table) {

        } else {
            return new WP_Error("Table not found");
        }

        $name = $request->get_param("name") ? sanitize_text_field($request->get_param('name')) : "Membership Certificate";

        if (empty($_FILES['certificate_file'])) {
            return new WP_Error('missing_file', 'A certificate file is required.', array('status' => 400));
        }

        // Handle secure upload
        $attachment_id = $this->upload_file('certificate_file', $name);
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        $file_url = wp_get_attachment_url($attachment_id);
        $email = sanitize_text_field($request->get_param('user_email'));
        if (!$email) {
            return new WP_Error("Email is required for certificate creation.");
        }
        ;

        $user_table = $wpdb->prefix . 'users';
        $user = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT ID, user_email FROM {$user_table} WHERE user_email = %s LIMIT 1",
                $email
            )
        );
        $user_id = (int) $user->ID ? $user->ID : null;

        $template_id = sanitize_text_field($request->get_param('template_id'));

        $secure_auth = Config::get('SSSECURE_AUTH_KEY', '');

        $cert_key = sanitize_text_field($request->get_param("cert_key"));
        $cert_hmac = hash_hmac('sha256', $cert_key, $secure_auth);

        $username = sanitize_text_field($request->get_param("user_name"));

        if (!$username) {
            return new WP_Error("User_name is required for certificate creation. if you don't know, user_name is the name of the user receiving the certificate");
        }
        ;



        $cert_hmac_post = sanitize_text_field($request->get_param('hmac_key'));

        // return new WP_Error("Cert HMAC: " . $cert_hmac . " CERT HMAC POST: " . $cert_hmac_post . " CERT KEY:  " . $cert_key . " AUTH KEY: " . $secure_auth);

        if (!hash_equals($cert_hmac_post, $cert_hmac)) {
            return new WP_Error("Invalid cryptorization key");
        }

        $data = array(
            'user_id' => $user_id,
            'template_id' => $template_id,
            'cert_key' => $cert_key,
            'cert_name' => $name,
            'cert_hmac' => $cert_hmac,
            'user_name' => $username,
            'user_email' => $email,
            'is_main' => intval($request->get_param('is_main')),
            'file_url' => $file_url,
            'date_issued' => current_time('mysql'),
            'date_expiry' => $request->get_param('date_expiry') ? sanitize_text_field($request->get_param('date_expiry')) : null,
        );

        $inserted = $wpdb->insert($table, $data);

        if (!$inserted) {
            wp_delete_attachment($attachment_id, true);
            return new WP_Error('db_error', 'MySQL Error: ' . $wpdb->last_error, array('status' => 500));
        }

        $data['id'] = $wpdb->insert_id;
        $data['file_url'] = $file_url;
        $data['key'] = $cert_key;
        $data['hmac'] = $cert_hmac;

        return new WP_REST_Response($data, 201);
    }
    private function upload_file($file_key, $folder = '')
    {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        add_filter('intermediate_image_sizes_advanced', '__return_empty_array');

        $upload_dir_filter = function ($uploads) use ($folder) {

            if (empty($folder)) {
                return $uploads;
            }

            // Remove leading/trailing slashes
            $folder = trim($folder, '/');

            $custom_dir = $uploads['basedir'] . '/' . $folder;

            // Create directory if it doesn't exist
            if (!file_exists($custom_dir)) {
                if (!wp_mkdir_p($custom_dir)) {
                    return $uploads;
                }
            }

            // Ensure directory is writable
            if (!is_writable($custom_dir)) {
                return $uploads;
            }

            $uploads['path'] = $custom_dir;
            $uploads['subdir'] = '/' . $folder;
            $uploads['url'] = $uploads['baseurl'] . '/' . $folder;

            return $uploads;
        };

        add_filter('upload_dir', $upload_dir_filter);

        $attachment_id = media_handle_upload($file_key, 0);

        remove_filter('upload_dir', $upload_dir_filter);
        remove_filter('intermediate_image_sizes_advanced', '__return_empty_array');

        if (is_wp_error($attachment_id)) {
            return new WP_Error(
                'upload_error',
                $attachment_id->get_error_message(),
                ['status' => 500]
            );
        }

        $file_path = get_attached_file($attachment_id);

        if (!$file_path || !file_exists($file_path)) {

            wp_delete_attachment($attachment_id, true);

            return new WP_Error(
                'file_missing',
                'The uploaded file could not be found.',
                ['status' => 500]
            );
        }

        return [
            'attachment_id' => $attachment_id,
            'file_path' => $file_path,
            'file_url' => wp_get_attachment_url($attachment_id),
        ];
    }
}