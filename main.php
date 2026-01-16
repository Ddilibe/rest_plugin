<?php
/**
 * Plugin Name: My REST API
 * Description: Modular REST API with JWT
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MYAPI_PATH', plugin_dir_path(__FILE__));

/**
 * PSR-4 AUTOLOADER FOR SRC\
 */
spl_autoload_register(function ($class) {

    // Only load our plugin classes
    if (strpos($class, 'SRC\\') !== 0) {
        return;
    }

    // Convert namespace to file path
    $relative_class = substr($class, 4); // remove "SRC\"
    $relative_class = str_replace('\\', '/', $relative_class);

    $file = MYAPI_PATH . 'src/' . $relative_class . '.php';

    if (file_exists($file)) {
        require_once $file;
    } else {
        error_log('Autoload failed: ' . $file);
    }
});

// BOOT PLUGIN
if (class_exists('SRC\\Loader')) {
    SRC\Loader::init();
} else {
    error_log('SRC\\Loader not found after autoload');
}

add_action('rest_api_init', function () {
    register_rest_route('cison/v1', '/docs', [
        'methods' => 'GET',
        'callback' => function () {
            return file_get_contents(MYAPI_PATH . 'swagger.json');
        }
    ]);
});

// register_activation_hook(__FILE__, function () {
//     require_once ABSPATH . 'wp-admin/includes/upgrade.php';

//     global $wpdb;

//     $table = $wpdb->prefix . 'users';
//     $charset = $wpdb->get_charset_collate();

//     $sql = "
//         CREATE TABLE $table (
//             id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
//             name VARCHAR(255) NOT NULL,
//             email VARCHAR(255) NOT NULL,
//             created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
//             PRIMARY KEY (id),
//             KEY user_id (user_id)
//         ) $charset;
//     ";

//     dbDelta($sql);
// });

// require_once ABSPATH . 'wp-admin/in'