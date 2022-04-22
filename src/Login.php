<?php

namespace AGaleski\WordPress\GoogleWorkspaceSso;

use \Google_Client as GoogleClient;
use \Google_Service_Oauth2 as GoogleServiceOauth2;

/**
 * Class Login
 *
 * @package AGaleski\WordPress\GoogleWorkspaceSso
 */
class Login
{

    /**
     * Renders the OAuth based Google SSO login screen and handles redirects to Google.
     *
     * @uses Settings::isActive(), Settings::getAccess(), GoogleClient::createAuthUrl()
     *
     * @return void
     */
    public static function run()
    {
        if (
            (isset($_GET[ 'action' ]) && $_GET[ 'action' ] === 'confirm_admin_email')
            || ! Settings::isActive()
        ) {

            return;
        }

        $access = Settings::getAccess();

        if (! empty($_GET[ 'handler' ]) && ! empty($access[ $_GET[ 'handler' ] ])) {

            setcookie('sso_handler', $_GET[ 'handler' ], time() + 86400, COOKIEPATH, COOKIE_DOMAIN, true);
            wp_safe_redirect(self::getClient($access[ $_GET[ 'handler' ] ])->createAuthUrl());
            exit;

        }

        self::render($access);
        exit;
    }

    /**
     * Verifies authentication and matches a WordPress user if possible.
     *
     * @param null|\WP_User $user
     *
     * @uses Settings::getAccess(), GoogleClient::fetchAccessTokenWithAuthCode()
     *
     * @return false|mixed|\WP_User|null
     */
    public static function authenticate($user = null)
    {
        if (
            isset($_GET[ 'code' ])
            && isset($_COOKIE[ 'sso_handler' ])
            && ! empty($access = Settings::getAccess()[ $_COOKIE[ 'sso_handler' ] ])
        ) {

            $client = self::getClient($access);
            $result = $client->fetchAccessTokenWithAuthCode($_GET['code']);
            $user   = false;

            if (isset($result[ 'id_token' ])) {

                $oauth2   = new GoogleServiceOauth2($client);
                $userInfo = $oauth2->userinfo->get();

                if (property_exists($userInfo, 'email')) {

                    $user = get_user_by('email', $userInfo->email);

                }


            }

            if (! empty($user)) {

                return $user;
            }

            wp_redirect(get_home_url() . '/wp-login.php');
            exit;

        }

        return $user;
    }

    /**
     * Prevents Admins/Shop-Managers from logging in via the MyAccount page.
     *
     * @see \WC_Form_Handler::process_login()
     *
     * @param array $creds
     *
     * @return array
     */
    public static function handleWoocommerce($creds = [])
    {
        if (class_exists('WooCommerce') && ! empty($creds[ 'user_login' ])) {

            $userLogin = esc_attr($creds[ 'user_login' ]);

            if (strpos($userLogin, '@')) {

                $user = get_user_by('email', $userLogin);

            } else {

                $user = get_user_by('login', $userLogin);

            }

            if (! empty($user) && property_exists($user, 'roles') && $user->roles[ 0 ] !== 'customer') {

                wp_redirect(home_url() . '/wp-login.php');
                exit;

            }

        }

        return $creds;
    }

    /**
     * Creates, sets up and returns the Google_Client.
     *
     * @param array $access
     *
     * @uses Settings::decrypt()
     * @uses GoogleClient::setClientId()
     * @uses GoogleClient::setClientSecret()
     * @uses GoogleClient::addScope()
     * @uses GoogleClient::setRedirectUri()
     *
     * @return GoogleClient
     */
    private static function getClient($access = [ 'id' => '', 'secret' => '' ])
    {
        $client = new GoogleClient();
        $client->setClientId(Settings::decrypt($access[ 'id' ]));
        $client->setClientSecret(Settings::decrypt($access[ 'secret' ]));
        $client->addScope([ 'openid', 'email', 'https://www.googleapis.com/auth/userinfo.profile', ]);
        $client->setRedirectUri(get_home_url() . '/wp-login.php');

        return $client;
    }

    /**
     * Renders the Login page for wp-admin via Google Workspace SSO login.
     *
     * @param array $access
     *
     * @return void
     */
    private static function render(array $access = [])
    {
        ?>
        <!DOCTYPE html>
        <html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
        <head>
            <meta http-equiv="Content-Type"
                  content="<?=get_bloginfo('html_type', 'display')?>; charset=<?=get_bloginfo('charset', 'display')?>"
            />
            <title><?=get_bloginfo('name', 'display')?></title>
            <style>
                .container {
                    width: 100%;
                    height: 100%;
                    display: table;
                    position: absolute;
                    top: 0;
                    left: 0;
                    font-family: Arial, Helvetica, sans-serif;
                }
                .container form {
                    display: table-cell;
                    vertical-align: middle;
                }
                .container form > div {
                    text-align: center;
                    margin: 0 auto;
                    width: 100%;
                    background: #fff;
                    padding: 1rem 0;
                }
                .container form select {
                    padding: 0.3rem;
                    cursor: pointer;
                    outline: none;
                    margin-top: 1px;
                }
                .container form button {
                    background-color: #3E5BC7;
                    padding: 5px 12px;
                    color: #fff;
                    cursor: pointer;
                    font-weight: bold;
                }
            </style>
        </head>
        <body>
        <div class="container">
            <form>
                <div><?=get_custom_logo(get_current_blog_id())?></div>
                <div>
                    <p><label for="handler">Log in with your Google workspace domain:</label></p>
                    <select id="handler" name="handler">
                        <option value="" disabled <?=empty($_COOKIE[ 'sso_handler' ]) ? '' : 'selected'?>>
                            Select one
                        </option>
                    <?php foreach ($access as $brand => $data) {

                        $selected = ! empty($_COOKIE[ 'sso_handler' ]) && $_COOKIE[ 'sso_handler' ] === $brand ? 'selected' : '';

                        echo "<option value=\"{$brand}\" {$selected}>{$brand}</option>";

                    } ?>
                    </select>
                    <button type="submit">Log in with Google</button>
                </div>
            </form>
        </div>
        </body>
        </html>
        <?php
    }

}
