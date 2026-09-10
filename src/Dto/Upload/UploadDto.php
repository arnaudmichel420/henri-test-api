<?php

namespace App\Dto\Upload;

use App\Enum\StatusEnum;
use Symfony\Component\Validator\Constraints as Assert;

final class UploadDto
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 512)]
    public ?string $s3Key = null;

    #[Assert\NotNull]
    public ?StatusEnum $status = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $mimeType = null;

    public ?string $sizeBytes = null;

    #[Assert\Length(max: 255)]
    public ?string $etag = null;
}
