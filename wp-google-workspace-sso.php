<?php
/*
Plugin Name: WordPress Google Workspace SSO
Plugin URI: https://github.com/hypeventures/woocommerce-page-cache
Description: WordPress plugin for Google single sign-on admin login via OAuth.
Version: 0.1
Author: Achim Galeski <achim.galeski@gmail.com>
Author URI: https://achim-galeski.de/
License: GPLv3
Text Domain: wordpress-google-sso
Domain Path: /languages/
*/

defined('ABSPATH') || exit;

use AGaleski\WordPress\GoogleWorkspaceSso\Login;
use AGaleski\WordPress\GoogleWorkspaceSso\Admin;

function wpgwsso_autoload($class = '')
{
    $prefix = 'AGaleski\\WordPress\\GoogleWorkspaceSso\\';
    $len    = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {

        return;
    }

    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, $len)) . '.php';

    if (file_exists($file)) {

        require $file;

    }
}

/**
 * Initialize the plugin once and run the contextual required code.
 *
 * @return void
 */
function wpgwsso_init()
{
    /*
     * Prevent running multiple times.
     */
    if (defined('WPGWSSO_PATH')) {

        return;
    }

    define('WPGWSSO_PATH',       __DIR__ . '/');
    define('WPGWSSO_REL_PATH',   basename(dirname(__FILE__)));
    define('WPGWSSO_URL',        plugin_dir_url(__FILE__));
    define('WPGWSSO_ADMIN_URL',  admin_url());
    define('WPGWSSO_PREFIX',     'wpgwsso_');

    load_plugin_textdomain('wordpress-google-sso', false, WPGWSSO_REL_PATH . '/languages');
    spl_autoload_register('wpgwsso_autoload');

    require_once 'vendor/autoload.php';

    if (! defined('REST_REQUEST') && ! defined('DOING_CRON')) {

        add_action('login_head', [ Login::class, 'run' ], 1);
        add_filter('authenticate', [ Login::class, 'authenticate' ], 10);
        add_action('admin_init', [ Admin::class, 'redirectTo' ]);
        add_action('admin_menu', [ Admin::class, 'add' ]);
        add_action('wp_ajax_' . Admin::ACTION, [ Admin::class, 'save' ]);
        add_filter('woocommerce_login_credentials', [ Login::class, 'handleWoocommerce' ], 1);

    }

}
add_action('init', 'wpgwsso_init', 11);

/**
 * Small helper for easy debugging - writes into default debug.log if debugging is enabled.
 *
 * @param mixed $data
 *
 * @return void
 */
function wpgwsso_debug($data = null)
{
    error_log(print_r($data, true) . PHP_EOL, 3, WP_CONTENT_DIR . '/debug.log');
}
