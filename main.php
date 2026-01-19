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

/**
 * Register Swagger docs endpoint
 */
add_action('rest_api_init', function () {
    register_rest_route('cison/v1', '/docs', [
        'methods' => 'GET',
        'callback' => function () {
            return file_get_contents(MYAPI_PATH . 'swagger.json');
        },
        'permission_callback' => '__return_true' // Add this!
    ]);
});

/**
 * BuddyBoss: Allow our namespace (primary method)
 */
add_filter('bb_rest_is_allowed', function ($allowed, $request) {
    if (!$request instanceof WP_REST_Request) {
        return $allowed;
    }

    $route = $request->get_route();

    // Allow ALL CISON API routes publicly
    if (strpos($route, '/cison/v1/') === 0) {
        return true;
    }

    return $allowed;
}, 999, 2);

/**
 * BuddyBoss: Mark as REST API request
 */
add_filter('bb_rest_is_request_to_rest_api', function($is_rest_api) {
    if (!empty($_SERVER['REQUEST_URI']) && 
        strpos($_SERVER['REQUEST_URI'], '/wp-json/cison/v1/') !== false) {
        return true;
    }

    return $is_rest_api;
}, 10);

/**
 * Disable authentication errors for our namespace (fallback)
 */
add_filter('rest_authentication_errors', function ($result) {
    if (!empty($_SERVER['REQUEST_URI']) && 
        strpos($_SERVER['REQUEST_URI'], '/wp-json/cison/v1/') !== false) {
        return true; // Changed from null to true
    }

    return $result;
}, 5); // Lower priority to run earlier