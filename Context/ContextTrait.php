<?php

namespace Diside\BehatExtension\Context;

use Doctrine\ORM\EntityRepository;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\HttpKernel\KernelInterface;

trait ContextTrait
{
    /** @var KernelInterface */
    protected $kernel;

    /**
     * @var string
     */
    protected $contextPath;

    protected function setContextPath($path)
    {
        $this->contextPath = $path;
    }

    protected function getContainer()
    {
        return $this->kernel->getContainer();
    }

    /** @return EntityRepository */
    protected function getRepository($name)
    {
        return $this->get('doctrine.orm.entity_manager')->getRepository($name);
    }

    protected function get($serviceName)
    {
        return $this->kernel->getContainer()->get($serviceName);
    }

    public function setKernel(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    protected function getValue(array $values, $key, $default = null)
    {
        return $this->hasValue($values, $key) ? $values[$key] : $default;
    }

    protected function getIntValue($values, $field, $default = 0)
    {
        return $this->getValue($values, $field, $default);
    }

    protected function getFloatValue(array $values, $key, $default = 0.0)
    {
        return ($this->hasValue($values, $key) && is_numeric($values[$key])) ? floatval($values[$key]) : $default;
    }

    protected function getBoolValue(array $values, $key, $default = true)
    {
        return $this->hasValue($values, $key) ? $values[$key] == "true" : $default;
    }

    protected function getDateValue(array $values, $key, $default = '')
    {
        return new \DateTime($this->hasValue($values, $key) ? $values[$key] : $default);
    }

    protected function hasValue(array $values, $key)
    {
        return isset($values[$key]);
    }

    protected function replacePlaceholders($text)
    {
        /** @var ExpressionLanguage $language */
        $language = $this->getExpressionLanguage();

        $variables = $this->getEntityLookupTables();

        while (false !== $startPos = strpos($text, '%')) {
            $endPos = strpos($text, '%', $startPos + 1);
            if (!$endPos) {
                return $text;
            }
            $expression = substr($text, $startPos + 1, $endPos - $startPos - 1);

            // evaluate the expression
            try {
                $evaluated = $language->evaluate($expression, $variables);
            } catch (SyntaxError $e) {
                $this->printDebug('Error evaluating the following expression:');
                $this->printDebug($expression);

                throw $e;
            }

            if(is_array($evaluated)) {
                $evaluated = implode(';', $evaluated);
            }

            // replace the expression with the final value
            $text = str_replace('%' . $expression . '%', $evaluated, $text);
        }

        return $text;
    }

    protected abstract function getEntityLookupTables();

    protected abstract function getExpressionLanguage();
}
