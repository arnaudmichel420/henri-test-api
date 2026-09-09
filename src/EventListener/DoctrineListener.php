<?php

namespace App\EventListener;

use App\Entity\Task;
use App\Service\MercurePublisherService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class DoctrineListener
{
    private array $changedTasks = [];

    public function __construct(private MercurePublisherService $publisher) {}

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();

        $insert = $uow->getScheduledEntityInsertions();
        $update = $uow->getScheduledEntityUpdates();
        $delete = $uow->getScheduledEntityDeletions();

        foreach ([...$insert, ...$update, ...$delete] as $entity) {
            if ($entity instanceof Task) {
                $this->changedTasks[] = $entity;
            }
        }
    }

    public function postFlush(): void
    {
        foreach ($this->changedTasks as $task) {
            $this->publisher->publish($task);
        }
        $this->changedTasks = [];
    }
}
