<?php

namespace SRC\Models;

function create_preconference_model_2025() {

    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_name = $wpdb->prefix . 'cison_preconference_2025';

    $sql = "CREATE TABLE {$table_name} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        order_id bigint(20) NOT NULL,
        member_id varchar(50) DEFAULT '',
        first_name varchar(100) DEFAULT '',
        last_name varchar(100) DEFAULT '',
        item_name varchar(255) DEFAULT '',
        item_price decimal(10,2) DEFAULT '0.00',
        order_total decimal(10,2) DEFAULT '0.00',
        status varchar(20) DEFAULT '',
        paid_date datetime DEFAULT NULL,
        email varchar(100) NOT NULL,
        phone varchar(20) DEFAULT '',
        payment_method varchar(50) DEFAULT '',
        transaction_id varchar(100) DEFAULT '',
        order_link text DEFAULT NULL,
        billing_state varchar(50) DEFAULT '',
        last_updated timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY order_id (order_id),
        KEY member_id (member_id),
        KEY email (email)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    $column_exists = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = %s 
            AND TABLE_NAME = %s 
            AND COLUMN_NAME = 'certid'",
            DB_NAME,
            $table_name
        )
    );
    
    if (empty($column_exists)) {
        $wpdb->query(
            "ALTER TABLE {$table_name} 
            ADD COLUMN certid varchar(100) DEFAULT '' AFTER member_id,
            ADD INDEX certid (certid)"
        );
    }

}

register_activation_hook(__FILE__, 'create_preconference_model_2025');
