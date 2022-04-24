<?php

namespace AGaleski\WordPress\GoogleWorkspaceSso;

/**
 * Class Admin
 *
 * @package AGaleski\WordPress\GoogleWorkspaceSso
 */
class Admin
{

    public const ACTION = WPGWSSO_PREFIX . 'save_ajax_action';

    private const NONCE = WPGWSSO_PREFIX . 'save_ajax_nonce';

    /**
     * Redirects a user to the appropriate target after successful Google Workspace SSO login.
     *
     * @return void
     */
    public static function redirectTo()
    {
        if (isset($_COOKIE[ 'wpgwsso_redirect_to' ])) {

            setcookie('wpgwsso_redirect_to', $_GET[ 'redirect_to' ], time() - 120, COOKIEPATH, COOKIE_DOMAIN, true);
            wp_safe_redirect($_COOKIE[ 'redirect_to' ]);
            exit;

        }
    }

    /**
     * Adds the WP-Admin settings page to the "Users" menu.
     *
     * @param string $context
     *
     * @return void
     */
    public static function add(string $context = '')
    {
        add_submenu_page(
            'users.php',
            'WordPress Google Workspace SSO',
            'Google SSO',
            'administrator',
            'wp-google-workspace-sso',
            [ __CLASS__, 'render' ],
            99
        );
    }

    /**
     * Handles AJAX requests
     *
     * @uses Settings::OPTION, Settings::set()
     *
     * @return void
     */
    public static function save()
    {
        check_ajax_referer(self::ACTION, self::NONCE);

        if (empty($_POST[ Settings::OPTION ])) {

            wp_send_json_error([ 'error' => 'Bad Request' ], 400);

        }

        $settings             = $_POST[ Settings::OPTION ];
        $settings[ 'active' ] = isset($settings[ 'active' ]);

        if (! empty($settings[ 'access' ])) {

            foreach ($settings[ 'access' ] as $domain => $data) {

                $domain                                      = esc_attr($domain);
                $settings[ 'access' ][ $domain ][ 'name' ]   = esc_attr($data[ 'name' ]);
                $settings[ 'access' ][ $domain ][ 'id' ]     = Settings::encrypt(esc_attr($data[ 'id' ]));
                $settings[ 'access' ][ $domain ][ 'secret' ] = Settings::encrypt(esc_attr($data[ 'secret' ]));

            }

        }

        Settings::set($settings);
        wp_send_json_success([], 202);
    }

