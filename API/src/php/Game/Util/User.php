<?php

namespace Game\Util;

use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface {

    private $roles = [];
    private $password;
    private $salt = null;
    private $username;

    /*
     * These properties are not needed for the UserInterface but we want them anyway.
     */
    private $id;
    private $emailAddress;
    private $dateRegistered;
    private $emailConfirmed;

    /**
     * Required options are: ['roles', 'password', 'username', 'id', 'emailAddress', 'dateRegistered', 'emailConfirmed']
     *
     * @param $options
     */
    function __construct($options)
    {
        $properties = ['roles', 'password', 'username', 'id', 'emailAddress', 'dateRegistered', 'emailConfirmed'];
        foreach($properties as $propertyName) {
            if (!isset($options[$propertyName])) {
                trigger_error('Missing option ' . $propertyName, E_USER_ERROR);
            }
            $this->$propertyName = $options[$propertyName];
        }
    }

    /**
     * @return \DateTime
     */
    public function getDateRegistered()
    {
        return $this->dateRegistered;
    }

    public function getEmailAddress()
    {
        return $this->emailAddress;
    }

    public function getEmailConfirmed()
    {
        return $this->emailConfirmed;
    }

    public function getId()
    {
        return intval($this->id);
    }

    /**
     * Returns the roles granted to the user.
     *
     * <code>
     * public function getRoles()
     * {
     *     return array('ROLE_USER');
     * }
     * </code>
     *
     * Alternatively, the roles might be stored on a ``roles`` property,
     * and populated in any number of different ways when the user object
     * is created.
     *
     * @return \Symfony\Component\Security\Core\Role\Role[] The user roles
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * Returns the password used to authenticate the user.
     *
     * This should be the encoded password. On authentication, a plain-text
     * password will be salted, encoded, and then compared to this value.
     *
     * @return string The password
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Returns the salt that was originally used to encode the password.
     *
     * This can return null if the password was not encoded using a salt.
     *
     * @return string The salt
     */
    public function getSalt()
    {
        return $this->salt;
    }

    /**
     * Returns the username used to authenticate the user.
     *
     * @return string The username
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Removes sensitive data from the user.
     *
     * This is important if, at any given point, sensitive information like
     * the plain-text password is stored on this object.
     *
     * @return void
     */
    public function eraseCredentials()
    {
        $this->salt = null;
        $this->password = null;
    }
}
