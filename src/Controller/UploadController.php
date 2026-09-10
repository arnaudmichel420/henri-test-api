<?php

namespace App\Controller;

use App\Dto\Upload\DocumentStateDto;
use App\Dto\Upload\PullDto;
use App\Dto\Upload\PushDto;
use App\Dto\Upload\UploadDto;
use App\Entity\Task;
use App\Entity\Upload;
use App\Enum\StatusEnum;
use App\Repository\TaskRepository;
use App\Repository\UploadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

class UploadController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/api/tasks/{taskId}/uploads', methods: ['GET'])]
    public function list(#[MapEntity(id: 'taskId')] Task $task): JsonResponse
    {
        return $this->json($task->getUploads());
    }

    #[Route('/api/tasks/{taskId}/uploads/{id}', methods: ['GET'])]
    public function show(Upload $upload): JsonResponse
    {
        return $this->json($upload);
    }

    #[Route('/api/tasks/{taskId}/uploads', methods: ['POST'])]
    public function create(#[MapEntity(id: 'taskId')] Task $task, #[MapRequestPayload] UploadDto $input): JsonResponse
    {
        $upload = new Upload();
        $upload->setTask($task);
        $this->updateUpload($upload, $input->s3Key, $input->status, $input->mimeType, $input->sizeBytes, $input->etag);

        $this->em->persist($upload);
        $this->em->flush();

        return $this->json($upload, 201);
    }

    #[Route('/api/tasks/{taskId}/uploads/{id}', methods: ['PUT'])]
    public function update(Upload $upload, #[MapRequestPayload] UploadDto $input): JsonResponse
    {
        $this->updateUpload($upload, $input->s3Key, $input->status, $input->mimeType, $input->sizeBytes, $input->etag);
        $this->em->flush();

        return $this->json($upload);
    }

    #[Route('/api/tasks/{taskId}/uploads/{id}', methods: ['DELETE'])]
    public function delete(Upload $upload): JsonResponse
    {
        $this->em->remove($upload);
        $this->em->flush();

        return $this->json(null, 204);
    }

    #[Route('/api/uploads/pull', methods: ['GET'])]
    public function pull(
        #[MapQueryString] PullDto $pullDto,
        UploadRepository $uploadRepository
    ): JsonResponse {
        $documents = $uploadRepository->pullUpload($pullDto);

        $newCheckpoint = count($documents) === 0 ?
            ['id' => $pullDto->id, 'updatedAt' => $pullDto->updatedAt] :
            ['id' => array_last($documents)->getId(), 'updatedAt' => array_last($documents)->getUpdatedAt()];

        return $this->json(['documents' => $documents, 'checkpoint' => $newCheckpoint], 200, [], ['groups' => ['pull']]);
    }

    #[Route('/api/uploads/push', methods: ['POST'])]
    public function push(
        #[MapRequestPayload(type: PushDto::class)] array $changeRows,
        UploadRepository $uploadRepository,
        TaskRepository $taskRepository
    ): JsonResponse {
        $this->em->getFilters()->disable('soft_delete');

        $conflicts = [];
        $ids = array_map(static fn(PushDto $row): Uuid => $row->newDocumentState->id, $changeRows);

        $realMasterStatesById = [];
        foreach ($uploadRepository->findBy(["id" => $ids]) as $upload) {
            $realMasterStatesById[(string) $upload->getId()] = $upload;
        }

        $this->em->wrapInTransaction(function () use ($changeRows, $realMasterStatesById, $taskRepository, &$conflicts): void {
            foreach ($changeRows as $changeRow) {
                $realMasterState = $realMasterStatesById[(string) $changeRow->newDocumentState->id] ?? null;

                if (!$realMasterState) {
                    $this->createUpload($changeRow->newDocumentState, $taskRepository);
                    continue;
                }

                if (
                    $this->checkConflict($changeRow->assumedMasterState, $realMasterState)
                ) {
                    $conflicts[] = $realMasterState;
                } else {
                    $isDeleted = $changeRow->newDocumentState->deleted;

                    if ($isDeleted) {
                        $this->em->remove($realMasterState);
                        continue;
                    }

                    $this->updateUpload(
                        $realMasterState,
                        $changeRow->newDocumentState->s3Key,
                        $changeRow->newDocumentState->status,
                        $changeRow->newDocumentState->mimeType,
                        $changeRow->newDocumentState->sizeBytes,
                        $changeRow->newDocumentState->etag,
                    );
                }
            }
        });

        return $this->json(['conflicts' => $conflicts], 200, [], ['groups' => ['pull']]);
    }

    private function createUpload(DocumentStateDto $documentState, TaskRepository $taskRepository)
    {
        if ($documentState->deleted) return;

        $task = $taskRepository->find($documentState->taskId);
        if (!$task) return;

        $upload = new Upload();
        $upload->setId($documentState->id);
        $upload->setTask($task);
        $this->updateUpload($upload, $documentState->s3Key, $documentState->status, $documentState->mimeType, $documentState->sizeBytes, $documentState->etag);

        $this->em->persist($upload);
    }

    private function updateUpload(Upload $upload, string $s3Key, StatusEnum $status, string $mimeType, ?string $sizeBytes, ?string $etag): void
    {
        $upload->setS3Key($s3Key);
        $upload->setStatus($status);
        $upload->setMimeType($mimeType);
        $upload->setSizeBytes($sizeBytes);
        $upload->setEtag($etag);
    }

    private function checkConflict(?DocumentStateDto $distantState, Upload $localState): bool
    {
        if (!$distantState->id->equals($localState->getId())) return true;
        if ($distantState->s3Key !== $localState->getS3Key()) return true;
        if ($distantState->status !== $localState->getStatus()) return true;
        if ($distantState->mimeType !== $localState->getMimeType()) return true;
        if ($distantState->sizeBytes !== $localState->getSizeBytes()) return true;
        if ($distantState->etag !== $localState->getEtag()) return true;
        if ($distantState->deleted !== $localState->isDeleted()) return true;

        return false;
    }
}
