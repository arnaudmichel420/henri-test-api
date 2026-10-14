<?php

namespace App\Dto\Task;

use App\Enum\TaskStateEnum;
use Symfony\Component\Validator\Constraints as Assert;

final class TaskListDto
{
    public function __construct(
        public readonly TaskStateEnum $state = TaskStateEnum::ALL,
        #[Assert\Positive]
        public readonly int $page = 1,
        #[Assert\Positive]
        #[Assert\LessThanOrEqual(100)]
        public readonly int $perPage = 20,
    ) {}
}
