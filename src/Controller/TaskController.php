<?php

namespace App\Controller;

use App\Dto\TaskDto;
use App\Entity\Task;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/tasks')]
class TaskController extends AbstractController
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly EntityManagerInterface $em,
    ) {}

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
        $task->setName($input->name);
        $task->setDate($input->date);
        $task->setImage($input->image);

        $this->em->persist($task);
        $this->em->flush();

        return $this->json($task, 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(Task $task, #[MapRequestPayload] TaskDto $input): JsonResponse
    {
        $task->setName($input->name);
        $task->setDate($input->date);
        $task->setImage($input->image);
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
}
