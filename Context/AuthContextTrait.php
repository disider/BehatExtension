<?php

namespace Diside\BehatExtension\Context;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Mink\Session;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

trait AuthContextTrait
{

    /**
     * @Given /^I am anonymous$/
     */
    public function iAmAnonymous()
    {
    }

    /**
     * @Given /^I am logged as "([^"]*)"$/
     */
    public function iAmLoggedAsEmail($email)
    {
        /** @var Session $session */
        $session = $this->getSession();
        $driver = $session->getDriver();

        if (!($driver instanceof BrowserKitDriver)) {
            throw new UnsupportedDriverActionException('This step is only supported by the BrowserKitDriver', $driver);
        }
        $client = $driver->getClient();
        $client->getCookieJar()->set(new Cookie(session_name(), true));

        $session = $client->getContainer()->get('session');

        $userProviderId = $this->getParameter('user_provider');
        $user = $this->kernel->getContainer()->get($userProviderId)->loadUserByUsername($email);
        $providerKey = $this->getParameter('firewall_name');

        $token = new UsernamePasswordToken($user, null, $providerKey, $user->getRoles());
        $session->set('_security_' . $providerKey, serialize($token));
        $session->save();

        $cookie = new Cookie($session->getName(), $session->getId());
        $client->getCookieJar()->set($cookie);
    }
}