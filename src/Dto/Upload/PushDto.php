<?php

namespace App\Dto\Upload;

use Symfony\Component\Validator\Constraints as Assert;

final class PushDto
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Valid]
        public readonly DocumentStateDto $newDocumentState,
        #[Assert\Valid]
        public readonly ?DocumentStateDto $assumedMasterState = null,
    ) {}
}
