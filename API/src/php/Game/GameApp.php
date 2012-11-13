<?php

namespace Game;

use Silex\Application;
use Silex\Application\FormTrait;
use Silex\Application\MonologTrait;
use Silex\Application\SecurityTrait;
use Silex\Application\TwigTrait;
use Silex\Application\SwiftmailerTrait;
use Silex\Application\UrlGeneratorTrait;

class GameApp extends Application
{
    use FormTrait;
    use MonologTrait;
    use SecurityTrait {
        user as traitUser;
    }
    use TwigTrait;
    use SwiftmailerTrait;
    use UrlGeneratorTrait;

    public function csrfToken($name) {
        $token = $this->session()->get($name)
                 ?: $this->csrfProvider()->generateCsrfToken($name);
        return $token;
    }

    /**
     * @return \Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface
     */
    public function csrfProvider() {
        return $this['form.csrf_provider'];
    }

    /**
     * @return \Doctrine\DBAL\Connection
     */
    public function db() {
        return $this['db'];
    }

    /**
     * @return \Game\Util\User
     */
    public function user()
    {
        return self::traitUser();
    }


    /**
     * @return \Symfony\Component\HttpFoundation\Session\Session
     */
    public function session()
    {
        return $this['session'];
    }


    /**
     * @return \Symfony\Component\Validator\Validator
     */
    public function validator() {
        return $this['validator'];
    }
}