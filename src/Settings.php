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

/**
 * Class Settings
 *
 * @package AGaleski\WordPress\GoogleWorkspaceSso
 * @version 1.0.0
 */
class Settings
{
    public const  OPTION      = WPGWSSO_PREFIX . 'settings';
    private const CIPHER_ALGO = 'aes-256-cbc';
    private const HASH_ALGO   = 'sha3-512';

    private static array $settings = [
        'active'     => false,
        'passphrase' => '',
        'hashKey'    => '',
        'access'     => [
            /**
            'example.com' => [
                'name'   => 'example.com',
                'id'     => '',
                'secret' => '',
            ],
            */
        ],
    ];

    /**
     * Exposes the activation status of this feature.
     *
     * @return bool
     */
    public static function isActive(): bool
    {
        return (bool) self::get()[ 'active' ];
    }

    /**
     * Exposes the Google Oauth access data array.
     *
     * @return array
     */
    public static function getAccess(): array
    {
        return self::get()[ 'access' ];
    }

    /**
     * Provides the settings array and loads it from the options table if necessary.
     *
     * @return array
     */
    private static function get(): array
    {
        if (empty(self::$settings[ 'loaded' ])) {
            $settings                   = get_network_option(get_current_network_id(), self::OPTION, []);
            self::$settings             = array_replace(self::$settings, $settings);
            self::$settings[ 'loaded' ] = true;
        }

        return self::$settings;
    }

    /**
     * Saves values of given $settings array if key is known.
     *
     * @param array $settings
     *
     * @return bool
     */
    public static function set(array $settings = []): bool
    {
        self::$settings = array_replace(self::$settings, $settings);

        return update_network_option(get_current_network_id(), self::OPTION, self::$settings);
    }

    /**
     * Returns the provided string, decrypted.
     *
     * @param string $string
     *
     * @return string
     */
    public static function decrypt(string $string = ''): string
    {
        $string   = base64_decode($string);
        $hash     = substr($string, 0, 64);
        $content  = substr($string, 64);
        $ivLength = openssl_cipher_iv_length(self::CIPHER_ALGO);

        if (! hash_equals($hash, hash_hmac(self::HASH_ALGO, $content, self::getHashKey(), true))) {
            return '';
        }

        return (string) openssl_decrypt(
            substr($content, $ivLength),
            self::CIPHER_ALGO,
            self::getPassphrase(),
            OPENSSL_RAW_DATA,
            substr($content, 0, $ivLength)
        );
    }

    /**
     * Returns the provided string, encrypted.
     *
     * @param string $string
     *
     * @return string
     */
    public static function encrypt(string $string = ''): string
    {
        $iv      = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER_ALGO));
        $message = openssl_encrypt($string, self::CIPHER_ALGO, self::getPassphrase(), OPENSSL_RAW_DATA, $iv);
        $hash    = hash_hmac(self::HASH_ALGO, $iv . $message, self::getHashKey(), true);

        return base64_encode($hash . $iv . $message);
    }

    /**
     * Provides a cryptographical passphrase for openssl - generates it at first use if needed.
     *
     * @return string
     */
    private static function getPassphrase(): string
    {
        if (empty(self::get()[ 'passphrase' ])) {
            $ivLength                       = openssl_cipher_iv_length(self::CIPHER_ALGO);
            self::$settings[ 'passphrase' ] = base64_encode(openssl_random_pseudo_bytes($ivLength));

            update_network_option(get_current_network_id(), self::OPTION, self::$settings);
        }

        return (string) base64_decode(self::$settings[ 'passphrase' ]);
    }

    /**
     * Provides a cryptographical key for hmac - generates it at first use if needed.
     *
     * @return string
     */
    private static function getHashKey(): string
    {
        if (empty(self::get()[ 'hashKey' ])) {
            self::$settings[ 'hashKey' ] = base64_encode(openssl_random_pseudo_bytes(64));

            update_network_option(get_current_network_id(), self::OPTION, self::$settings);
        }

        return (string) base64_decode(self::$settings[ 'hashKey' ]);
    }
}