    /**
     * Renders the wp-admin WordPress Google Workspace SSO settings page.
     *
     * @uses Settings::OPTION
     *
     * @return void
     */
    public static function render()
    {
        ?>
        <link rel="stylesheet" type="text/css" href="<?=WPGWSSO_URL?>assets/css/admin.css?v<?=time()?>">
        <style>
            #ag_settings_container table {
                margin: 0;
                border-bottom: 1px solid #000;
            }
            #ag_settings_container table:last-child {
                border-bottom: 0;
            }
            #ag_settings_container .button-warning {
                width: 100%;
                line-height: 7rem;
            }
        </style>
        <form class="ag-admin-form">
            <?=wp_nonce_field(self::ACTION, self::NONCE, true, false)?>
            <input type="hidden" name="action" value="<?=self::ACTION?>">
            <h1><span>&#128272;</span> WordPress ⓖoogle Workspace SSO</h1>
            <div>
                <h2>Login screen replacement status:</h2>
                <div>
                    <label class="switch">
                        <input type="checkbox"
                               id="<?=Settings::OPTION?>[active]"
                               name="<?=Settings::OPTION?>[active]"
                               <?=Settings::isActive() ? 'checked="checked"' : ''?>
                        />
                        <span class="slider"></span>
                    </label>
                    <span>( Replaces default wp-admin login with Google SSO if activated. )</span>
                </div>
            </div>
            <div>
                <h2>Add new workspace domain:</h2>
                <div>
                    <label for="sso_new_domain">@</label>
                    <input type="text" id="sso_new_domain" name="sso_new_domain" class="half" placeholder="example.com">
                    <button type="button" id="sso_add_new_domain" class="button-secondary">Add workspace domain</button>
                </div>
            </div>
            <div>
                <h2>Workspace domain settings:</h2>
                <div id="ag_settings_container">
                <?php foreach (Settings::getAccess() as $domain => $data) {
                    echo self::getSettingHtml($domain);
                } ?>
                </div>
            </div>
            <button type="submit" class="button-primary">Save settings</button>
        </form>
        <script src="<?=WPGWSSO_URL?>assets/js/admin.js?ver=v<?=time()?>"></script>
        <script>
            (function($) {
                $('#sso_add_new_domain').click(function () {
                    let container = $('#ag_settings_container'),
                        input     = $('#sso_new_domain'),
                        domain    = input.val(),
                        html      = '<?=str_replace(PHP_EOL, '', self::getSettingHtml())?>'
                    ;

                    if (!domain) {
                        alert('Enter a domain name before adding new credentials.');
                        return;
                    }

                    container.prepend(html.replace(/%%DOMAIN_NAME%%/g, domain));
                    input.val('');
                });
            })(jQuery);

            function wpgwsso_remove_domain(elem) {
                jQuery(elem).closest('table').remove();
            }
        </script>
        <?php
        echo file_get_contents(WPGWSSO_PATH . 'readme.html');
    }

    /**
     * Provides the settings html table for a workspace domain.
     *
     * @param string $domain
     *
     * @uses Settings::OPTION, Settings::getAccess()
     *
     * @return string
     */
    private static function getSettingHtml(string $domain = '%%DOMAIN_NAME%%') : string
    {
        $option = Settings::OPTION;
        $id     = '';
        $secret = '';

        /**
         * @todo Hook description!
         */
        $extension = apply_filters('wpgwsso_settings_html_extension', '', $domain, $option);

        if ($domain !== '%%DOMAIN_NAME%%') {

            $access = Settings::getAccess();
            $id     = $access[ $domain ][ 'id' ] ? Settings::decrypt($access[ $domain ][ 'id' ]) : '';
            $secret = $access[ $domain ][ 'secret' ] ? Settings::decrypt($access[ $domain ][ 'secret' ]) : '';

        }

        return "
            <table class=\"ag-form-table\" role=\"presentation\">
                <tr>
                    <th><label for=\"{$option}[access][{$domain}][name]\">Domain name</label></th>
                    <td>
                        <strong>@&nbsp;</strong>
                        <input type=\"text\" class=\"half disabled\"
                               id=\"{$option}[access][{$domain}][name]\"
                               name=\"{$option}[access][{$domain}][name]\"
                               value=\"{$domain}\"
                        />
                    </td>
                    <td rowspan=\"3\">
                        <button type=\"button\" class=\"button button-warning\" onclick=\"wpgwsso_remove_domain(this)\">
                            DELETE
                        </button>
                    </td>
                </tr>
                <tr>
                    <th><label for=\"{$option}[access][{$domain}][id]\">Google Client ID</label></th>
                    <td>
                        <input type=\"text\" class=\"full\" required
                               id=\"{$option}[access][{$domain}][id]\"
                               name=\"{$option}[access][{$domain}][id]\"
                               value=\"{$id}\"
                        />
                    </td>
                </tr>
                <tr>
                    <th><label for=\"{$option}[access][{$domain}][secret]\">Google Client Secret</label></th>
                    <td>
                        <input type=\"text\" class=\"full\" required
                               id=\"{$option}[access][{$domain}][secret]\"
                               name=\"{$option}[access][{$domain}][secret]\"
                               value=\"{$secret}\"
                        />
                    </td>
                </tr>
                {$extension}
            </table>
        ";
    }

}
