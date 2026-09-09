<?php

namespace App\Entity;

use App\Interface\HasIdAndUpdatedAt;
use App\Repository\TaskRepository;
use App\Trait\CreatedAtUpdatedAtEntity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt')]
class Task implements HasIdAndUpdatedAt
{
    use SoftDeleteableEntity;
    use CreatedAtUpdatedAtEntity;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups(['pull'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['pull'])]
    private ?string $name = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['pull'])]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['pull'])]
    private ?string $image = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function setId(Uuid $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(?\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    #[Groups(['pull'])]
    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }
}
