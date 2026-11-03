<?php

namespace App\Support;

use Illuminate\Support\Facades\Hash;

/**
 * Verifies password hashes produced by WordPress so imported users can log in,
 * after which the caller should re-hash the password with Laravel's bcrypt.
 */
class WordPressPassword
{
    private const ITOA64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public static function isBcrypt(string $hash): bool
    {
        return preg_match('/^\$2[abxy]\$\d{2}\$.{53}$/', $hash) === 1;
    }

    public static function check(string $password, string $hash): bool
    {
        if ($hash === '') {
            return false;
        }

        // WordPress 6.8+: "$wp" prefix + bcrypt of base64(hmac-sha384(password, 'wp-sha384')).
        if (str_starts_with($hash, '$wp$2')) {
            $pre = base64_encode(hash_hmac('sha384', $password, 'wp-sha384', true));

            return password_verify($pre, substr($hash, 3));
        }

        if (self::isBcrypt($hash)) {
            return password_verify($password, $hash);
        }

        // Classic phpass portable hashes.
        if (str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$')) {
            return hash_equals($hash, self::phpass($password, $hash));
        }

        // Very old WordPress installs stored a plain MD5.
        if (strlen($hash) === 32 && ctype_xdigit($hash)) {
            return hash_equals(strtolower($hash), md5($password));
        }

        return false;
    }

    /** True when the stored hash should be replaced by a Laravel bcrypt hash. */
    public static function needsRehash(string $hash): bool
    {
        return ! self::isBcrypt($hash) || Hash::needsRehash($hash);
    }

    private static function phpass(string $password, string $setting): string
    {
        $fail = '*0';
        if (str_starts_with($setting, $fail)) {
            $fail = '*1';
        }

        $id = substr($setting, 0, 3);
        if ($id !== '$P$' && $id !== '$H$') {
            return $fail;
        }

        $countLog2 = strpos(self::ITOA64, $setting[3]);
        if ($countLog2 === false || $countLog2 < 7 || $countLog2 > 30) {
            return $fail;
        }
        $count = 1 << $countLog2;

        $salt = substr($setting, 4, 8);
        if (strlen($salt) !== 8) {
            return $fail;
        }

        $hash = md5($salt.$password, true);
        do {
            $hash = md5($hash.$password, true);
        } while (--$count);

        return substr($setting, 0, 12).self::encode64($hash, 16);
    }

    private static function encode64(string $input, int $count): string
    {
        $output = '';
        $i = 0;
        do {
            $value = ord($input[$i++]);
            $output .= self::ITOA64[$value & 0x3f];
            if ($i < $count) {
                $value |= ord($input[$i]) << 8;
            }
            $output .= self::ITOA64[($value >> 6) & 0x3f];
            if ($i++ >= $count) {
                break;
            }
            if ($i < $count) {
                $value |= ord($input[$i]) << 16;
            }
            $output .= self::ITOA64[($value >> 12) & 0x3f];
            if ($i++ >= $count) {
                break;
            }
            $output .= self::ITOA64[($value >> 18) & 0x3f];
        } while ($i < $count);

        return $output;
    }
}
