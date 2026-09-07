<?php

namespace App\Controller;

use App\Dto\PushDto;
use App\Dto\PullDto;
use App\Entity\Task;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/tasks')]
class ReplicationController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

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
        // $lastEventId = 0;

        $conflicts = [];

        // $event = [
        //     "id" => $lastEventId++,
        //     "documents" => [],
        //     "checkpoint" => null
        // ];

        foreach ($changeRows as $changeRow) {
            $realMasterState = $taskRepository->find($changeRow->newDocumentState->id);

            if (!$realMasterState) {
                if ($changeRow->newDocumentState->deleted) continue;

                $task = new Task();
                $task->setId($changeRow->newDocumentState->id);
                $task->setName($changeRow->newDocumentState->name);
                $task->setDate($changeRow->newDocumentState->date);
                $task->setImage($changeRow->newDocumentState->image);

                $this->em->persist($task);

                // $event["documents"][] = $realMasterState;
                // $event["checkpoint"] = ['id' => $task->getId(), 'updatedAt' => $task->getUpdatedAt()];

                continue;
            }

            if (
                !$changeRow->assumedMasterState ||
                (
                    $changeRow->assumedMasterState &&
                    $realMasterState->getName() !== $changeRow->assumedMasterState->name)
            ) {
                $conflicts[] = $realMasterState;
            } else {
                if ($changeRow->newDocumentState->deleted) {
                    $this->em->remove($realMasterState);
                    continue;
                }

                $realMasterState->setName($changeRow->newDocumentState->name);
                $realMasterState->setDate($changeRow->newDocumentState->date);
                $realMasterState->setImage($changeRow->newDocumentState->image);

                // $event["documents"][] = $realMasterState;
                // $event["checkpoint"] = ['id' => $realMasterState->getId(), 'updatedAt' => $realMasterState->getUpdatedAt()];
            }
        }

        // if (count($event["documents"]) > 0) {
        //     //myPullStream$.next(event);
        // }

        $this->em->flush();

        return $this->json(['conflicts' => $conflicts], 200, [], ['groups' => ['pull']]);
    }
}
