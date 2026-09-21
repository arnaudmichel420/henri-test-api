<?php

namespace App\EventListener;

use App\Entity\Task;
use App\Entity\Upload;
use App\Service\MercurePublisherService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class DoctrineListener
{
    private array $changedEntities = [];

    public function __construct(private MercurePublisherService $publisher) {}

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();

        $insert = $uow->getScheduledEntityInsertions();
        $update = $uow->getScheduledEntityUpdates();
        $delete = $uow->getScheduledEntityDeletions();

        foreach ([...$insert, ...$update, ...$delete] as $entity) {
            if ($entity instanceof Task || $entity instanceof Upload) {
                $this->changedEntities[] = $entity;
            }
        }
    }

    public function postFlush(): void
    {
        foreach ($this->changedEntities as $entity) {
            $this->publisher->publish($entity);
        }
        $this->changedEntities = [];
    }
}
