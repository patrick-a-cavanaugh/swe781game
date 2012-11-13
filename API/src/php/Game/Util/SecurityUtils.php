<?php

namespace Game\Util;

class SecurityUtils {

    /**
     * Constant-time comparison of two strings of the same length to see if they are identical. Taken from the
     * BcryptPasswordEncoder and made available here for use in other parts of the application (e.g. comparing secure
     * API and CSRF tokens).
     *
     * @param $string1
     * @param $string2
     * @return bool if the strings are the same
     */
    public static function safeCompareStrings($string1, $string2) {
        if (strlen($string1) !== strlen($string2)) {
            return false;
        }

        $result = 0;
        for ($i = 0; $i < strlen($string1); $i++) {
            $result |= ord($string1[$i]) ^ ord($string2[$i]);
        }

        return 0 === $result;
    }

    /**
     * Get a cryptographically secure random number using OpenSSL.
     *
     * @param $min int the inclusive lower bound
     * @param $max int the inclusive upper bound
     * @return int
     */
    public static function secureRandom($min, $max) {
        // This function thanks to
        // http://docs.php.net/manual/en/function.openssl-random-pseudo-bytes.php comment by
        // christophe dot weis at statec dot etat dot lu
        // and similar to
        // http://stackoverflow.com/questions/1313223/replace-rand-with-openssl-random-pseudo-bytes
        $range = ($max + 1) - $min;
        if ($range == 0) return $min; // not so random...
        $log = log($range, 2);
        $bytes = (int)($log / 8) + 1; // length in bytes
        $bits = (int)$log + 1; // length in bits
        $filter = (int)(1 << $bits) - 1; // set all lower bits to 1
        do {
            $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes, $s)));
            $rnd = $rnd & $filter; // discard irrelevant bits
        } while ($rnd >= $range);
        return $min + $rnd;
    }
}