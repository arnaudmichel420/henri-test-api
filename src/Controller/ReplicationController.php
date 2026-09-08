<?php

namespace App\Controller;

use App\Dto\DocumentStateDto;
use App\Dto\PushDto;
use App\Dto\PullDto;
use App\Entity\Task;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Psr\Log\LoggerInterface;

#[Route('/api/tasks')]
class ReplicationController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em, private LoggerInterface $logger) {}

    #[Route('/pull', methods: ['GET'])]
    public function pull(
        #[MapQueryString] PullDto $pullDto,
        TaskRepository $taskRepository
    ): JsonResponse {
        $documents = $taskRepository->pullTask($pullDto);

        $newCheckpoint = count($documents) === 0 ?
            ['id' => $pullDto->id, 'updatedAt' => $pullDto->updatedAt] :
            ['id' => array_last($documents)->getId(), 'updatedAt' => array_last($documents)->getUpdatedAt()];

        return $this->json(['documents' => $documents, 'checkpoint' => $newCheckpoint], 200, [], ['groups' => ['pull']]);
    }

    #[Route('/push', methods: ['POST'])]
    public function push(
        #[MapRequestPayload(type: PushDto::class)] array $changeRows,
        TaskRepository $taskRepository
    ): JsonResponse {
        //todo ajouter une transaction

        $conflicts = [];
        $ids = array_map(static fn(PushDto $row): Uuid => $row->newDocumentState->id, $changeRows);

        $realMasterStatesById = [];
        foreach ($taskRepository->findBy(["id" => $ids]) as $task) {
            $realMasterStatesById[(string) $task->getId()] = $task;
        }

        foreach ($changeRows as $changeRow) {
            $realMasterState = $realMasterStatesById[(string) $changeRow->newDocumentState->id] ?? null;

            if (!$realMasterState) {
                $this->createTask($changeRow->newDocumentState);
                continue;
            }

            if (
                $this->checkConflict($changeRow->assumedMasterState, $realMasterState)
            ) {
                $this->logger->warning('ReplicationController::push conflict detected', [
                    'assumedMasterState' => $changeRow->assumedMasterState,
                    'realMasterState' => [
                        'id' => (string) $realMasterState->getId(),
                        'name' => $realMasterState->getName(),
                        'date' => $realMasterState->getDate()?->format('c'),
                        'image' => $realMasterState->getImage(),
                        'deleted' => $realMasterState->isDeleted(),
                    ],
                ]);
                $conflicts[] = $realMasterState;
            } else {
                $isDeleted = $changeRow->newDocumentState->deleted;

                if ($isDeleted) {
                    $this->em->remove($realMasterState);
                    continue;
                }

                $this->updateTask($realMasterState, $changeRow->newDocumentState);
            }
        }

        $this->em->flush();

        return $this->json(['conflicts' => $conflicts], 200, [], ['groups' => ['pull']]);
    }

    private function createTask(DocumentStateDto $documentState)
    {
        if ($documentState->deleted) return;

        $task = new Task();
        $task->setId($documentState->id);
        $task->setName($documentState->name);
        $task->setDate($documentState->date);
        $task->setImage($documentState->image);

        $this->em->persist($task);
    }

    private function updateTask(Task $task, DocumentStateDto $documentState)
    {
        $task->setName($documentState->name);
        $task->setDate($documentState->date);
        $task->setImage($documentState->image);
    }

    private function checkConflict(?DocumentStateDto $distantState, Task $localState): bool
    {
        if (!$distantState->id->equals($localState->getId())) return true;
        if ($distantState->name !== $localState->getName()) return true;
        if ($distantState->date?->getTimestamp() !== $localState->getDate()?->getTimestamp()) return true;
        if ($distantState->image !== $localState->getImage()) return true;
        if ($distantState->deleted !== $localState->isDeleted()) return true;

        return false;
    }
}
