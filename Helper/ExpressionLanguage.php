<?php

namespace Diside\BehatExtension\Helper;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage as BaseExpressionLanguage;
use Symfony\Component\ExpressionLanguage\ParserCache\ParserCacheInterface;

class ExpressionLanguage extends BaseExpressionLanguage
{
    public function __construct(ParserCacheInterface $cache = null, array $providers = array())
    {
        parent::__construct($cache, $providers);

        $this->register('date', function ($date = null, $format = 'Y-m-d') {
            return sprintf('(new \DateTime(%s))', $date);
        }, function (array $values, $date = null, $format = 'Y-m-d') {
            $date = new \DateTime($date);

            return $date->format($format);
        });

        $this->register('datetime', function ($date = null, $format = 'Y-m-d H:i:s') {
            return sprintf('(new \DateTime(%s))', $date);
        }, function (array $values, $date = null, $format = 'Y-m-d H:i:s') {
            $date = new \DateTime($date);

            return $date->format($format);
        });

        $this->register('now', function ($format = 'Y-m-d H:i:s') {
            return sprintf('(new \DateTime(%s))');
        }, function (array $values, $format = 'Y-m-d H:i:s') {
            $date = new \DateTime;

            return $date->format($format);
        });
    }
}
