<?php

namespace Diside\BehatExtension\Helper;

use AppBundle\Entity\Repository\AbstractRepository;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\PropertyAccess\PropertyAccess;

class EntityLookup
{
    /** @var EntityRepository */
    private $repository;

    /** @var string */
    private $field;

    /** @var string */
    private $relation;

    public function __construct($repository, $field, $relation = null)
    {
        $this->repository = $repository;
        $this->field = $field;
        $this->relation = $relation;
    }

    public function get($value)
    {
        if ($value == 'last') {
            // "last" is a special word you can use
            $obj = $this->repository->findLast();

            if (!$obj) {
                throw new \Exception(
                    sprintf(
                        'Cannot find any %s entities',
                        get_class($this->repository)
                    )
                );
            }
        } else {
            if (!is_null($this->relation)) {
                $obj = $this->repository->createQueryBuilder('q')
                    ->leftJoin('q.' . $this->relation, 'r')
                    ->where('r.' . $this->field . ' = :value')
                    ->setParameter('value', $value)
                    ->getQuery()
                    ->getOneOrNullResult()
                ;
            } else {
                $obj = $this->repository->findOneBy(array($this->field => $value));
            }

            if (!$obj) {
                throw new \Exception(
                    sprintf(
                        'Cannot find %s=%s via %s',
                        $this->field,
                        $value,
                        get_class($this->repository)
                    )
                );
            }
        }

        return new EntityAccessor($obj);
    }

    public function __get($value)
    {
        return $this->get($value);
    }

}