<?php

namespace App\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Gedmo\SoftDeleteable\SoftDeleteableListener;

#[AsDoctrineListener(event: SoftDeleteableListener::POST_SOFT_DELETE)]
final class SoftDeleteListener
{
    /**
     * Gedmo's soft delete only touches deletedAt, bypassing #[ORM\PreUpdate] — bump
     * updatedAt manually so the Mercure checkpoint advances and clients see the delete.
     */
    public function postSoftDelete(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        /** @var EntityManagerInterface $em */
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        $oldUpdatedAt = $entity->getUpdatedAt();
        $entity->setUpdatedAt();
        $uow->scheduleExtraUpdate($entity, ['updatedAt' => [$oldUpdatedAt, $entity->getUpdatedAt()]]);
    }
}
