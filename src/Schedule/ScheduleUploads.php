<?php

namespace App\Schedule;

use App\Message\CheckOldPendingMessage;
use App\Message\DeleteS3OrphanMessage;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('uploads')]
class ScheduleUploads implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
        private readonly LockFactory $lockFactory,
        private string $checkOldPending,
        private string $deleteS3Orphan,
    ) {}

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            ->stateful($this->cache) // ensure missed tasks are executed
            ->processOnlyLastMissedRun(true) // ensure only last missed task is run
            ->add(
                RecurringMessage::cron($this->checkOldPending, new CheckOldPendingMessage())
            )
            ->add(
                RecurringMessage::cron($this->deleteS3Orphan, new DeleteS3OrphanMessage())
            )
            ->lock($this->lockFactory->createLock('scheduler-file'));
    }
}
