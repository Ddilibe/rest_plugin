<?php
namespace SRC;

use SRC\Routes\AuthRoute;
use SRC\Routes\HelloRoute;
use SRC\Routes\SubmitRoute;
use SRC\Routes\UserRoute;
use SRC\Routes\BuddyBossRoute;

class Loader
{
    public static function init()
    {
        error_log('=== SRC\Loader::init() called ===');
        
        // Register routes on rest_api_init
        add_action('rest_api_init', [self::class, 'routes']);
        
        // Also register on init for early registration
        add_action('init', [self::class, 'early_routes'], 5);
        
        // Check if BuddyBoss is active
        add_action('init', [self::class, 'check_buddyboss']);
    }
    
    public static function early_routes()
    {
        error_log('=== Registering early routes ===');
        
        // Force registration of public endpoints early
        self::register_public_endpoints();
    }
    
    public static function routes()
    {
        error_log('=== SRC\Loader::routes() called ===');
        
        // Register all routes
        AuthRoute::register();
        HelloRoute::register();
        SubmitRoute::register();
        UserRoute::register();
        BuddyBossRoute::register();
        
        // Log registered routes
        add_action('rest_api_init', function() {
            global $wp_rest_server;
            if ($wp_rest_server) {
                $routes = $wp_rest_server->get_routes();
                error_log('Total registered routes: ' . count($routes));
                
                foreach ($routes as $route => $handlers) {
                    if (strpos($route, '/cison/v1') === 0) {
                        error_log('CISON route: ' . $route);
                    }
                }
            }
        }, 999);
    }
    
    private static function register_public_endpoints()
    {
        error_log('=== Registering public endpoints ===');
        
        // Register a test endpoint directly
        register_rest_route('cison/v1', '/test', [
            'methods' => 'GET',
            'callback' => function() {
                return [
                    'success' => true,
                    'message' => 'Public test endpoint working!',
                    'time' => current_time('mysql'),
                    'user_logged_in' => is_user_logged_in(),
                    'user_id' => get_current_user_id()
                ];
            },
            'permission_callback' => '__return_true'
        ]);
    }
    
    public static function check_buddyboss()
    {
        // Check if BuddyBoss is active
        if (!function_exists('bp_is_active')) {
            error_log('BuddyBoss not active');
            return;
        }
        
        error_log('BuddyBoss is active. Components:');
        
        // Check which BuddyBoss components are active
        $components = [
            'activity',
            'members',
            'groups',
            'friends',
            'messages',
            'notifications',
            'xprofile'
        ];
        
        foreach ($components as $component) {
            if (bp_is_active($component)) {
                error_log('  - ' . $component . ': Active');
            } else {
                error_log('  - ' . $component . ': Inactive');
            }
        }
    }
}