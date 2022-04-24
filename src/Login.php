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

        if (! empty($_GET[ 'workspace' ]) && ! empty($access[ $_GET[ 'workspace' ] ])) {

            setcookie('workspace', $_GET[ 'workspace' ], time() + 86400, COOKIEPATH, COOKIE_DOMAIN, true);

            wp_redirect(self::getClient($access[ $_GET[ 'workspace' ] ])->createAuthUrl());
            exit;

        } else if (isset($_GET[ 'redirect_to' ])) {

            setcookie('wpgwsso_redirect_to', $_GET[ 'redirect_to' ], time() + 120, COOKIEPATH, COOKIE_DOMAIN, true);

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
     * @return \WP_User|null
     */
    public static function authenticate($user = null)
    {
        if (
            isset($_GET[ 'code' ])
            && isset($_COOKIE[ 'workspace' ])
            && ! empty($access = Settings::getAccess()[ $_COOKIE[ 'workspace' ] ])
        ) {

            $client = self::getClient($access);
            $result = $client->fetchAccessTokenWithAuthCode($_GET[ 'code' ]);
            $user   = null;

            if (isset($result[ 'id_token' ])) {

                $oauth2   = new GoogleServiceOauth2($client);
                $userInfo = $oauth2->userinfo->get();

                if (property_exists($userInfo, 'email')) {

                    $user = get_user_by('email', $userInfo->email);

                }

            }

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
        $client->setRedirectUri(wp_login_url());

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
                html, body {
                    width: 100%;
                    height: 100%;
                }
                body {
                    font-family: Verdana, Arial, Helvetica, sans-serif;
                    background: #fff;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    overflow: hidden;
                }
                form > div {
                    text-align: center;
                    margin: 0 auto;
                    width: 100%;
                    padding: 1rem 0;
                }
                select {
                    padding: 0.3rem;
                    cursor: pointer;
                    outline: none;
                    margin-top: 1px;
                }
                button {
                    background-color: #009;
                    padding: 5px 12px;
                    color: #fff;
                    cursor: pointer;
                    font-weight: bold;
                }
            </style>
        </head>
        <body>
        <form>
            <div><?=get_custom_logo(get_current_blog_id())?></div>
            <div>
                <label for="#workspace">user @</label>
                <select id="workspace" name="workspace">
                    <option value="" disabled <?=empty($_COOKIE[ 'workspace' ]) ? '' : 'selected'?>>
                        Select one
                    </option>
                <?php foreach ($access as $brand => $data) {

                    $selected = ! empty($_COOKIE[ 'workspace' ]) && $_COOKIE[ 'workspace' ] === $brand ? 'selected' : '';

                    echo "<option value=\"{$brand}\" {$selected}>{$brand}</option>";

                } ?>
                </select>
                <button type="submit">Log in with Google</button>
            </div>
        </form>
        </body>
        </html>
        <?php
    }

}
