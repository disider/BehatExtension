<?php

namespace Diside\BehatExtension\Context;

use PHPUnit_Framework_Assert as a;

trait EntityContextTrait
{
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

        a::assertContains($value, $field);
    }

    /**
     * @Given /^the "([^"]*)" entity property should not contain "([^"]*)"$/
     */
    public function theEntityPropertyShouldNotContain($field, $value)
    {
        $field = $this->replacePlaceholders($field);
        $value = $this->replacePlaceholders($value);

        a::assertNotContains($value, $field);
    }

    /**
     * @Given /^the "([^"]*)" entity property should be empty$/
     */
    public function theEntityPropertyShouldBeEmpty($field)
    {
        $field = $this->replacePlaceholders($field);

        a::assertEmpty($field);
    }

    /**
     * @Given /^the "([^"]*)" entity property should not be empty$/
     */
    public function theEntityPropertyShouldNotBeEmpty($field)
    {
        $field = $this->replacePlaceholders($field);

        a::assertNotEmpty($field);
    }
}