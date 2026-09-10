<?php

namespace App\Dto\Upload;

use App\Enum\StatusEnum;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

final class DocumentStateDto
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly Uuid $id,
        #[Assert\NotBlank]
        public readonly Uuid $taskId,
        #[Assert\NotBlank]
        #[Assert\Length(max: 512)]
        public readonly string $s3Key,
        #[Assert\NotNull]
        public readonly StatusEnum $status,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public readonly string $mimeType,
        public readonly ?string $sizeBytes = null,
        #[Assert\Length(max: 255)]
        public readonly ?string $etag = null,
        public readonly bool $deleted = false,
    ) {}
}
