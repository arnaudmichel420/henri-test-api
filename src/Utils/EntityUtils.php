<?php

namespace App\Utils;

use App\Interface\HasIdAndUpdatedAt;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\MappingException;
use Symfony\Component\String\Inflector\EnglishInflector;

final class EntityUtils
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Returns Doctrine entity name
     */
    public function getEntityName(object $entity): string
    {
        try {
            $shortName = $this->em->getClassMetadata(get_class($entity))->getReflectionClass()->getShortName();
            $entityName = (new EnglishInflector())->pluralize($shortName)[0];
        } catch (MappingException $e) {
            throw new \Exception('Given object ' . get_class($entity) . ' is not a Doctrine Entity. ');
        }

        return $entityName;
    }
}
