<?php

namespace App\Controller;

use App\Dto\Task\TaskDto;
use App\Entity\Task;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use App\Dto\Task\DocumentStateDto;
use App\Dto\Task\PullDto;
use App\Dto\Task\PushDto;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Uid\Uuid;

#[Route('/api/tasks')]
class TaskController extends AbstractController
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly EntityManagerInterface $em,
    ) {}

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
        $this->em->getFilters()->disable('soft_delete');

        $conflicts = [];
        $ids = array_map(static fn(PushDto $row): Uuid => $row->newDocumentState->id, $changeRows);

        $realMasterStatesById = [];
        foreach ($taskRepository->findBy(["id" => $ids]) as $task) {
            $realMasterStatesById[(string) $task->getId()] = $task;
        }

        $this->em->wrapInTransaction(function () use ($changeRows, $realMasterStatesById, &$conflicts): void {
            foreach ($changeRows as $changeRow) {
                $realMasterState = $realMasterStatesById[(string) $changeRow->newDocumentState->id] ?? null;

                if (!$realMasterState) {
                    $this->createTask($changeRow->newDocumentState);
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

                    $this->updateTask($realMasterState, $changeRow->newDocumentState->name, $changeRow->newDocumentState->date);
                }
            }
        });

        return $this->json(['conflicts' => $conflicts], 200, [], ['groups' => ['pull']]);
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->taskRepository->findAll());
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(Task $task): JsonResponse
    {
        return $this->json($task);
    }

    #[Route('', methods: ['POST'])]
    public function create(#[MapRequestPayload] TaskDto $input): JsonResponse
    {
        $task = new Task();
        $this->updateTask($task, $input->name, $input->date);

        $this->em->persist($task);
        $this->em->flush();

        return $this->json($task, 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(Task $task, #[MapRequestPayload] TaskDto $input): JsonResponse
    {
        $this->updateTask($task, $input->name, $input->date);
        $this->em->flush();

        return $this->json($task);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(Task $task): JsonResponse
    {
        $this->em->remove($task);
        $this->em->flush();

        return $this->json(null, 204);
    }

    private function createTask(DocumentStateDto $documentState)
    {
        if ($documentState->deleted) return;

        $task = new Task();
        $task->setId($documentState->id);
        $this->updateTask($task, $documentState->name, $documentState->date);

        $this->em->persist($task);
    }

    private function updateTask(Task $task, string $name, ?\DateTimeImmutable $date): void
    {
        $task->setName($name);
        $task->setDate($date);
    }

    private function checkConflict(?DocumentStateDto $distantState, Task $localState): bool
    {
        if (!$distantState->id->equals($localState->getId())) return true;
        if ($distantState->name !== $localState->getName()) return true;
        if ($distantState->date?->getTimestamp() !== $localState->getDate()?->getTimestamp()) return true;
        if ($distantState->deleted !== $localState->isDeleted()) return true;

        return false;
    }
}
