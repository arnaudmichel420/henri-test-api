<?php

namespace App\Service;

use App\Interface\HasIdAndUpdatedAt;
use App\Utils\EntityUtils;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerInterface;

final class MercurePublisherService
{
    public function __construct(private HubInterface $hub, private SerializerInterface $serializer, private EntityUtils $entityUtils, private LoggerInterface $logger) {}

    public function publish(HasIdAndUpdatedAt $entity): void
    {
        $entityName = strtolower($this->entityUtils->getEntityName($entity));
        $this->logger->critical("entityName", [$entityName]);

        $payload = [
            'documents' => [json_decode($this->serializer->serialize($entity, 'json', ['groups' => ['pull']]), true)],
            'checkpoint' => ['id' => (string) $entity->getId(), 'updatedAt' => $entity->getUpdatedAt()?->format('Y-m-d\TH:i:s.uP')],
        ];

        $this->hub->publish(new Update(
            topics: $entityName,
            data: json_encode($payload),
        ));
    }
}