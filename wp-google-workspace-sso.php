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

include 'functions.php';

add_action('init', 'wpgwsso_init', 11);
