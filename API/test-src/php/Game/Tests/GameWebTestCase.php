<?php

namespace Game\Tests;

use Silex\WebTestCase;
use Game\Util\MessageLogger;

/**
 * Superclass that knows how to bootstrap the application for integration tests.
 */
class GameWebTestCase extends WebTestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->app['session.test'] = true;
    }

    public function createApplication()
    {
        if (!defined('WEB_TEST_CASE')) {
            define('WEB_TEST_CASE', true);
            define('ENV', 'test');
        }
        $app = require __DIR__.'/../../../../bootstrap.php';
        // Thanks to https://gist.github.com/1117957 for this code for testing SwiftMailer
        $app["swiftmailer.transport"] =
            new \Swift_Transport_NullTransport($app['swiftmailer.transport.eventdispatcher']);
        $app['mailer.logger'] = new MessageLogger();
        $app['mailer']->registerPlugin($app['mailer.logger']);
        return $app;
    }

    protected function getClientWithToken()
    {
        $client = $this->createClient();
        $client->followRedirects(true);
        // Get the CSRF token first
        $crawler = $client->request('GET', '/');

        return [
            $client,
            $crawler->filter('input[name="_csrf_token"]')->attr('value')
        ];
    }
}