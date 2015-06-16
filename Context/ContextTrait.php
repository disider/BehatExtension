<?php

namespace Diside\BehatExtension\Context;

use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use InvalidArgumentException;
use PSS\Behat\Symfony2MockerExtension\ServiceMocker;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguage;

trait ContextTrait
{
    /** @var KernelInterface */
    protected $kernel;

    /** @var ServiceMocker */
    protected $mocker = null;

    /**
     * @var string
     */
    protected $contextPath;

    protected function setContextPath($path)
    {
        $this->contextPath = $path;
    }

    /** @BeforeScenario */
    public function purgeDatabase()
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getContainer()->get('doctrine.orm.entity_manager');

        $purger = new ORMPurger($entityManager);
        $purger->purge();
    }

    public function setServiceMocker(ServiceMocker $mocker)
    {
        $this->mocker = $mocker;
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

    protected function hasValue(array $values, $key)
    {
        return isset($values[$key]);
    }

    protected function replacePlaceholders($text)
    {
        $language = new ExpressionLanguage();
        $language->register('md5', function ($path) {
            return sprintf('(md5(%1$s))', $path);
        }, function ($arguments, $path) {
            if (!$this->contextPath)
                throw new InvalidArgumentException('Base file path not set. Call setContextPath() with a valid file path.');

            $path = $this->contextPath . '/' . $path;

            if(!is_file($path))
                throw new \Exception('Undefined file: ' . $path);

            return md5(file_get_contents($path));
        });
        $language->register('get_file', function ($path) {
            return sprintf('(file_get_contents(%1$s))', $path);
        }, function ($arguments, $path) {
            if (!$this->contextPath)
                throw new InvalidArgumentException('Base file path not set. Call setContextPath() with a valid file path.');

            $path = $this->contextPath . '/' . $path;

            if(!is_file($path))
                throw new \Exception('Undefined file: ' . $path);

            return base64_encode(file_get_contents($path));
        });

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
