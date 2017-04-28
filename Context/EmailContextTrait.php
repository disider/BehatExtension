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
                    if (isset($values[$index]['to']) && !empty($values[$index]['to'])) {
                        $expectedTo = $this->replacePlaceholders($values[$index]['to']);
                        $to = $message->getTo();
                        phpunit::assertArrayHasKey($expectedTo, $to, sprintf(
                            'Email "%s" not found in list ["%s"]',
                            $expectedTo, implode(', ', array_keys($to))
                        ));
                    } else {
                        phpunit::fail('Expected emails are less than the sent ones');
                    }

                    if (isset($values[$index]['cc']) && !empty($values[$index]['cc'])) {
                        $expectedCc = $this->replacePlaceholders($values[$index]['cc']);
                        $cc = $message->getCc();
                        phpunit::assertArrayHasKey($expectedCc, $cc, sprintf(
                            'CC "%s" not found in list ["%s"]',
                            $expectedCc, implode(', ', array_keys($cc))
                        ));
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