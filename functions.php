<?php
/**
 * WordPress Google Workspace SSO - WordPress plugin for Google single sign-on admin login via OAuth.
 * Copyright (C) 2022 Achim Galeski ( achim-galeski@gmail.com )
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 3, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA02110-1301USA
 *
 * @version 1.0.0
 */

use AGaleski\WordPress\GoogleWorkspaceSso\Admin;
use AGaleski\WordPress\GoogleWorkspaceSso\Login;
use AGaleski\WordPress\GoogleWorkspaceSso\Settings;

/**
 * Callback function. Custom dependency free psr-4 autoloader for this plugin.
 *
 * @used-by  spl_autoload_register()
 *
 * @param string $class
 *
 * @return void
 */
function wpgwsso_autoload(string $class = ''): void
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
function wpgwsso_init(): void
{
    if (
        defined('WPGWSSO_PATH')
        || defined('REST_REQUEST')
        || defined('DOING_CRON')
    ) {
        return;
    }

    define('WPGWSSO_PATH', __DIR__ . '/');
    define('WPGWSSO_REL_PATH', basename(dirname(__FILE__)));
    define('WPGWSSO_URL', plugin_dir_url(__FILE__));
    define('WPGWSSO_ADMIN_URL', admin_url());
    define('WPGWSSO_PREFIX', 'wpgwsso_');

    load_plugin_textdomain('wordpress-google-sso', false, WPGWSSO_REL_PATH . '/languages');
    spl_autoload_register('wpgwsso_autoload');

    add_action('admin_menu', [ Admin::class, 'add' ]);
    add_action('wp_ajax_' . Admin::ACTION, [ Admin::class, 'save' ]);

    if (Settings::isActive()) {
        add_action('login_head', [ Login::class, 'run' ], 1);
        add_filter('authenticate', [ Login::class, 'authenticate' ], 10);
        add_action('admin_init', [ Admin::class, 'redirectTo' ]);
        add_filter('woocommerce_login_credentials', [ Login::class, 'handleWoocommerce' ], 1);
    }
}

/**
 * Small helper for easy debugging - writes into default debug.log if debugging is enabled.
 *
 * @param mixed $data
 *
 * @return void
 */
function wpgwsso_debug($data = null): void
{
    error_log(print_r($data, true) . PHP_EOL, 3, WP_CONTENT_DIR . '/debug.log');
}
