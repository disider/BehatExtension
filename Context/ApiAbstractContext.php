<?php

namespace Diside\BehatExtension\Context;

use Behat\Behat\Context\BehatContext;
use Behat\Behat\Event\BaseScenarioEvent;
use Behat\Behat\Event\StepEvent;
use Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Symfony2Extension\Context\KernelAwareInterface;
use PHPUnit_Framework_Assert as a;
use Symfony\Component\BrowserKit\Client;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiAbstractContext extends BehatContext implements KernelAwareInterface
{
    use ContextTrait;

    /** @var Client */
    protected $client;

    /**
     * The last request that was used to make the response
     *
     * @var Request
     */
    protected $lastRequest;

    /**
     * The BrowserKit HTTP Response.
     *
     * @var Response
     */
    protected $response;

    /**
     * The decoded response object.
     */
    protected $responsePayload;

    /**
     * @var ConsoleOutput
     */
    protected $output;

    /** @var string */
    private $payload;

    /** @var string */
    private $accessToken;


    /**
     * @AfterScenario
     */
    public function printLastResponseOnError(BaseScenarioEvent $scenarioEvent)
    {
        if ($scenarioEvent->getResult() == StepEvent::FAILED) {
            if ($this->response) {
                $body = $this->getResponse()->getContent();

                // could we even ask them if they want to print out the error?
                // or do it based on verbosity

                // print some debug details
                $this->printDebug('');
                $this->printDebug('<error>Failure!</error> when making the following request:');
                $this->printDebug(
                    sprintf(
                        '<comment>%s</comment>: <info>%s</info>',
                        $this->lastRequest->getMethod(),
                        $this->lastRequest->getUri()
                    ) . "\n"
                );

                if ($this->response->headers->get('Content-Type') == 'application/json') {
                    $data = json_decode($body);
                    if ($data === null) {
                        // invalid JSON!
                        $this->printDebug($body);
                    } else {
                        // valid JSON, print it pretty
                        $this->printDebug(json_encode($data, JSON_PRETTY_PRINT));
                    }
                } else {
                    // the response is HTML - see if we should print all of it or some of it
                    $isValidHtml = strpos($body, '</body>') !== false;

                    if ($isValidHtml) {
                        $this->printDebug(
                            '<error>Failure!</error> Below is a summary of the HTML response from the server.'
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
        }
    }

    /**
     * Checks the response exists and returns it.
     *
     * @return Response
     */
    protected function getResponse()
    {
        if (!$this->response) {
            throw new \Exception("You must first make a request to check a response.");
        }

        return $this->response;
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

    /**
     * @Given /^there is a payload:$/
     */
    public function thereIsAPayload(PyStringNode $payload)
    {
        $this->payload = $payload;
    }

    /**
     * @When /^I request "(GET|PUT|POST|DELETE|PATCH) ([^"]*)"$/
     */
    public function iRequest($httpMethod, $resource)
    {
        $resource = $this->replacePlaceholders($resource);
        if ($this->accessToken)
            $resource .= '?access_token=' . $this->accessToken;
        $this->resource = $resource;

        $method = strtolower($httpMethod);

        $payload = $this->replacePlaceholders($this->payload);

        $this->client->request($method, $resource, array(), array(), array('HTTP_X-Requested-With' => 'XMLHttpRequest'), $payload);
        $this->lastRequest = $this->client->getRequest();
        $this->response = $this->client->getResponse();
        $this->responsePayload = $this->getResponsePayload();
    }

    /**
     * @Then /^the response status code should be (?P<code>\d+)$/
     */
    public function checkResponseStatusCode($statusCode)
    {
        $response = $this->getResponse();
        $contentType = $response->headers->get('Content-Type');

        // looks for application/json or something like application/problem+json
        if (preg_match('#application\/(.)*\+?json#', $contentType)) {
            $bodyOutput = $response->getContent();
        } else {
            $bodyOutput = 'Output is "' . $contentType . '", which is not JSON and is therefore scary. Run the request manually.';
        }

        a::assertSame((int)$statusCode, (int)$response->getStatusCode(), $bodyOutput);
    }

    /**
     * @Given /^the "([^"]*)" header should be "([^"]*)"$/
     */
    public function theHeaderShouldBe($headerName, $expectedHeaderValue)
    {
        $response = $this->getResponse();

        $expectedHeaderValue = $this->replacePlaceholders($expectedHeaderValue);

        a::assertEquals($expectedHeaderValue, (string)$response->headers->get($headerName));
    }

    /**
     * @Given /^print last response$/
     */
    public function printLastResponse()
    {
        if ($this->response) {
            $this->printDebug((string)$this->response);
        }
    }

    /**
     * @Given /^the following response properties exist:$/
     */
    public function theResponsePropertiesExist(PyStringNode $properties)
    {
        foreach (explode("\n", (string)$properties) as $property) {
            $this->thePropertyExists($property);
        }
    }

    /**
     * @Given /^the "([^"]*)" property should exist$/
     */
    public function thePropertyExists($property)
    {
        $message = sprintf(
            'Asserting the [%s] property exists in: %s',
            $property,
            json_encode($this->responsePayload)
        );

        a::assertTrue($this->hasProperty($this->responsePayload, $property), $message);
    }

    /**
     * @Given /^the "([^"]*)" property should not exist$/
     */
    public function thePropertyNotExists($property)
    {
        $message = sprintf(
            'Asserting the [%s] property does not exist in: %s',
            $property,
            json_encode($this->responsePayload)
        );

        a::assertFalse($this->hasProperty($this->responsePayload, $property), $message);
    }

    /**
     * @Given /^the "([^"]*)" link should exist$/
     */
    public function theLinkExists($link)
    {
        $this->thePropertyExists('_links.' . $link);
    }

    private function hasProperty($payload, $property)
    {
        foreach (explode('.', $property) as $segment) {
            if (is_object($payload)) {
                if (!property_exists($payload, $segment)) {
                    return false;
                }
                $payload = $payload->{$segment};
            } elseif (is_array($payload)) {
                if (!array_key_exists($segment, $payload)) {
                    return false;
                }

                $payload = $payload[$segment];
            }
        }

        return true;
    }

    /**
     * @Given /^the response payload contains the following properties:$/
     */
    public function theResponsePayloadContainsTheFollowingProperties(TableNode $table)
    {
        foreach ($table->getHash() as $properties) {
            foreach ($properties as $property => $value) {
                $this->thePropertyEquals($property, $value);
            }
        }
    }

    /**
     * @Then /^the "([^"]*)" property should equal "([^"]*)"$/
     */
    public function thePropertyEquals($property, $expectedValue)
    {
        $payload = $this->getResponsePayload();
        $actualValue = $this->getProperty($payload, $property);
        $actualValue = is_bool($actualValue) ? ($actualValue ? "true" : "false") : $actualValue;

        $expectedValue = $this->replacePlaceholders($expectedValue);

        a::assertEquals(
            $expectedValue,
            $actualValue,
            "Asserting the [$property] property in current scope equals [$expectedValue]: " . json_encode($payload)
        );
    }

    /**
     * Return the response payload from the current response.
     *
     * @return  mixed
     */
    protected function getResponsePayload()
    {
        if (!$this->responsePayload) {
            $json = json_decode($this->getResponse()->getContent(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $message = 'Failed to decode JSON body ';

                switch (json_last_error()) {
                    case JSON_ERROR_DEPTH:
                        $message .= '(Maximum stack depth exceeded).';
                        break;
                    case JSON_ERROR_STATE_MISMATCH:
                        $message .= '(Underflow or the modes mismatch).';
                        break;
                    case JSON_ERROR_CTRL_CHAR:
                        $message .= '(Unexpected control character found).';
                        break;
                    case JSON_ERROR_SYNTAX:
                        $message .= '(Syntax error, malformed JSON): ' . "\n\n" . $this->getResponse()->getContent();
                        break;
                    case JSON_ERROR_UTF8:
                        $message .= '(Malformed UTF-8 characters, possibly incorrectly encoded).';
                        break;
                    default:
                        $message .= '(Unknown error).';
                        break;
                }

                throw new \Exception($message);
            }

            $this->responsePayload = $json;
        }

        return $this->responsePayload;
    }

    private function getProperty($payload, $property)
    {
        foreach (explode('.', $property) as $key) {
            if (is_object($payload)) {
                if (!property_exists($payload, $key)) {
                    throw new \Exception(sprintf('Cannot find the key "%s"', $property));
                }

                $payload = $payload->{$key};

            } elseif (is_array($payload)) {
                if (!array_key_exists($key, $payload)) {
                    throw new \Exception(sprintf('Cannot find the property "%s"', $property));
                }

                $payload = $payload[$key];
            }
        }

        return $payload;
    }

    /**
     * @Given /^the "([^"]*)" property should contain "([^"]*)"$/
     */
    public function thePropertyShouldContain($property, $expectedValue)
    {
        $payload = $this->getResponsePayload();
        $actualValue = $this->getProperty($payload, $property);

        a::assertContains(
            $expectedValue,
            $actualValue,
            "Asserting the [$property] property contains [$expectedValue]: " . json_encode($payload)
        );
    }

    /**
     * @Then /^the "([^"]*)" property should be an array$/
     */
    public function thePropertyIsAnArray($property)
    {
        $payload = $this->getResponsePayload();

        $actualValue = $this->getProperty($payload, $property);

        a::assertTrue(
            is_array($actualValue),
            "Asserting the [$property] property is an array: " . json_encode($payload)
        );
    }

    /**
     * @Then /^the "([^"]*)" property should contain (\d+) item(?:|s)$/
     */
    public function thePropertyContainsItems($property, $count)
    {
        $payload = $this->getResponsePayload();

        a::assertCount(
            (int)$count,
            $this->getProperty($payload, $property),
            "Asserting the [$property] property contains [$count] items: " . json_encode($payload)
        );
    }
    
    /**
     * @Given /^the "([^"]*)" property should contain the item(?:|s):$/
     */
    public function thePropertyShouldContainTheItems($property, TableNode $table)
    {
        $payload = $this->getResponsePayload();
        $actualValue = $this->getProperty($payload, $property);

        foreach ($table->getRows() as $row) {
            $value = $this->replacePlaceholders($row[0]);
            a::assertContains($value, $actualValue);
        }
    }

    /**
     * @Given /^the embedded "([^"]*)" should have a "([^"]*)" property equal to "([^"]*)"$/
     */
    public function theEmbeddedShouldHaveAPropertyEqualTo($embeddedName, $property, $value)
    {
        $this->thePropertyEquals(
            sprintf('_embedded.%s.%s', $embeddedName, $property),
            $value
        );
    }

    protected function buildClient($baseUrl)
    {
        $this->client = $this->get('test.client');
    }

    /** @return ContainerInterface */
    protected function getContainer()
    {
        return $this->kernel->getContainer();
    }

}