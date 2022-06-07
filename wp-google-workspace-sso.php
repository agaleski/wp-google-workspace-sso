<?php
/**
 * Plugin Name: WordPress Google Workspace SSO
 * Plugin URI: https://github.com/hypeventures/woocommerce-page-cache
 * Description: WordPress plugin for Google single sign-on admin login via OAuth.
 * Version: 1.0.0
 * Author: Achim Galeski <achim.galeski@gmail.com>
 * Author URI: https://achim-galeski.de/
 * License: GPLv3
 * Text Domain: wordpress-google-workspace-sso
 * Domain Path: /languages/
 * Requires at least: 5.7
 * Requires PHP: 7.4
 *
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

defined('ABSPATH') || exit;

include 'functions.php';

add_action('init', 'wpgwsso_init', 11);
