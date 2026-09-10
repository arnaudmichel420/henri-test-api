<?php

namespace App\Entity;

use App\Enum\StatusEnum;
use App\Repository\UploadRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Interface\HasIdAndUpdatedAt;
use App\Trait\CreatedAtUpdatedAtEntity;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\HasLifecycleCallbacks]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt')]
#[ORM\Entity(repositoryClass: UploadRepository::class)]
class Upload implements HasIdAndUpdatedAt
{
    use SoftDeleteableEntity;
    use CreatedAtUpdatedAtEntity;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups(['pull'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'uploads')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['pull'])]
    private ?Task $task = null;

    #[ORM\Column(length: 512)]
    #[Groups(['pull'])]
    private ?string $s3Key = null;

    #[ORM\Column(enumType: StatusEnum::class)]
    #[Groups(['pull'])]
    private ?StatusEnum $status = null;

    #[ORM\Column(length: 255)]
    #[Groups(['pull'])]
    private ?string $mimeType = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    #[Groups(['pull'])]
    private ?string $sizeBytes = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['pull'])]
    private ?string $etag = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getTask(): ?Task
    {
        return $this->task;
    }

    public function setTask(?Task $task): static
    {
        $this->task = $task;

        return $this;
    }

    public function getS3Key(): ?string
    {
        return $this->s3Key;
    }

    public function setS3Key(string $s3Key): static
    {
        $this->s3Key = $s3Key;

        return $this;
    }

    public function getStatus(): ?StatusEnum
    {
        return $this->status;
    }

    public function setStatus(StatusEnum $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSizeBytes(): ?string
    {
        return $this->sizeBytes;
    }

    public function setSizeBytes(?string $sizeBytes): static
    {
        $this->sizeBytes = $sizeBytes;

        return $this;
    }

    public function getEtag(): ?string
    {
        return $this->etag;
    }

    public function setEtag(?string $etag): static
    {
        $this->etag = $etag;

        return $this;
    }
    
    #[Groups(['pull'])]
    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }
}
