<?php

namespace Diside\BehatExtension\Context;

use Behat\Behat\Event\BaseScenarioEvent;
use Behat\Behat\Event\StepEvent;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\MinkExtension\Context\MinkContext;
use Behat\Symfony2Extension\Context\KernelAwareInterface;
use Doctrine\Common\Proxy\Exception\InvalidArgumentException;
use PHPUnit_Framework_Assert as a;
use PSS\Behat\Symfony2MockerExtension\Context\ServiceMockerAwareInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\DomCrawler\Crawler;

abstract class AbstractContext extends MinkContext implements KernelAwareInterface, ServiceMockerAwareInterface
{
    use ContextTrait;

    /**
     * @var ConsoleOutput
     */
    protected $output;

    public function __construct($parameters)
    {
        $this->debug = isset($parameters['debug']) ? $parameters['debug'] : true;
    }

    /**
     * @AfterScenario
     */
    public function printLastResponseOnError(BaseScenarioEvent $scenarioEvent)
    {
        if ($scenarioEvent->getResult() == StepEvent::FAILED) {
            $body = $this->getSession()->getPage()->getContent();

            // could we even ask them if they want to print out the error?
            // or do it based on verbosity

            // print some debug details
            $this->printDebug('<error>Failure!</error> when making the following request:');
            $this->printDebug(
                sprintf("    <info>%s</info>", $this->getSession()->getCurrentUrl())
            );

            // the response is HTML - see if we should print all of it or some of it
            $isValidHtml = strpos($body, '</body>') !== false;

            if ($isValidHtml) {
                $this->printDebug(
                    '    Below is a summary of the HTML response from the server.'
                );

                // finds the h1 and h2 tags and prints them only
                $crawler = new Crawler($body);
                foreach ($crawler->filter('h1, h2')->extract(array('_text')) as $header) {
                    $this->printDebug(sprintf('        ' . $header));
                }
            } else {
                $this->printDebug($body);
            }
        }
    }


    /**
     * @When /^I visit "([^"]*)"$/
     */
    public function iVisit($page)
    {
        $page = $this->replacePlaceholders($page);
        $this->visit($page);

//        if($this->getSession()->getStatusCode() != 200)
//            $this->printLastResponse();

        $this->assertResponseStatus(200);
    }

    /**
     * @When /^I debug "([^"]*)"$/
     */
    public function iDebug($page)
    {
        $this->visit($page);

        $this->printLastResponse();
    }

    /**
     * @When /^I try to visit "([^"]*)"$/
     */
    public function iTryToVisit($page)
    {
        $page = $this->replacePlaceholders($page);

        $this->visit($page);
        $this->assertResponseStatusIsNot(200);
    }

    public function assertPageAddress($page)
    {
        $page = $this->replacePlaceholders($page);

        $this->assertSession()->addressEquals($this->locatePath($page));
    }

    /**
     * @Then /^I should see an? "([^"]*)" error$/
     */
    public function iSeeError($error)
    {
        $this->assertElementContainsText('.has-error .help-block', $error);
    }

    /**
     * @Then /^I should see an? "([^"]*)" error with class "([^"]*)"$/
     */
    public function iSeeErrorWithClass($error, $class)
    {
        $this->assertElementContainsText($class, $error);
    }

    /**
     * @Then /^I should see a "([^"]*)" global error$/
     */
    public function iSeeGlobalError($error)
    {
        $this->assertElementContainsText('.alert.alert-danger', $error);
    }

    /**
     * @Given /^I should see no "([^"]*)"$/
     */
    public function iSeeNo($text)
    {
        $this->assertPageNotContainsText($text);
    }

    /**
     * @Given /^I should see the "([^"]*)" menu$/
     */
    public function iSeeTheMenu($menu)
    {
        $this->assertSession()->elementExists('xpath', sprintf('//a[contains(@href, "%s")]', $menu));
    }

