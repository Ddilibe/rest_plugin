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
 * CRITICAL: Disable BuddyBoss REST API restrictions FOR OUR NAMESPACE
 * This must run at plugins_loaded with priority 0 (BEFORE BuddyBoss)
 */
add_action('plugins_loaded', function() {
    error_log('=== Plugins loaded - Disabling BuddyBoss restrictions ===');
    
    // Nuclear option: Disable ALL BuddyBoss REST API functionality
    if (class_exists('BP_REST_Components_Endpoint')) {
        error_log('Disabling BuddyBoss REST API core functionality');
        
        // Remove BuddyBoss REST API initialization
        remove_action('rest_api_init', 'bp_rest_init', 10);
        
        // Remove authentication errors
        remove_filter('rest_authentication_errors', 'bp_rest_authentication_errors', 15);
        
        // Remove CORS headers
        remove_filter('rest_pre_serve_request', 'bp_rest_send_cors_headers', 15);
        remove_filter('rest_pre_serve_request', 'bp_rest_allow_all_cors', 15);
        
        // Remove endpoint registration
        remove_action('rest_api_init', 'bp_rest_register_endpoints', 10);
        
        // Clear any cached endpoints
        global $wp_rest_server;
        if ($wp_rest_server) {
            $wp_rest_server->override_by_default = true;
        }
    }
    
    // Completely bypass bb_rest_is_allowed for our namespace
    add_filter('bb_rest_is_allowed', function ($allowed, $request) {
        if (!$request instanceof WP_REST_Request) {
            return $allowed;
        }
        
        $route = $request->get_route();
        error_log('bb_rest_is_allowed checking: ' . $route);
        
        // Allow ALL routes under cison/v1 without authentication
        if (strpos($route, '/cison/v1/') === 0) {
            error_log('BYPASSING BuddyBoss auth for: ' . $route);
            return true; // This is the key - return TRUE to bypass auth
        }
        
        // Also allow root cison/v1
        if ($route === '/cison/v1' || $route === '/cison/v1/') {
            error_log('BYPASSING BuddyBoss auth for namespace root');
            return true;
        }
        
        return $allowed;
    }, 999999, 2); // Very high priority
    
    // Also bypass the main WordPress REST authentication
    add_filter('rest_authentication_errors', function ($result) {
        global $wp;
        
        // Get current route
        $route = isset($wp->query_vars['rest_route']) ? $wp->query_vars['rest_route'] : '';
        if (empty($route) && !empty($_SERVER['REQUEST_URI'])) {
            $uri = $_SERVER['REQUEST_URI'];
            if (strpos($uri, '/wp-json/cison/v1/') !== false) {
                $route = '/cison/v1' . str_replace('/wp-json/cison/v1', '', $uri);
            }
        }
        
        // Bypass authentication for our namespace
        if (strpos($route, '/cison/v1/') === 0 || $route === '/cison/v1') {
            error_log('Bypassing rest_authentication_errors for: ' . $route);
            return null; // Return null means authenticated
        }
        
        return $result;
    }, 999999);
    
    // Force REST API to recognize our routes
    add_filter('rest_pre_dispatch', function($result, $server, $request) {
        $route = $request->get_route();
        
        if (strpos($route, '/cison/v1/') === 0 || $route === '/cison/v1') {
            error_log('rest_pre_dispatch for cison/v1 route: ' . $route);
            
            // Force authentication to pass
            $user_id = apply_filters('determine_current_user', false);
            if (!$user_id) {
                // Set a dummy user to bypass auth
                wp_set_current_user(0);
            }
        }
        
        return $result;
    }, 10, 3);
    
}, 0); // Priority 0 to run BEFORE BuddyBoss

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
    // Remove any existing CORS headers from BuddyBoss
    remove_filter('rest_pre_serve_request', 'bp_rest_send_cors_headers', 15);
    remove_filter('rest_pre_serve_request', 'bp_rest_allow_all_cors', 15);
    
    // Add our own CORS headers
    add_filter('rest_pre_serve_request', function($value) {
        $route = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        
        if (strpos($route, '/wp-json/cison/v1') !== false) {
            error_log('Adding CORS headers for cison/v1 request');
            
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce, X-Requested-With');
            header('Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages, X-WP-Nonce');
            
            // Handle preflight requests
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                status_header(200);
                exit;
            }
        }
        return $value;
    }, 20);
}, 0);

/**
 * Register Swagger docs endpoint (must be public)
 */
add_action('rest_api_init', function () {
    error_log('Registering /docs endpoint');
    register_rest_route('cison/v1', '/docs', [
        'methods' => 'GET',
        'callback' => function () {
            error_log('Docs endpoint called');
            $docs_file = MYAPI_PATH . 'swagger.json';
            
            if (file_exists($docs_file)) {
                $content = file_get_contents($docs_file);
                return json_decode($content, true);
            } else {
                return [
                    'error' => 'Swagger docs not found',
                    'file' => $docs_file
                ];
            }
        },
        'permission_callback' => function() {
            // Always allow access to docs
            return true;
        }
    ]);
}, 10);

/**
 * Register a simple hello endpoint for testing
 */
add_action('rest_api_init', function() {
    error_log('Registering hello endpoint');
    register_rest_route('cison/v1', '/hello', [
        'methods' => 'GET',
        'callback' => function() {
            return [
                'success' => true,
                'message' => 'Hello from CISON API!',
                'timestamp' => current_time('timestamp'),
                'version' => '1.0.0',
                'routes' => [
                    '/hello',
                    '/docs',
                    '/auth/login',
                    '/buddyboss/stats',
                    '/buddyboss/groups',
                    '/buddyboss/activity',
                    '/buddyboss/profile/{id}'
                ]
            ];
        },
        'permission_callback' => function() {
            // Always allow access to hello endpoint
            return true;
        }
    ]);
}, 10);

/**
 * Log all REST requests to our namespace
 */
add_filter('rest_pre_dispatch', function($result, $server, $request) {
    $route = $request->get_route();
    if (strpos($route, '/cison/v1/') === 0 || $route === '/cison/v1') {
        error_log('=== CISON API Request ===');
        error_log('Route: ' . $route);
        error_log('Method: ' . $request->get_method());
        error_log('Params: ' . json_encode($request->get_params()));
        
        // Check if route exists
        $routes = $server->get_routes();
        if (!isset($routes[$route]) && !isset($routes[$route . '/'])) {
            error_log('Route NOT FOUND in registered routes');
        } else {
            error_log('Route FOUND');
        }
    }
    return $result;
}, 10, 3);

/**
 * DEBUG: List all registered routes on admin page
 */
if (is_admin()) {
    add_action('admin_notices', function() {
        global $wp_rest_server;
        
        if ($wp_rest_server) {
            $routes = $wp_rest_server->get_routes();
            
            echo '<div class="notice notice-info">';
            echo '<h3>CISON API Registered Routes:</h3>';
            echo '<ul>';
            
            foreach ($routes as $route => $handlers) {
                if (strpos($route, '/cison/v1') === 0) {
                    echo '<li><strong>' . esc_html($route) . '</strong>';
                    foreach ($handlers as $handler) {
                        if (isset($handler['methods'])) {
                            echo ' - Methods: ' . implode(', ', array_keys($handler['methods']));
                        }
                    }
                    echo '</li>';
                }
            }
            
            echo '</ul>';
            echo '</div>';
        }
    });
}