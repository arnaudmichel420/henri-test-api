<?php

namespace App\Entity;

use App\Interface\HasIdAndUpdatedAt;
use App\Repository\TaskRepository;
use App\Trait\CreatedAtUpdatedAtEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    /**
     * @var Collection<int, Upload>
     */
    #[ORM\OneToMany(targetEntity: Upload::class, mappedBy: 'task', orphanRemoval: true)]
    private Collection $uploads;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->uploads = new ArrayCollection();
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

    #[Groups(['pull'])]
    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }

    /**
     * @return Collection<int, Upload>
     */
    public function getUploads(): Collection
    {
        return $this->uploads;
    }

    public function addUpload(Upload $upload): static
    {
        if (!$this->uploads->contains($upload)) {
            $this->uploads->add($upload);
            $upload->setTask($this);
        }

        return $this;
    }

    public function removeUpload(Upload $upload): static
    {
        if ($this->uploads->removeElement($upload)) {
            // set the owning side to null (unless already changed)
            if ($upload->getTask() === $this) {
                $upload->setTask(null);
            }
        }

        return $this;
    }
}
