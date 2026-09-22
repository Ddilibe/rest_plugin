<?php

namespace SRC\Controllers;

use WP_REST_Request;
use WP_Error;

class ConferenceRegistrationController
{
    const DEFAULT_REGISTERING_FOR = 'Conference Onsite + Preconference Onsite';
    const DEFAULT_PAYMENT_STATUS = 'paid';

    /**
     * Define and validate query parameters.
     */
    public static function get_endpoint_args()
    {
        return [
            'registering_for' => [
                'required' => false,
                'type' => 'string',
                'default' => self::DEFAULT_REGISTERING_FOR,
                'description' => 'Filter by the value of the registering_for column.',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'year' => [
                'required' => false,
                'type' => 'integer',
                'default' => (int) date('Y'),
                'description' => 'Filter registrations by registration year (YYYY).',
                'sanitize_callback' => 'absint',
                'validate_callback' => [ConferenceRegistrationController::class, 'validate_year'],
            ],
            'paymentstatus' => [
                'required' => false,
                'type' => 'string',
                'default' => self::DEFAULT_PAYMENT_STATUS,
                'description' => 'Filter by payment_status.',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    /**
     * Validate that the year is a 4-digit integer.
     */
    public static function validate_year($value)
    {
        return is_numeric($value) && (int) $value >= 1000 && (int) $value <= 9999;
    }

    /**
     * Retrieve all transactions from the conference_registrations table.
     */
    public static function get_transactions(WP_REST_Request $request)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'conference_registrations';

        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $table_name
        ));

        if (!$table_exists) {
            return new WP_Error(
                'table_not_found',
                sprintf('Table %s does not exist.', $table_name),
                ['status' => 500]
            );
        }

        $registering_for = $request->get_param('registering_for') ?: self::DEFAULT_REGISTERING_FOR;
        $year = (int) ($request->get_param('year') ?: date('Y'));
        $payment_status = $request->get_param('paymentstatus') ?: self::DEFAULT_PAYMENT_STATUS;

        $where = [];
        $placeholders = [];

        if (strtolower($registering_for) !== 'all') {
            $where[] = 'registering_for = %s';
            $placeholders[] = $registering_for;
        }

        $where[] = 'YEAR(registration_date) = %d';
        $placeholders[] = $year;

        $where[] = 'payment_status = %s';
        $placeholders[] = $payment_status;

        // Make the 'all' case explicit in the response filters
        if (strtolower($registering_for) === 'all') {
            $registering_for = 'all';
        }

        $sql = "SELECT * FROM {$table_name} WHERE " . implode(' AND ', $where) . ' ORDER BY registration_date DESC';

        $transactions = $wpdb->get_results($wpdb->prepare($sql, $placeholders), ARRAY_A);

        return rest_ensure_response([
            'status' => 'success',
            'total' => count($transactions),
            'filters' => [
                'registering_for' => $registering_for,
                'year' => $year,
                'paymentstatus' => $payment_status,
            ],
            'data' => $transactions,
        ]);
    }
}