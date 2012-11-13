<?php

namespace Game\Tests;

use Game\Tests\GameWebTestCase;

class UserRegistrationTest extends GameWebTestCase
{

    public function testUserRegistrationValidation()
    {
        /** @var \Symfony\Component\HttpKernel\Client $client */
        list($client, $csrfToken) = parent::getClientWithToken();

        $client->request('POST', '/user/create', [], [], [
            'HTTP_X-GameApp-CSRF-Token' => $csrfToken
        ]);

        /**
         * We expect: {"emailAddress":["This value should not be blank."],
         * "userName":["This value should not be blank."],
         * "password":["This value should not be blank."],
         * "passwordConfirmationMatching":["This does not match the password"]}
         */

        $errors = json_decode($client->getResponse()->getContent());

        $this->assertObjectHasAttribute('emailAddress', $errors);
        $this->assertObjectHasAttribute('userName', $errors);
        $this->assertObjectHasAttribute('password', $errors);
        $this->assertEquals('This value should not be blank.', $errors->emailAddress[0]);
        $this->assertEquals('This value should not be blank.', $errors->userName[0]);
        $this->assertEquals('This value should not be blank.', $errors->password[0]);

        $this->assertEquals(0, $this->app['mailer.logger']->countMessages(), "No emails should have been sent");
    }

    public function testUserRegistration()
    {
        /** @var \Symfony\Component\HttpKernel\Client $client */
        list($client, $csrfToken) = parent::getClientWithToken();

        $randomUsername = substr(dechex(mt_rand(0, PHP_INT_MAX)), 0, 8);
        $client->request('POST', '/user/create', [
            'emailAddress'         => $randomUsername.'@example.com',
            'userName'             => $randomUsername.'name',
            'password'             => '123abc456$%^',
            'passwordConfirmation' => '123abc456$%^'
        ], [], [
            'HTTP_X-GameApp-CSRF-Token' => $csrfToken
        ]);
        $this->assertTrue($client->getResponse()->isOk());
        $this->assertEquals(1, $this->app['mailer.logger']->countMessages(), "Only one email sent");

        // TODO: test that the email is sent and that we can then login
    }
}