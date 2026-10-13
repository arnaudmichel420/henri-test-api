<?php

namespace App\Dto\Task;

use Symfony\Component\Validator\Constraints as Assert;

final class TaskDto
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    public ?\DateTimeImmutable $scheduledAt = null;

    public ?\DateTimeImmutable $doneAt = null;

    public bool $done = false;

    #[Assert\Length(max: 255)]
    public ?string $image = null;
}
