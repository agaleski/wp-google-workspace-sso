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
 */

namespace AGaleski\WordPress\GoogleWorkspaceSso;

use \Google_Client as GoogleClient;
use \Google_Service_Oauth2 as GoogleServiceOauth2;

/**
 * Class Login
 *
 * @package AGaleski\WordPress\GoogleWorkspaceSso
 * @version 1.0.0
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
    public static function run(): void
    {
        if ((isset($_GET[ 'action' ]) && $_GET[ 'action' ] === 'confirm_admin_email')) {
            return;
        }

        $access = Settings::getAccess();

        if (! empty($_GET[ 'workspace' ]) && ! empty($access[ $_GET[ 'workspace' ] ])) {
            setcookie('workspace', $_GET[ 'workspace' ], time() + 86400, COOKIEPATH, COOKIE_DOMAIN, true);
            wp_redirect(self::getClient($access[ $_GET[ 'workspace' ] ])->createAuthUrl());
            exit;
        } elseif (isset($_GET[ 'redirect_to' ])) {
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
    public static function authenticate(?\WP_User $user = null): ?\WP_User
    {
        if (empty($_GET[ 'code' ]) || empty($_COOKIE[ 'workspace' ])) {
            return $user;
        }

        $access = Settings::getAccess()[ $_COOKIE[ 'workspace' ] ];

        if (empty($access)) {
            return $user;
        }

        $client = self::getClient($access);
        $result = $client->fetchAccessTokenWithAuthCode($_GET[ 'code' ]);
        $user   = null;

        if (isset($result[ 'id_token' ]) && $client->verifyIdToken($result[ 'id_token' ])) {
            $oauth2   = new GoogleServiceOauth2($client);
            $userInfo = $oauth2->userinfo->get();

            if ($userInfo->getVerifiedEmail()) {
                $user = get_user_by('email', $userInfo->getEmail());
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
    public static function handleWoocommerce(array $creds = []): array
    {
        if (class_exists('WooCommerce') && ! empty($creds[ 'user_login' ])) {
            $userLogin = esc_attr($creds[ 'user_login' ]);

            if (strpos($userLogin, '@')) {
                $user = get_user_by('email', $userLogin);
            } else {
                $user = get_user_by('login', $userLogin);
            }

            if (
                ! empty($user)
                && property_exists($user, 'roles')
                && $user->roles[ 0 ] !== 'customer'
            ) {
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
    private static function getClient(array $access = [ 'id' => '', 'secret' => '' ]): GoogleClient
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
    private static function render(array $access = []): void
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
