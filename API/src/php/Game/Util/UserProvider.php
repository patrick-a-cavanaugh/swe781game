<?php
namespace Game\Util;

use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Doctrine\DBAL\Connection;

/**
 * Based on the sample provider from http://silex.sensiolabs.org/doc/providers/security.html
 *
 * Also requires email confirmation before the user is considered valid.
 */
class UserProvider implements UserProviderInterface
{
    private $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public function loadUserByUsername($username)
    {
        /** @var $stmt \Doctrine\DBAL\Driver\Statement */
        $stmt = $this->conn->executeQuery('SELECT * FROM `user` WHERE username = ?', array(strtolower($username)));

        if (!$user = $stmt->fetch()) {
            throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $username));
        }
        if (!$user['email_confirmed']) {
            throw new UnsupportedUserException(sprintf('Username "%s" does not yet have a confirmed email address.',
                $username));
        }

        $roles = [];
        return new User([
            'username' => $user['username'],
            'password' => $user['password_hash'],
            'roles' => $roles,
            'id' => $user['id'],
            'emailAddress' => $user['email_address'],
            'dateRegistered' => date_create_from_format('Y-m-d H:i:s', $user['date_registered']),
            'emailConfirmed' => !!$user['email_confirmed']
        ]);
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    public function supportsClass($class)
    {
        return $class === 'Symfony\Component\Security\Core\User\User';
    }
}