    /**
     * @Then /^I should see no "([^"]*)" menu$/
     */
    public function iSeeNoMenu($menu)
    {
        $this->assertSession()->elementNotExists('xpath', sprintf('//a[contains(@href, "%s")]', $menu));
    }

    /**
     * @Then /^I should see (\d+) link with class "([^"]*)"?$/
     * @Then /^I should see (\d+) links with class "([^"]*)"?$/
     */
    public function iSeeLinksWithClass($num, $class)
    {
        $this->assertNumElements($num, $class);
    }

    /**
     * @Then /^I should see no links with class "([^"]*)"?$/
     */
    public function iSeeNoLinksWithClass($class)
    {
        $this->assertNumElements(0, $class);
    }

    /**
     * @Given /^I should see the "([^"]*)" link$/
     */
    public function iSeeLink($link)
    {
        $link = $this->replacePlaceholders($link);

        $this->assertSession()->elementExists('xpath', sprintf('//a[contains(@href, "%s")]', $link));
    }

    /**
     * @Then /^I should see no "([^"]*)" link$/
     */
    public function iSeeNoLink($link)
    {
        $link = $this->replacePlaceholders($link);

        $this->assertSession()->elementNotExists('xpath', sprintf('//a[contains(@href, "%s")]', $link));
    }

    /**
     * @When /^I click the "([^"]*)" link$/
     */
    public function iClickTheLink($link)
    {
        $link = $this->replacePlaceholders($link);

        $this->clickLink($link);
    }

    /**
     * @Then /^the page should contain$/
     */
    public function thePageContains(PyStringNode $text)
    {
        $this->assertPageContainsText($text);
    }

    /**
     * @Then /^there should be no "([^"]*)" field$/
     */
    public function thereIsNoField($field)
    {
        $field = $this->formatField($field);

        $this->assertSession()->fieldNotExists($field);
    }

    /**
     * @Then /^I should see the "([^"]*)" fields:$/
     */
    public function iCanViewTheFormFields($form, TableNode $table)
    {
        foreach ($table->getHash() as $values) {
            foreach ($values as $field => $value) {
                $field = $this->formatField(sprintf('%s.%s', $form, $field));
                $field = $this->replacePlaceholders($field);
                $value = $this->replacePlaceholders($value);

                $this->assertFieldContains($field, $value);
            }
        }
    }

    /**
     * @Then /^I should see the "([^"]*)" rows:$/
     */
    public function iCanViewTheRows($row, TableNode $table)
    {
        foreach ($table->getHash() as $i => $values) {
            $xpath = sprintf('//*[@class="%s"][position()=%d]', $row, $i + 1);

            $element = $this->getSession()->getPage()->find('xpath', $xpath);

            foreach ($values as $key => $value) {
                $actual = $element->find('css', '.' . $key);
                $message = sprintf(
                    'The element ".%s" was not found in ".%s".',
                    $key,
                    $row
                );
                a::assertNotNull($actual, $message);

                $html = $this->fixStepArgument($value);
                $regex = '/' . preg_quote($html, '/') . '/ui';

                $message = sprintf(
                    'The string "%s" was not found in the HTML of the element matching ".%s .%s".',
                    $html,
                    $row,
                    $key
                );

                if (!preg_match($regex, $actual->getHtml())) {
                    throw new \InvalidArgumentException($message);
                }
            }
        }
    }

    /**
     * @Given /^I should see the "([^"]*)" option "([^"]*)" selected$/
     */
    public function iShouldSeeTheOptionSelected($select, $option)
    {
        $select = $this->formatField($select);

        $this->assertSelect($select);
        $this->assertOptionSelected($select, $option);
    }

    /**
     * @Given /^I should see the "([^"]*)" options selected:$/
     */
    public function iShouldSeeTheOptionsSelected($select, TableNode $table)
    {
        $select = $this->formatField($select) . '[]';

        $this->assertSelect($select);

        foreach ($table->getRows() as $options) {
            foreach ($options as $option) {
                $this->assertOptionSelected($select, $option);
            }
        }
    }

