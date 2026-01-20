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

// Enable logging
error_log('=== My REST API Plugin Loading ===');

/**
 * DISABLE BuddyBoss REST API restrictions FOR OUR NAMESPACE
 * This must run EARLY, before BuddyBoss loads its restrictions
 */
add_action('plugins_loaded', function() {
    error_log('Plugins loaded - checking BuddyBoss restrictions');
    
    // Priority 0 to run before BuddyBoss
    add_filter('bb_rest_is_request_to_rest_api', function($is_rest_api) {
        if (!empty($_SERVER['REQUEST_URI'])) {
            $request_uri = $_SERVER['REQUEST_URI'];
            error_log('Checking request URI: ' . $request_uri);
            
            // If it's our API, force it to be recognized as REST API
            if (strpos($request_uri, '/wp-json/cison/v1/') !== false) {
                error_log('Forcing cison/v1 to be recognized as REST API');
                return true;
            }
        }
        return $is_rest_api;
    }, 0);
    
    // Disable BuddyBoss authentication for our namespace
    add_filter('bb_rest_is_allowed', function ($allowed, $request) {
        if (!$request instanceof WP_REST_Request) {
            return $allowed;
        }
        
        $route = $request->get_route();
        error_log('bb_rest_is_allowed checking: ' . $route);
        
        // Allow ALL routes under cison/v1 without authentication
        if (strpos($route, '/cison/v1/') === 0) {
            error_log('ALLOWING cison/v1 route: ' . $route);
            return true; // This is the key - return TRUE to bypass auth
        }
        
        return $allowed;
    }, 999, 2);
    
    // Also disable BuddyBoss' rest_send_cors_headers filter
    add_filter('rest_pre_serve_request', function($served, $result, $request, $server) {
        $route = $request->get_route();
        if (strpos($route, '/cison/v1/') === 0) {
            // Remove BuddyBoss CORS headers
            remove_filter('rest_pre_serve_request', 'bp_rest_allow_all_cors', 15);
            remove_filter('rest_pre_serve_request', 'bp_rest_send_cors_headers', 15);
        }
        return $served;
    }, 1, 4);
    
    // Disable BuddyBoss specific authentication
    add_filter('bp_rest_authentication_errors', function($error) {
        if (!empty($_SERVER['REQUEST_URI']) && 
            strpos($_SERVER['REQUEST_URI'], '/wp-json/cison/v1/') !== false) {
            error_log('Bypassing bp_rest_authentication_errors for cison/v1');
            return null; // Return null to indicate no error
        }
        return $error;
    }, 10);
}, 0); // Priority 0 to run before BuddyBoss loads

/**
 * ALTERNATIVE: Completely disable BuddyBoss REST API if needed
 * Uncomment if the above doesn't work
 */
/*
add_action('init', function() {
    if (class_exists('BP_REST_Components_Endpoint')) {
        remove_action('rest_api_init', 'bp_rest_register_endpoints', 10);
        remove_filter('rest_authentication_errors', 'bp_rest_authentication_errors', 15);
    }
});
*/

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
        error_log('Loaded: ' . $file);
    } else {
        error_log('Autoload failed: ' . $file);
    }
});

// BOOT PLUGIN
if (class_exists('SRC\\Loader')) {
    error_log('SRC\\Loader found - initializing');
    SRC\Loader::init();
} else {
    error_log('ERROR: SRC\\Loader not found after autoload');
}

/**
 * Add CORS headers for our API
 */
add_action('rest_api_init', function() {
    // Remove BuddyBoss CORS headers for our namespace
    remove_filter('rest_pre_serve_request', 'bp_rest_send_cors_headers', 15);
    
    // Add our own CORS headers
    add_filter('rest_pre_serve_request', function($value) {
        if (!empty($_SERVER['REQUEST_URI']) && 
            strpos($_SERVER['REQUEST_URI'], '/wp-json/cison/v1/') !== false) {
            
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce');
            header('Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages, X-WP-Nonce');
        }
        return $value;
    }, 20);
});

/**
 * Register Swagger docs endpoint
 */
add_action('rest_api_init', function () {
    error_log('Registering /docs endpoint');
    register_rest_route('cison/v1', '/docs', [
        'methods' => 'GET',
        'callback' => function () {
            error_log('Docs endpoint called');
            return file_get_contents(MYAPI_PATH . 'swagger.json');
        },
        'permission_callback' => '__return_true'
    ]);
});

/**
 * Log all REST requests to our namespace
 */
add_filter('rest_pre_dispatch', function($result, $server, $request) {
    $route = $request->get_route();
    if (strpos($route, '/cison/v1/') === 0) {
        error_log('REST Request to: ' . $route);
        error_log('Method: ' . $request->get_method());
        error_log('Query params: ' . json_encode($request->get_query_params()));
        
        // Force authentication bypass
        if (!is_user_logged_in() && strpos($route, '/cison/v1/auth') === false) {
            error_log('User not logged in, but allowing API access');
        }
    }
    return $result;
}, 10, 3);

/**
 * NUCLEAR OPTION: Disable all authentication for our namespace
 * Add this as a last resort
 */
add_filter('rest_authentication_errors', function ($result) {
    if (!empty($_SERVER['REQUEST_URI']) && 
        strpos($_SERVER['REQUEST_URI'], '/wp-json/cison/v1/') !== false) {
        error_log('rest_authentication_errors: Bypassing ALL auth for cison/v1');
        
        // Check if there's already an error
        if (is_wp_error($result) && $result->get_error_code() === 'rest_not_logged_in') {
            error_log('Overriding rest_not_logged_in error');
            return true; // Return true to indicate it's allowed
        }
        
        // If BuddyBoss is blocking, return true to bypass
        if ($result instanceof WP_Error && 
            $result->get_error_code() === 'bb_rest_authorization_required') {
            error_log('Overriding bb_rest_authorization_required error');
            return true;
        }
    }
    return $result;
}, 999); // Very high priority to override BuddyBoss