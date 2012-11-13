<?php

namespace Game\Util;

use Symfony\Component\Security\Core\Encoder\BasePasswordEncoder;

/**
 * Uses the BCrypt hashing algorithm to protect passwords. Compares password hashes using a constant-time
 * algorithm to protect against timing attacks.
 */
class BcryptPasswordEncoder extends BasePasswordEncoder {

    private $bCrypt;

    /**
     * @param int $rounds number of BCrypt rounds to use
     */
    function __construct($rounds = 12)
    {
        $this->bCrypt = new Bcrypt($rounds);
    }

    /**
     * Encodes the raw password.
     *
     * @param string $raw  The password to encode
     * @param string $salt The salt (ignored)
     *
     * @return string The encoded password
     */
    public function encodePassword($raw, $salt)
    {
        return $this->bCrypt->hash($raw);
    }

    /**
     * Checks a raw password against an encoded password.
     *
     * @param string $encoded An encoded password in bcrypt format including the salt and rounds parameters.
     * @param string $raw     A raw password
     * @param string $salt    The salt (ignored)
     *
     * @return Boolean true if the password is valid, false otherwise
     */
    public function isPasswordValid($encoded, $raw, $salt)
    {
        return $this->comparePasswords($encoded, crypt($raw, $encoded));
    }
}
