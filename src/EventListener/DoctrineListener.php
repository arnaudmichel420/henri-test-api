<?php

namespace App\EventListener;

use App\Entity\Task;
use App\Service\MercurePublisherService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class DoctrineListener
{
    private array $changedTasks = [];

    public function __construct(private MercurePublisherService $publisher, private LoggerInterface $logger) {}

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();

        $insert = $uow->getScheduledEntityInsertions();
        $update = $uow->getScheduledEntityUpdates();
        $delete = $uow->getScheduledEntityDeletions();

        foreach ([...$insert, ...$update, ...$delete] as $entity) {
            if ($entity instanceof Task) {
                $this->logger->info('|||||||||||||||||||||||||| DoctrineListener::onFlush triggered for Task', ['id' => (string) $entity->getId()]);
                $this->changedTasks[] = $entity;
            }
        }

        // Gedmo's SoftDeleteable converts a scheduled deletion into an extra update that
        // only touches deletedAt, bypassing #[ORM\PreUpdate] — so updatedAt stays stale and
        // the Mercure checkpoint doesn't advance, making other clients ignore the delete event.
        // Entities still in $delete haven't been marked deleted by Gedmo yet (it may run
        // after us), so isDeleted() can't be trusted there — bump unconditionally.
        foreach ($delete as $entity) {
            if ($entity instanceof Task) {
                $this->bumpUpdatedAt($entity, $uow);
            }
        }
        // Entities already converted to updates by Gedmo (it ran before us) do carry
        // deletedAt, so only bump the ones that are actual soft-deletes.
        foreach ($update as $entity) {
            if ($entity instanceof Task && $entity->isDeleted()) {
                $this->bumpUpdatedAt($entity, $uow);
            }
        }
    }

    private function bumpUpdatedAt(Task $entity, \Doctrine\ORM\UnitOfWork $uow): void
    {
        $oldUpdatedAt = $entity->getUpdatedAt();
        $entity->setUpdatedAt();
        $uow->scheduleExtraUpdate($entity, ['updatedAt' => [$oldUpdatedAt, $entity->getUpdatedAt()]]);
    }

    public function postFlush(): void
    {
        $this->logger->critical('|||||||||||||||||||||||||| DoctrineListener::postFlush triggered', [
            'changedTasks' => array_map(static fn(Task $task) => [
                'id' => (string) $task->getId(),
                'name' => $task->getName(),
                'date' => $task->getDate()?->format('c'),
                'image' => $task->getImage(),
                'deleted' => $task->isDeleted(),
            ], $this->changedTasks),
        ]);

        foreach ($this->changedTasks as $task) {
            $this->publisher->publish($task);
        }
        $this->changedTasks = [];
    }
}
