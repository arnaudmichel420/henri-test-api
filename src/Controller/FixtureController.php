<?php

namespace App\Controller;

use App\Entity\Task;
use App\Entity\Upload;
use App\Enum\StatusEnum;
use App\Repository\TaskRepository;
use App\Repository\UploadRepository;
use App\Service\S3;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/fixtures')]
class FixtureController extends AbstractController
{
    private const TOTAL_TASKS = 20;

    private const TASK_NAMES = [
        'Installer le tableau domotique du salon',
        'Configurer les prises connectées de la cuisine',
        "Poser l'interrupteur connecté du couloir",
        'Installer la borne de recharge véhicule électrique',
        'Paramétrer la VMC connectée',
        "Mettre en service le tableau électrique intelligent",
        'Installer le portail motorisé connecté',
        'Déployer les éclairages connectés extérieurs',
        'Programmer les ampoules connectées du garage',
        'Installer le détecteur de fumée connecté',
        'Configurer les volets roulants connectés',
        'Installer le thermostat connecté',
        'Poser les capteurs de gouttières connectés',
        "Mettre en service le système d'alarme connecté",
        'Configurer la chaudière connectée',
        'Installer les capteurs de fenêtre connectés',
        "Câbler l'accès au local technique",
        'Paramétrer le hub domotique central',
        'Installer les détecteurs de fumée connectés',
        'Faire la mise en service annuelle du système domotique',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TaskRepository $taskRepository,
        private readonly UploadRepository $uploadRepository,
        private readonly S3 $s3,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {}

    #[Route('/load', methods: ['POST'])]
    public function load(): JsonResponse
    {
        $this->reset();
        $count = $this->createTasks();

        return $this->json(['tasks_created' => $count]);
    }

    private function reset(): void
    {
        foreach ($this->uploadRepository->findAll() as $upload) {
            $this->em->remove($upload);
        }
        foreach ($this->taskRepository->findAll() as $task) {
            $this->em->remove($task);
        }
        $this->em->flush();

        $keys = array_keys($this->s3->getAllS3KeyWithDateInBucket());
        if ($keys) {
            $this->s3->removeFileByKey($keys);
        }
    }

    private function createTasks(): int
    {
        $count = 0;

        foreach (glob($this->projectDir.'/public/fixture/done/*') as $imagePath) {
            $task = $this->buildTask(
                self::TASK_NAMES[$count++],
                $this->randomDate(-7, 0),
                done: true,
                doneAt: $this->randomDate(-7, 0),
            );
            $this->attachImage($task, $imagePath);
            $this->em->flush();
        }

        foreach (glob($this->projectDir.'/public/fixture/late/*') as $imagePath) {
            $task = $this->buildTask(
                self::TASK_NAMES[$count++],
                $this->randomDate(-7, -1),
                done: false,
                doneAt: null,
            );
            $this->attachImage($task, $imagePath);
            $this->em->flush();
        }

        while ($count < self::TOTAL_TASKS) {
            $this->buildTask(
                self::TASK_NAMES[$count++],
                $this->randomDate(0, 7),
                done: false,
                doneAt: null,
            );
            $this->em->flush();
        }

        return $count;
    }

    private function randomDate(int $minDays, int $maxDays): \DateTimeImmutable
    {
        return (new \DateTimeImmutable())
            ->modify(random_int($minDays, $maxDays).' days')
            ->setTime(random_int(0, 23), random_int(0, 59), random_int(0, 59));
    }

    private function buildTask(string $name, \DateTimeImmutable $scheduledAt, bool $done, ?\DateTimeImmutable $doneAt): Task
    {
        $task = new Task();
        $task->setName($name);
        $task->setScheduledAt($scheduledAt);
        $task->setDone($done);
        $task->setDoneAt($doneAt);

        $this->em->persist($task);

        return $task;
    }

    private function attachImage(Task $task, string $imagePath): void
    {
        $key = 'uploads/'.$task->getId().'-'.basename($imagePath);
        $result = $this->s3->putObject($key, $imagePath, 'image/webp');

        $upload = new Upload();
        $upload->setTask($task);
        $upload->setS3Key($key);
        $upload->setStatus(StatusEnum::UPLOADED);
        $upload->setMimeType('image/webp');
        $upload->setSizeBytes((string) filesize($imagePath));
        $upload->setEtag(trim((string) ($result['ETag'] ?? ''), '"'));

        $this->em->persist($upload);
    }
}
