<?php

namespace Diside\BehatExtension\Context;

use Behat\Gherkin\Node\TableNode;
use PHPUnit_Framework_Assert as phpunit;
use Swift_Mime_Message;

trait EmailContextTrait
{
    /**
     * @Then /^I should get emails on:$/
     */
    public function iShouldGetEmailsOn(TableNode $table)
    {
        $index = 0;
        $values = $table->getHash();

        $this->get('app.swift_mailer_proxy')
            ->shouldHaveReceived('send')
            ->with(\Mockery::on(
                function (Swift_Mime_Message $message) use (&$index, &$values) {
                    if (isset($values[$index]['email']) && !empty($values[$index]['email'])) {
                        $expectedEmail = $values[$index]['email'];
                        $emails = $message->getTo();
                        phpunit::assertArrayHasKey($expectedEmail, $emails, sprintf(
                            'Email "%s" not found in list ["%s"]',
                            $expectedEmail, implode(', ', array_keys($emails))
                        ));
                    } else {
                        phpunit::fail('Expected emails are less than the sent ones');
                    }

                    if (isset($values[$index]['subject']) && !empty($values[$index]['subject'])) {
                        $expectedSubject = $values[$index]['subject'];
                        $subject = $message->getSubject();
                        phpunit::assertContains($expectedSubject, $subject, sprintf(
                            'Subject "%s" not found in "%s"',
                            $expectedSubject, $subject
                        ));
                    }

                    if (isset($values[$index]['body']) && !empty($values[$index]['body'])) {
                        $expectedBody = $values[$index]['body'];
                        $body = $message->getBody();
                        phpunit::assertContains($expectedBody, $body, sprintf(
                            'Content "%s" not found in "%s"',
                            $expectedBody, $body
                        ));
                    }

                    $index += 1;

                    return true;
                }));

        $this->get('app.swift_mailer_proxy')
            ->shouldHaveReceived('send')
            ->times(count($values));
    }

    /**
     * @Then /^I should get no emails$/
     */
    public function iShouldGetNoEmails()
    {
        $this->get('app.swift_mailer_proxy')
            ->shouldNotHaveReceived('send');
    }
}