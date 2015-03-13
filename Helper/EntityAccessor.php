<?php

namespace Diside\BehatExtension\Helper;

use Symfony\Component\PropertyAccess\PropertyAccess;

class EntityAccessor
{
    private $obj;

    public function __construct($obj)
    {
        $this->obj = $obj;
    }

    public function __get($field)
    {
        $accessor = PropertyAccess::createPropertyAccessor();

        return $accessor->getValue($this->obj, $field);
    }

}