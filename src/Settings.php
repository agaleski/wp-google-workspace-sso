<?php

namespace AGaleski\WordPress\GoogleWorkspaceSso;

/**
 * Class Settings
 *
 * @package AGaleski\WordPress\GoogleWorkspaceSso
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
    public static function isActive() : bool
    {
        return self::get()[ 'active' ];
    }

    /**
     * Exposes the Google Oauth access data array.
     *
     * @return array
     */
    public static function getAccess() : array
    {
        return self::get()[ 'access' ];
    }

    /**
     * Provides the settings array and loads it from the options table if necessary.
     *
     * @return array
     */
    private static function get() : array
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
    public static function set(array $settings = []) : bool
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
    public static function decrypt(string $string = '') : string
    {
        $string   = base64_decode($string);
        $hash     = substr($string, 0, 64);
        $content  = substr($string, 64);
        $ivLength = openssl_cipher_iv_length(self::CIPHER_ALGO);

        if (! hash_equals($hash, hash_hmac(self::HASH_ALGO, $content, self::getHashKey(), true))) {

            return '';
        }

        return '' . openssl_decrypt(
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
    public static function encrypt(string $string = '') : string
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
    private static function getPassphrase() : string
    {
        if (empty(self::get()[ 'passphrase' ])) {

            $ivLength                       = openssl_cipher_iv_length(self::CIPHER_ALGO);
            self::$settings[ 'passphrase' ] = base64_encode(openssl_random_pseudo_bytes($ivLength));

            update_network_option(get_current_network_id(), self::OPTION, self::$settings);

        }

        return base64_decode(self::$settings[ 'passphrase' ]);
    }

    /**
     * Provides a cryptographical key for hmac - generates it at first use if needed.
     *
     * @return string
     */
    private static function getHashKey() : string
    {
        if (empty(self::get()[ 'hashKey' ])) {

            self::$settings[ 'hashKey' ] = base64_encode(openssl_random_pseudo_bytes(64));

            update_network_option(get_current_network_id(), self::OPTION, self::$settings);

        }

        return base64_decode(self::$settings[ 'hashKey' ]);
    }

}