    /**
     * @Then /^I should not see the "([^"]*)" field$/
     */
    public function iCannotViewTheFormField($field)
    {
        $field = $this->formatField($field);

        $field = $this->fixStepArgument($field);
        $this->assertSession()->fieldNotExists($field);
    }

    /**
     * @Then /^I can click on "([^"]*)"$/
     */
    public function iCanClickOn($title)
    {
        $this->assertElementOnPage('a[title~="' . $title . '"]');
    }

    /**
     * @Then /^I can press "([^"]*)"$/
     */
    public function iCanPress($button)
    {
        $this->assertSession()->elementExists(
            'named',
            array(
                'button',
                $this->getSession()->getSelectorsHandler()->xpathLiteral($button)
            )
        );
    }

    /**
     * @Then /^I cannot press "([^"]*)"$/
     */
    public function iCannotPress($button)
    {
        $this->assertSession()->elementNotExists(
            'named',
            array(
                'button',
                $this->getSession()->getSelectorsHandler()->xpathLiteral($button)
            )
        );
    }

    /**
     * @Given /^I fill the "([^"]*)" form with:$/
     */
    public function iFillTheFormWith($form, TableNode $table)
    {
        foreach ($table->getHash() as $values) {
            foreach ($values as $key => $value) {
                $value = $this->replacePlaceholders($value);
                $key = $this->replacePlaceholders($key);

                $this->fillField(sprintf('%s[%s]', $form, $key), $value);
            }
        }
    }

    /**
     * @Given /^I fill the "([^"]*)" field with "([^"]*)"$/
     */
    public function iFillTheFieldWith($field, $value)
    {
        $field = $this->formatField($field);

        $this->fillField($field, $this->replacePlaceholders($value));
    }

    /**
     * @Given /^I check the "([^"]*)" field$/
     */
    public function iCheckTheField($field)
    {
        $field = $this->formatField($field);

        $this->checkOption($field);
    }

    /**
     * @Given /^I uncheck the "([^"]*)" field$/
     */
    public function iUncheckTheField($field)
    {
        $field = $this->formatField($field);

        $this->uncheckOption($field);
    }

    /**
     * @Given /^I select the "([^"]*)" option "([^"]*)"$/
     */
    public function iSelectTheOption($select, $option)
    {
        $select = $this->formatField($select);
        $option = $this->replacePlaceholders($option);

        $select = $this->fixStepArgument($select);
        $option = $this->fixStepArgument($option);
        $this->getSession()->getPage()->selectFieldOption($select, $option);
    }

    /**
     * @Given /^I select the "([^"]*)" options:$/
     */
    public function iSelectTheOptions($field, TableNode $table)
    {
        $select = $this->formatField($field) . '[]';

        foreach ($table->getRows() as $options) {
            foreach ($options as $option) {
                $option = $this->replacePlaceholders($option);

                $select = $this->fixStepArgument($select);
                $option = $this->fixStepArgument($option);
                $this->getSession()->getPage()->selectFieldOption($select, $option, true);
            }
        }
    }

    /**
     * @Given /^I upload "([^"]*)" in the "([^"]*)" field$/
     */
    public function iUploadInTheField($fileName, $field)
    {
        if (!$this->contextPath)
            throw new InvalidArgumentException('Base file path not set. Call AbstractContext::setFilePath() with a valid file path.');

        $filePath = $this->contextPath . DIRECTORY_SEPARATOR . $fileName;
        if (!is_file($filePath))
            throw new InvalidArgumentException(sprintf('File not found in %s', $filePath));

        $this->attachFileToField($field, $filePath);
    }

    /**
     * @Given /^the "([^"]*)" field should equal "([^"]*)"$/
     */
    public function theFieldInFormContains($field, $value)
    {
        $field = $this->formatField($field);

        $this->assertFieldContains($field, $value);
    }

