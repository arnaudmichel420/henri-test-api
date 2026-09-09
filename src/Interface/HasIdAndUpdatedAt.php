<?php

namespace App\Interface;

use Symfony\Component\Uid\Uuid;

interface HasIdAndUpdatedAt
{
    public function getId(): ?Uuid;
    public function getUpdatedAt(): ?\DateTimeImmutable;
}
