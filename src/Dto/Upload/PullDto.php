<?php

namespace App\Dto\Upload;

use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

final class PullDto
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly Uuid $id,
        #[Assert\NotBlank]
        public readonly int $limit,
        #[Assert\NotBlank]
        public readonly \DateTimeImmutable $updatedAt,

    ) {}
}
