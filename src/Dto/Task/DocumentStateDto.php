<?php

namespace App\Dto\Task;

use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

final class DocumentStateDto
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly Uuid $id,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public readonly string $name,
        public readonly ?\DateTimeImmutable $scheduledAt = null,
        public readonly ?\DateTimeImmutable $doneAt = null,
        public readonly bool $done = false,
        #[Assert\Length(max: 255)]
        public readonly ?string $image = null,
        public readonly bool $deleted = false,
    ) {}
}