    /**
     * @Then /^I should see the "([^"]*)" details:$/
     */
    public function iCanViewTheDetails($section, TableNode $table)
    {
        foreach ($table->getHash() as $values) {
            $this->assertDetailExists($section, $values);
        }
    }

    /**
     * @Then /^there should be response headers with:$/
     */
    public function theResponseHeadersContains(TableNode $table)
    {
        $headers = $this->getSession()->getResponseHeaders();

        foreach ($table->getHash() as $values) {
            foreach ($values as $key => $value) {
                a::assertThat($headers[strtolower($key)][0], a::equalTo($value));
            }
        }
    }

    /**
     * @Then /^I should see (\d+) "([^"]*)"$/
     */
    public function iSeeElements($number, $class)
    {
        $this->assertNumElements($number, '.' . $class);
    }

    /**
     * @Given /^dump element "([^"]*)"$/
     */
    public function iDumpElement($element)
    {
        var_dump($this->getSession()->getPage()->find('css', $element)->getHtml());
    }

    public function getContainer()
    {
        return $this->kernel->getContainer();
    }

    private function assertDetailExists($section, $values)
    {
        if (strpos($section, '.') !== false) {
            $vars = explode('.', $section);
            $xpath = sprintf('//*[@class="%s"][position()=%d]', $vars[0], $vars[1] + 1);

            $element = $this->getSession()->getPage()->find('xpath', $xpath);

            if (null === $element) {
                throw new \InvalidArgumentException(sprintf('Could not evaluate XPath: "%s"', $xpath));
            }
        } else {
            $element = $this->assertSession()->elementExists('css', '.' . $section);
        }

        foreach ($values as $key => $value) {
            $actual = $element->getHtml();
            $html = $this->fixStepArgument($value);
            $regex = '/' . preg_quote($html, '/') . '/ui';

            if (!preg_match($regex, $actual)) {
                $message = sprintf(
                    'The string "%s" was not found in the HTML of the element matching %s "%s".',
                    $html,
                    $section
                );
                throw new \InvalidArgumentException($message);
            }
        }
    }

    public function printDebug($string)
    {
        $this->getOutput()->writeln($string);
    }

    /**
     * @return ConsoleOutput
     */
    private function getOutput()
    {
        if ($this->output === null) {
            $this->output = new ConsoleOutput();
        }

        return $this->output;
    }

    protected function formatField($field)
    {
        $vars = explode('.', $field);
        $field = $vars[0];
        array_shift($vars);

        foreach ($vars as $var) {
            $field .= '[' . $var . ']';
        }

        return $field;
    }

    protected function assertSelect($select)
    {
        $selectElement = $this->getSession()->getPage()->find('named', array('select', "\"{$select}\""));
        a::assertNotNull($selectElement, sprintf('Select %s does not exist', $select));
    }

    protected function assertOptionSelected($select, $option)
    {
        $option = $this->replacePlaceholders($option);

        $selectElement = $this->getSession()->getPage()->find('named', array('select', "\"{$select}\""));
        $optionElement = $selectElement->find('named', array('option', "\"{$option}\""));

        a::assertNotNull($optionElement, sprintf('Option %s does not exist in select %s', $option, $select));
        a::assertTrue($optionElement->hasAttribute("selected"));
        a::assertTrue($optionElement->getAttribute("selected") == "selected");
    }

    protected function assertOptionNotSelected($select, $option)
    {
        $option = $this->replacePlaceholders($option);

        $selectElement = $this->getSession()->getPage()->find('named', array('select', "\"{$select}\""));
        $optionElement = $selectElement->find('named', array('option', "\"{$option}\""));

        a::assertNotNull($optionElement, sprintf('Option %s does not exist in select %s', $option, $select));
        a::assertFalse($optionElement->hasAttribute("selected"));
        a::assertFalse($optionElement->getAttribute("selected") == "selected");
    }
}
