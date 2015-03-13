<?php

namespace Diside\BehatExtension\Context;

use AppBundle\Entity\Repository\EntityRepository;
use AppBundle\Features\Context\Helper\EntityLookup;
use Doctrine\ORM\EntityManager;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguage;

trait ContextTrait
{
    /** @var KernelInterface */
    protected $kernel;

    /** @return EntityRepository */
    protected function getRepository($name)
    {
        return $this->get('doctrine.orm.entity_manager')->getRepository($name);
    }

    protected function get($serviceName) {
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

    protected function hasValue(array $values, $key)
    {
        return isset($values[$key]);
    }

    protected function replacePlaceholders($text)
    {
        $language = new ExpressionLanguage();

        $variables = $this->getEntityLookupTables();

        while (false !== $startPos = strpos($text, '%')) {
            $endPos = strpos($text, '%', $startPos + 1);
            if (!$endPos) {
                throw new \Exception('Cannot find finishing % - expression look unbalanced!');
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
            // replace the expression with the final value
            $text = str_replace('%' . $expression . '%', $evaluated, $text);
        }

        return $text;
    }

    protected abstract function getEntityLookupTables();

}
