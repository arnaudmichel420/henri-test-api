<?php

namespace App\MessageHandler;

use App\Message\CheckOldPendingMessage;
use App\Repository\UploadRepository;
use App\Service\S3;
use App\Service\UploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CheckOldPendingMessageHandler
{
    public function __construct(private LockFactory $lockFactory, private UploadService $uploadService) {}

    public function __invoke(CheckOldPendingMessage $message): void
    {
        //lock global métier
        $lock = $this->lockFactory->createLock(
            'check_old_pending',
            600 // TTL en secondes
        );

        if (!$lock->acquire()) {
            // Un autre worker est déjà en train d’exécuter le job
            return;
        }

        try {
            $this->uploadService->checkIfFilesWereUploaded();
        } finally {
            $lock->release();
        }
    }
}
