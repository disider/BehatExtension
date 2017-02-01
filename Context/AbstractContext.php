<?php

namespace Diside\BehatExtension\Context;

use Behat\Behat\EventDispatcher\Event;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Session;
use Behat\MinkExtension\Context\MinkContext;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use InvalidArgumentException;
use PHPUnit_Framework_Assert as a;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

abstract class AbstractContext extends MinkContext implements KernelAwareContext
{
    use ContextTrait;

    /**
     * @var ConsoleOutput
     */
    protected $output;

    /** @var string */
    protected $filePath;

    protected function setFilePath($path)
    {
        $this->filePath = $path;
    }

    public function getExpressionLanguage()
    {
        return new ExpressionLanguage();
    }

    /**
     * @AfterScenario
     */
    public function printLastResponseOnError(AfterScenarioScope $scope)
    {
        if (!$scope->getTestResult()->isPassed()) {
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

                /** @var \DOMElement $crawledNode */

                $this->printSelector($crawler, 'h1');
                $this->printSelector($crawler, 'h2');
                $this->printSelector($crawler, '.alert');

                /** @var \DOMElement $node */
                foreach ($crawler->filter('.has-error') as $node) {
                    $subCrawler = new Crawler($node);
                    $items = $subCrawler->filter('label')->extract(['for']);
                    $errors = $subCrawler->filter('ul li')->extract(['_text']);

                    foreach($items as $i => $item) {
                        $this->printDebug(sprintf('    <info>%s:</info> <error>%s</error>', $item, $errors[$i]));
                        break;
                    }
                }

                /** @var \DOMElement $node */
                foreach ($crawler->filter('.help-block') as $node) {
                    $subCrawler = new Crawler($node);
                    $errors = $subCrawler->filter('li')->extract(['_text']);

                    foreach($errors as $i => $error) {
                        $this->printDebug(sprintf('    <info>Global error:</info> <error>%s</error>', $error));
                    }
                }
            } else {
                $this->printDebug($body);
            }
        }
    }

    /**
     * @Override
     */
    public function visit($page)
    {
        $page = $this->replacePlaceholders($page);

        parent::visit($page);
    }


    /**
     * @When I visit :page
     */
    public function iVisit($page)
    {
        $this->visit($page);

        /** @var Session $session */
        $session = $this->getSession();
        $driver = $session->getDriver();

        if ($driver instanceof BrowserKitDriver) {
            if ($this->getSession()->getStatusCode() != 200) {
                $this->printLastResponse();
            }

            $this->assertResponseStatus(200);
        }
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
        $this->visit($page);

        $this->assertResponseStatusIsNot(200);
    }

    public function assertPageAddress($page)
    {
        $page = $this->replacePlaceholders($page);

        $this->assertSession()->addressEquals($this->locatePath($page));
    }

    /**
     * @Given /^I should see text "([^"]*)"$/
     */
    public function iShouldSeeText($text)
    {
        $text = $this->replacePlaceholders($text);
        $this->assertPageContainsText($text);
    }

    /**
     * @Then /^I should see an? "([^"]*)" error$/
     */
    public function iSeeAnError($error)
    {
        $elements = $this->getSession()->getPage()->findAll('css', '.has-error .help-block');

        /** @var NodeElement $element */
        foreach ($elements as $element) {
            if (strpos($element->getText(), $error) !== false) {
                return true;
            }
        }

        $message = sprintf('No "%s" error found', $error);
        throw new InvalidArgumentException($message);
    }

    /**
     * @Then /^I should see an? "([^"]*)" error with class "([^"]*)"$/
     */
    public function iSeeAnErrorWithClass($error, $class)
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

        $this->assertSession()->elementExists('xpath', $this->formatXpathLink($link));
    }

    /**
     * @Then /^I should see no "([^"]*)" link$/
     */
    public function iSeeNoLink($link)
    {
        $link = $this->replacePlaceholders($link);

        $this->assertSession()->elementNotExists('xpath', $this->formatXpathLink($link));
    }

    /**
     * @When /^I click the "([^"]*)" link$/
     * @When /^I click on "([^"]*)"$/
     */
    public function iClickTheLink($link)
    {
        $link = $this->replacePlaceholders($link);

        $this->clickLink($link);
    }

    /**
     * @When /^I click the (\d+)(st|nd|rd|th)? "([^"]*)" link$/
     */
    public function iClickTheLinkInPosition($num, $pos, $link)
    {
        $link = $this->replacePlaceholders($link);

        $link = $this->fixStepArgument($link);
        $items = $this->getSession()->getPage()->findAll('named', ['link', $link]);

        if (count($items) < $num) {
            throw new ElementNotFoundException($this->getSession()->getDriver(), 'link', 'id|title|alt|text', $link);
        }

        /** @var NodeElement $item */
        $item = $items[$num - 1];
        $item->click();
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
     * @Given /^I should see the "([^"]*)" field with "([^"]*)"$/
     */
    public function iShouldSeeTheFieldWith($field, $value)
    {
        $field = $this->replacePlaceholders($field);
        $field = $this->formatField($field);

        $value = $this->replacePlaceholders($value);

        $this->assertFieldContains($field, $value);
    }

    /**
     * @Then /^I should see the "([^"]*)" fields:$/
     */
    public function iShouldSeeTheFormFields($form, TableNode $table)
    {
        foreach ($table->getRowsHash() as $field => $value) {
            $field = $this->formatField(sprintf('%s.%s', $form, $field));
            $field = $this->replacePlaceholders($field);
            $value = $this->replacePlaceholders($value);

            $this->assertFieldContains($field, $value);
        }
    }

    /**
     * @Then /^I should not see the "([^"]*)" fields:$/
     */
    public function iShouldNotSeeTheFormFields($form, TableNode $table)
    {
        foreach ($table->getRows() as $fields) {
            foreach ($fields as $field) {
                $field = $this->formatField(sprintf('%s.%s', $form, $field));
                $field = $this->replacePlaceholders($field);

                $element = $this->getSession()->getPage()->findField($field);

                if($element) {
                    $message = sprintf('An element matching "%s" appears on this page, but it should not.', $field);
                    throw new InvalidArgumentException($message);
                }
            }
        }
    }

    /**
     * @Then /^I should not see the "([^"]*)" fields values:$/
     */
    public function iShouldSeeNoFormFieldsValues($form, TableNode $table)
    {
        foreach ($table->getRowsHash() as $field => $value) {
            $field = $this->formatField(sprintf('%s.%s', $form, $field));
            $field = $this->replacePlaceholders($field);
            $value = $this->replacePlaceholders($value);

            $this->assertFieldNotContains($field, $value);
        }
    }

    /**
     * @Then /^I should see the "([^"]*)" form errors:$/
     */
    public function iShouldSeeTheFormErrors($form, TableNode $table)
    {
        $form = $this->replacePlaceholders($form);

        foreach ($table->getRowsHash() as $field => $value) {
            $field = str_replace('.', '_', $field);

            $element = sprintf('div.has-error label[for="%s_%s"] ~ ul', $form, $field);
            $this->assertElementContains($element, $value);
        }
    }

    /**
     * @Given /^I should see the "([^"]*)" checkbox error:$/
     * @Given /^I should see the "([^"]*)" checkbox errors:$/
     */
    public function iShouldSeeTheCheckboxError($field, TableNode $table)
    {
        foreach ($table->getRows() as $value) {
            $element = sprintf('//input[@id = \'%s\']/../../ul', $field);
            $this->assertElementContains($element, $value[0], 'xpath');
        }
    }

    /**
     * @Then /^I should see the "([^"]*)" form:$/
     */
    public function iShouldSeeTheForm($form, TableNode $table)
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
    public function iShouldSeeRows($row, TableNode $table)
    {
        $xpath = sprintf('//*[contains(concat(" ", normalize-space(@class), " "), " %s ")]', $row);
        $rows = $this->getSession()->getPage()->findAll('xpath', $xpath);

        a::assertThat(
            count($rows),
            a::greaterThanOrEqual(count($table->getHash())),
            sprintf('Not enough "%s" rows found.', $row)
        );

        foreach ($table->getHash() as $i => $values) {
            $element = $rows[$i];

            foreach ($values as $key => $value) {
                if (!empty($value)) {
                    $actual = $this->findElementInRowByClass($row, $element, $key);
                    $this->assertRowElementContainsText($i, $row, $key, $value, $actual);
                }
            }
        }
    }

    /**
     * @Then /^I should see the "([^"]*)" row details:$/
     */
    public function iShouldSeeRowDetails($row, TableNode $table)
    {
        $pos = strpos($row, '.');

        $i = substr($row, $pos + 1);
        $row = substr($row, 0, $pos);

        $xpath = sprintf('//*[contains(concat(" ", normalize-space(@class), " "), " %s ")]', $row);
        $rows = $this->getSession()->getPage()->findAll('xpath', $xpath);

        a::assertThat(
            count($rows),
            a::greaterThanOrEqual($i),
            sprintf('Not enough "%s" rows found.', $row)
        );

        foreach ($table->getRowsHash() as $key => $value) {
            $element = $rows[$i];
            if (!empty($value)) {
                $actual = $this->findElementInRowByClass($row, $element, $key);
                $this->assertRowElementContainsText($i, $row, $key, $value, $actual);
            }
        }
    }

    /**
     * @Then /^I should see no "([^"]*)" row details:$/
     */
    public function iShouldSeeNoRowDetails($row, TableNode $table)
    {
        $pos = strpos($row, '.');

        $i = substr($row, $pos + 1);
        $row = substr($row, 0, $pos);

        $xpath = sprintf('//*[contains(concat(" ", normalize-space(@class), " "), " %s ")]', $row);
        $rows = $this->getSession()->getPage()->findAll('xpath', $xpath);

        a::assertThat(
            count($rows),
            a::greaterThanOrEqual($i),
            sprintf('Not enough "%s" rows found.', $row)
        );

        foreach ($table->getRowsHash() as $key => $value) {
            $element = $rows[$i];
            $this->assertElementInRowByClassDoesNotExist($row, $element, $key);
        }

    }

    /**
     * @Then /^I should see no "([^"]*)" rows$/
     */
    public function iShouldSeeNoRows($row)
    {
        $this->iShouldCountRows(0, $row);
    }

    /**
     * @Then /^I should see (\d+) "([^"]*)" rows?$/
     */
    public function iShouldCountRows($number, $row)
    {
        $xpath = sprintf('//*[contains(concat(" ", normalize-space(@class), " "), " %s ")]', $row);

        $elements = $this->getSession()->getPage()->findAll('xpath', $xpath);

        a::assertThat(count($elements), a::equalTo($number));
    }

    /**
     * @Then /^I should see the "([^"]*)" option "([^"]*)" selected$/
     */
    public function iShouldSeeTheOptionSelected($select, $option)
    {
        $this->assertSelect($select);
        $this->assertOptionSelected($select, $option);
    }

    /**
     * @Then /^I should see the "([^"]*)" options selected:$/
     */
    public function iShouldSeeTheOptionsSelected($select, TableNode $table)
    {
        $select = $this->formatField($select);
        $this->assertSelect($select);

        foreach ($table->getRows() as $options) {
            foreach ($options as $option) {
                $this->assertOptionSelected($select, $option);
            }
        }
    }

    /**
     * @Then /^I should see the "([^"]*)" field checked$/
     */
    public function iShouldSeeTheChecked($field)
    {
        $field = $this->replacePlaceholders($field);
        $field = $this->formatField($field);

        $this->assertCheckboxChecked($field);
    }

    /**
     * @Then /^I should see the "([^"]*)" field unchecked$/
     */
    public function iShouldSeeTheUnchecked($field)
    {
        $field = $this->replacePlaceholders($field);
        $field = $this->formatField($field);

        $this->assertCheckboxNotChecked($field);
    }

    /**
     * @Then /^I should see the "([^"]*)" field$/
     */
    public function iViewTheFormField($field)
    {
        $field = $this->formatField($field);

        $field = $this->fixStepArgument($field);
        $this->assertSession()->fieldExists($field);
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
     * @Then /^I can press "([^"]*)"$/
     */
    public function iCanClickOn($linkOrButton)
    {
        $this->assertSession()->elementExists(
            'named',
            array(
                'link_or_button',
                $this->getSession()->getSelectorsHandler()->xpathLiteral($linkOrButton),
            )
        );
    }

    /**
     * @Then /^I cannot click on "([^"]*)"$/
     * @Then /^I cannot press "([^"]*)"$/
     */
    public function iCannotPress($linkOrButton)
    {
        $element = $this->getSession()->getPage()->find(
            'named',
            array(
                'link_or_button',
                $this->getSession()->getSelectorsHandler()->xpathLiteral($linkOrButton),
            )
        );

        if (null !== $element) {
            $message = sprintf('An element matching "%s" appears on this page, but it should not.', $linkOrButton);
            throw new InvalidArgumentException($message);
        }
    }

    /**
     * @Given /^I fill the "([^"]*)" fields with:$/
     */
    public function iFillTheFieldsWith($form, TableNode $table)
    {
        foreach ($table->getRowsHash() as $key => $value) {
            $form = $this->replacePlaceholders($form);

            $value = $this->replacePlaceholders($value);
            $key = $this->replacePlaceholders($key);

            $key = $this->formatField(sprintf('%s.%s', $form, $key));

            if ($this->getSession()->getPage()->hasSelect($key)) {
                $this->iSelectTheOption($key, $value);
            } else {
                $this->fillField($key, $value);
            }
        }
    }

    /**
     * @Given /^I fill the "([^"]*)" form with:$/
     * @deprecated: Use /^I fill the "([^"]*)" fields with:$/
     */
    public function iFillTheFormWith($form, TableNode $table)
    {
        $form = $this->formatField($form);

        foreach ($table->getHash() as $index => $values) {
            foreach ($values as $key => $value) {
                $value = $this->replacePlaceholders($value);
                $key = $this->replacePlaceholders($key);

                $this->fillField(sprintf('%s[%s]', $form, $key), $value);
            }
        }
    }

    /**
     * @Given /^I fill the "([^"]*)" form collection with:$/
     */
    public function iFillTheFormCollectionWith($form, TableNode $table)
    {
        $form = $this->replacePlaceholders($form);

        foreach ($table->getHash() as $index => $values) {
            foreach ($values as $key => $value) {
                $value = $this->replacePlaceholders($value);
                $key = $this->replacePlaceholders($key);

                $field = sprintf('%s[%s][%s]', $form, $index, $key);

                if ($this->getSession()->getPage()->hasSelect($field)) {
                    $this->iSelectTheOption($field, $value);
                } else {
                    $this->fillField($field, $value);
                }
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
        $field = $this->replacePlaceholders($field);
        $field = $this->formatField($field);

        $this->checkOption($field);
    }

    /**
     * @Given /^I check the "([^"]*)" fields:$/
     */
    public function iCheckTheFields($field, TableNode $table)
    {
        foreach ($table->getRows() as $options) {
            foreach ($options as $option) {
                $checkbox = $this->getSession()->getPage()->findField($option);

                if (!$checkbox) {
                    throw new InvalidArgumentException(
                        sprintf('Checkbox "%s" with label "%s" not found', $field, $option)
                    );
                }

                $checkbox->check();
            }
        }
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
     * @Given /^I check the "([^"]*)" radio button with "([^"]*)" value$/
     */
    public function iCheckTheRadioButton($element, $value)
    {
        $this->getSession()->getPage()->selectFieldOption($element, $value);
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
     * @Then /^I should see the "([^"]*)" field (disabled)$/
     */
    public function iSeeTheFieldStatus($field, $status)
    {
        $element = $this->findField($field);

        if (!$element->hasAttribute($status)) {
            $message = sprintf('The field "%s" has no attribute "%s"', $field, $status);
            throw new InvalidArgumentException($message);
        }
    }

    /**
     * @Then /^I should see the "([^"]*)" option "([^"]*)"$/
     */
    public function iSeeTheOption($field, $option)
    {
        $option = $this->replacePlaceholders($option);

        $element = $this->findOption($field, $option);

        if (!$element) {
            $message = sprintf('There is no option "%s" within "%s".', $option, $field);
            throw new InvalidArgumentException($message);
        }
    }

    /**
     * @Then /^I should not see the "([^"]*)" option "([^"]*)"$/
     */
    public function iSeeNoOption($select, $option)
    {
        $this->assertSelect($select);
        $selectElement = $this->findSelect($select);

        $optionElement = $selectElement->find('named', array('option', $option));

        if($optionElement != null) {
            $message = sprintf('There is an option "%s" within "%s", but it should not.', $option, $select);
            throw new InvalidArgumentException($message);
        }
    }

    /**
     * @Then /^I should see the "([^"]*)" option "([^"]*)" (disabled)$/
     */
    public function iSeeTheOptionStatus($field, $option, $status)
    {
        $element = $this->findOption($field, $option);

        if (!$element->hasAttribute($status)) {
            $message = sprintf('The option "%s" within "%s" has no attribute "%s"', $option, $field, $status);
            throw new InvalidArgumentException($message);
        }
    }

    /**
     * @Then /^I should not see the "([^"]*)" option "([^"]*)" (disabled)$/
     */
    public function iSeeNoOptionStatus($field, $option, $status)
    {
        $element = $this->findOption($field, $option);

        if ($element->hasAttribute($status)) {
            $message = sprintf(
                'The option "%s" within "%s" has attribute "%s", but it should not',
                $option,
                $field,
                $status
            );
            throw new InvalidArgumentException($message);
        }
    }

    /**
     * @Then /^I can select the "([^"]*)" option "([^"]*)"$/
     */
    public function iCanSelectTheOption($field, $option)
    {
        $this->findOption($field, $option);
    }

    /**
     * @Then /^I should not see the "([^"]*)" field (disabled)$/
     */
    public function iSeeNoFieldStatus($field, $status)
    {
        $element = $this->findField($field);

        if ($element->hasAttribute($status)) {
            $message = sprintf('The field "%s" has an attribute "%s", but is should not.', $field, $status);
            throw new InvalidArgumentException($message);
        }
    }

    /**
     * @Given /^I upload "([^"]*)" in the "([^"]*)" field$/
     */
    public function iUploadInTheField($fileName, $field)
    {
        $field = $this->formatField($field);

        if (!$this->filePath) {
            throw new InvalidArgumentException(
                'Base file path not set. Call AbstractContext::setFilePath() with a valid file path.'
            );
        }

        $filePath = $this->filePath . DIRECTORY_SEPARATOR . $fileName;
        if (!is_file($filePath)) {
            throw new InvalidArgumentException(sprintf('File not found in %s', $filePath));
        }

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
        foreach ($table->getRowsHash() as $field => $value) {
            $selector = sprintf('.%s .%s', $section, $field);

            $value = $this->replacePlaceholders($value);

            $this->assertElementContains($selector, $value);
        }
    }

    /**
     * @Then /^I should see no "([^"]*)" details:$/
     */
    public function iCanViewNoDetails($section, TableNode $table)
    {
        foreach ($table->getRowsHash() as $field => $value) {
            $selector = sprintf('.%s .%s', $section, $field);

            $this->assertElementNotContains($selector, $value);
        }
    }

    /**
     * @Then /^I should see no "([^"]*)" details row?s:$/
     */
    public function iCanViewNoDetailsRow($section, TableNode $table)
    {
        foreach ($table->getRowsHash() as $field => $value) {
            $selector = sprintf('.%s .%s', $section, $field);

            $this->assertElementNotOnPage($selector);
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
     * @Then /^I should see (\d+) "([^"]*)"(s|es)?$/
     */
    public function iSeeElements($number, $class)
    {
        $this->assertNumElements($number, '.' . $class);
    }

    /**
     * @Then /^the "([^"]*)" entity property should be "([^"]*)"$/
     */
    public function theEntityPropertyShouldBe($field, $value)
    {
        $field = $this->replacePlaceholders($field);
        $value = $this->replacePlaceholders($value);

        if (in_array($value, array('true', 'false'))) {
            $value = $value == 'true';
        }

        a::assertThat($field, a::equalTo($value));
    }

    /**
     * @Given /^the "([^"]*)" entity property should contain "([^"]*)"$/
     */
    public function theEntityPropertyShouldContain($field, $value)
    {
        $field = $this->replacePlaceholders($field);
        $value = $this->replacePlaceholders($value);

        a::assertThat($field, a::stringContains($value));
    }

    /**
     * @Then /^I should see the "([^"]*)" form collection:$/
     */
    public function iShouldSeeFormCollection($form, TableNode $table)
    {
        foreach ($table->getHash() as $i => $values) {
            foreach ($values as $field => $value) {
                $field = $this->formatField(sprintf('%s.%s.%s', $form, $i, $field));
                $field = $this->replacePlaceholders($field);
                $value = $this->replacePlaceholders($value);

                if ($this->getSession()->getPage()->hasSelect($field)) {
                    $this->assertOptionSelected($field, $value);
                } else {
                    $this->assertFieldContains($field, $value);
                }
            }
        }
    }

    /**
     * @Given /^dump element "([^"]*)"$/
     */
    public function iDumpElement($element)
    {
        var_dump($this->getSession()->getPage()->find('css', $element)->getHtml());
    }


    /**
     * @Then /^I should see the "([^"]*)" radio "([^"]*)"$/
     * @Then /^I should see the "([^"]*)" checkbox "([^"]*)"$/
     */
    public function iSeeTheRadio($field, $radio)
    {
        $radio = $this->replacePlaceholders($radio);

        $element = $this->findOption($field, $radio);

        if (!$element) {
            $message = sprintf('There is no option "%s" within "%s".', $radio, $field);
            throw new InvalidArgumentException($message);
        }
    }

    /**
     * @Then /^I should not see the "([^"]*)" radio "([^"]*)"$/
     * @Then /^I should not see the "([^"]*)" checkbox "([^"]*)"$/
     */
    public function iSeeNoRadio($field, $radio)
    {
        $radio = $this->replacePlaceholders($radio);

        try {
            $element = $this->findOption($field, $radio);
        } catch (InvalidArgumentException $e) {
            $element = null;
        }

        if ($element) {
            $message = sprintf('There is an option "%s" within "%s", but it should not.', $radio, $field);
            throw new InvalidArgumentException($message);
        }
    }

    protected function printSelector($crawler, $selector)
    {
        foreach ($crawler->filter($selector) as $header) {
            $this->printDebug(sprintf('    <info>%s:</info> %s', $selector, trim($header->nodeValue)));
        }
    }

    public function printDebug($string)
    {
        $this->getOutput()->writeln($string);
    }

    public function pressButton($button)
    {
        $button = $this->replacePlaceholders($button);
        $button = $this->fixStepArgument($button);
        $this->getSession()->getPage()->pressButton($button);
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
        $selectElement = $this->findSelect($select);
        a::assertNotNull($selectElement, sprintf('Select %s does not exist', $select));
    }

    protected function assertOptionSelected($select, $option)
    {
        $option = $this->replacePlaceholders($option);

        $selectElement = $this->findSelect($select);
        $optionElement = $selectElement->find('named', array('option', $option));

        a::assertNotNull($optionElement, sprintf('Option %s does not exist in select %s', $option, $select));
        a::assertTrue($optionElement->hasAttribute("selected"), sprintf('Option %s is not selected in select %s', $option, $select));
        a::assertTrue($optionElement->getAttribute("selected") == "selected", sprintf('Option %s is not selected in select %s', $option, $select));
    }

    protected function assertOptionNotSelected($select, $option)
    {
        $option = $this->replacePlaceholders($option);

        $selectElement = $this->findSelect($select);
        $optionElement = $selectElement->find('named', array('option', $option));

        a::assertNotNull($optionElement, sprintf('Option %s does not exist in select %s', $option, $select));
        a::assertFalse($optionElement->hasAttribute("selected"));
        a::assertFalse($optionElement->getAttribute("selected") == "selected");
    }

    protected function findElementInRow($row, $xpath, $i)
    {
        $element = $this->getSession()->getPage()->find('xpath', $xpath);

        if (!$element) {
            throw new InvalidArgumentException(sprintf('The element "%s[%d]" was not found.', $row, $i));
        }

        return $element;
    }

    protected function findElementInRowByClass($row, NodeElement $element, $key)
    {
        $actual = $element->find('css', '.' . $key);
        $message = sprintf(
            'The element ".%s" was not found in ".%s".',
            $key,
            $row
        );
        a::assertNotNull($actual, $message);

        return $actual;
    }

    protected function assertElementInRowByClassDoesNotExist($row, NodeElement $element, $key)
    {
        $actual = $element->find('css', '.' . $key);
        $message = sprintf(
            'The element ".%s" was found in ".%s", but it should not exist.',
            $key,
            $row
        );

        if ($actual) {
            throw new \InvalidArgumentException($message);
        }
    }

    protected function assertRowElementContainsText($position, $row, $key, $expected, NodeElement $nodeElement)
    {
        $expected = $this->fixStepArgument($expected);
        $expected = $this->replacePlaceholders($expected);
        $expected = html_entity_decode($expected);

        $regex = '/' . preg_quote($expected, '/') . '/ui';

        $actual = trim($nodeElement->getHtml());

        $message = sprintf(
            'The string "%s" was not found in the HTML of the row "%d" matching ".%s .%s", found "%s"',
            $expected,
            $position,
            $row,
            $key,
            $actual
        );

        if (!preg_match($regex, $actual)) {
            throw new \InvalidArgumentException($message);
        }

        return $message;
    }

    public function assertElementContains($selector, $value, $selectorType = 'css')
    {
        $element = $this->assertSession()->elementExists($selectorType, $selector);
        $actual = $element->getHtml();
        $regex = '/' . preg_quote($value, '/') . '/ui';

        if (!preg_match($regex, $actual)) {
            $message = sprintf(
                'The string "%s" was not found in the HTML of the element matching %s "%s" (found "%s").',
                $value,
                'css',
                $selector,
                trim($actual)
            );
            throw new \InvalidArgumentException($message);
        }
    }

    private function findSelect($select)
    {
        $select = $this->formatField($select);

        $session = $this->getSession();

        $page = $session->getPage();
        $selectElement = $page->find('named', array('select', $select));

        if (!$selectElement) {
            $selectElement = $page->find('named', array('select', $select . '[]'));
        }

        return $selectElement;
    }

    private function findField($field)
    {
        $field = $this->formatField($field);

        $element = $this->getSession()->getPage()->findField($field);

        if (!$element) {
            $message = sprintf('The field "%s" does not exist', $field);
            throw new InvalidArgumentException($message);
        }

        return $element;
    }

    private function findFields($field, $type = 'field')
    {
        $field = $this->formatField($field);

        $elements = $this->getSession()->getPage()->findAll('named', array($type, $field));

        if (count($elements) == 0) {
            $message = sprintf('No "%s" fields exist', $field);
            throw new InvalidArgumentException($message);
        }

        return $elements;
    }

    private function findOption($field, $option)
    {
        $elements = $this->findFields($field);

        foreach ($elements as $element) {
            if ($element->getAttribute('value') == $option) {
                return $element;
            }
        }

        $message = sprintf('The option "%s" within "%s" does not exist', $option, $field);
        throw new InvalidArgumentException($message);
    }

    /**
     * @param $link
     * @return string
     */
    protected function formatXpathLink($link)
    {
        return sprintf('//a[("%1$s" = substring(@href, string-length(@href) - string-length("%1$s") +1)) or contains(@title, "%1$s") or descendant::text()[contains(., "%1$s")]]', $link);
    }
}
