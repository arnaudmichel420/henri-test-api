<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class TaskDto
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    public ?\DateTimeImmutable $date = null;

    #[Assert\Length(max: 255)]
    public ?string $image = null;
}
