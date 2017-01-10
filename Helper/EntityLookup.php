<?php

namespace Diside\BehatExtension\Helper;

use AppBundle\Entity\Repository\AbstractRepository;
use Doctrine\Common\Collections\ArrayCollection;
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
        if ($value == 'last' || $value == 'first') {
            // "last" is a special word you can use
            $method = 'find' . ucfirst($value);
            $obj = $this->repository->$method();

            if (!$obj) {
                throw new \Exception(
                    sprintf(
                        'Cannot find any %s entities',
                        get_class($this->repository)
                    )
                );
            }

        } else if (preg_match('/nth\d+/i', $value)) {
            $index = str_replace('nth', '', $value) - 1;
            $collection = $this->repository->findAll();

            if (array_key_exists($index, $collection)) {
                $obj = $collection[$index];
            } else {
                throw new \Exception(
                    sprintf(
                        'Cannot find nth%s via %s',
                        $index,
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
                    ->getOneOrNullResult();
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