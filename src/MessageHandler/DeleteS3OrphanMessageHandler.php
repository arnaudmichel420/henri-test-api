<?php

namespace App\MessageHandler;

use App\Message\DeleteS3OrphanMessage;
use App\Service\UploadService;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class DeleteS3OrphanMessageHandler
{
    public function __construct(private LockFactory $lockFactory, private UploadService $uploadService) {}

    public function __invoke(DeleteS3OrphanMessage $message): void
    {
        //lock global métier
        $lock = $this->lockFactory->createLock(
            'delete_s3_orphan',
            600 // TTL en secondes
        );

        if (!$lock->acquire()) {
            // Un autre worker est déjà en train d’exécuter le job
            return;
        }

        try {
            $this->uploadService->deleteS3Orphan();
        } finally {
            $lock->release();
        }
    }
}
