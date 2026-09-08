<?php

namespace App\Service;

use App\Entity\Task;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerInterface;

final class MercurePublisherService
{
    public function __construct(private HubInterface $hub, private SerializerInterface $serializer, private string $mercurePublicUrl, private LoggerInterface $logger) {}

    public function publish(Task $task): void
    {
        $payload = [
            'documents' => [json_decode($this->serializer->serialize($task, 'json', ['groups' => ['pull']]), true)],
            'checkpoint' => ['id' => (string) $task->getId(), 'updatedAt' => $task->getUpdatedAt()?->format('Y-m-d\TH:i:s.uP')],
        ];

        $this->logger->info('MercurePublisherService hub url', ['publicUrl' => $this->hub->getPublicUrl()]);

        try {
            $this->hub->publish(new Update(
                topics: 'tasks',
                data: json_encode($payload),
            ));
        } catch (\Throwable $e) {
            $this->logger->error('Mercure publish failed', ['message' => $e->getMessage(), 'previous' => $e->getPrevious()?->getMessage()]);
            throw $e;
        }
    }
}
// 3. Côté client (juste pour situer la frontière)

//   RxDB attend un Observable en pull.stream$. Le téléphone crée un EventSource pointant vers
//   MERCURE_PUBLIC_URL?topic=https://example.com/tasks avec le JWT subscriber, transforme chaque message SSE (déjà au format
//   {documents, checkpoint}) en next() de ce stream. Aucun code Symfony supplémentaire n'est nécessaire ici — Mercure fait le
//   pont.

//   Ce qui manque encore pour que ce soit complet : la config mercure.yaml (topic autorisé, JWT claims), et gérer le cas où le
//   listener publie mais que la transaction échoue ensuite (rare avec postFlush mais pas impossible avec des listeners suivants)
//   — actuellement hors scope de ce que tu as décrit.

//   {documents, checkpoint}) en next() de ce stream. Aucun code Symfony supplémentaire n'est nécessaire ici — Mercure fait le
//   pont.