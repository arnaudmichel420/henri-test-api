<?php

namespace App\Repository;

use App\Dto\Task\PullDto;
use App\Entity\Task;
use App\Enum\TaskStateEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
     * @return Task[]
     */
    public function pullTask(PullDto $dto): array
    {
        $this->getEntityManager()->getFilters()->disable('soft_delete');

        $qb = $this->createQueryBuilder('t');

        return $qb->where(
            $qb->expr()->orX(
                't.updatedAt > :updatedAt',
                $qb->expr()->andX(
                    't.updatedAt = :updatedAt',
                    't.id > :id'
                )
            )
        )
            ->setParameter('updatedAt', $dto->updatedAt)
            ->setParameter('id', $dto->id)
            ->addOrderBy('t.updatedAt', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->setMaxResults($dto->limit)
            ->getQuery()
            ->getResult();
    }

    public function findByState(TaskStateEnum $state, int $page, int $perPage): Paginator
    {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.scheduledAt', 'ASC');

        $now = new \DateTimeImmutable();

        match ($state) {
            TaskStateEnum::DONE => $qb->andWhere('t.done = true'),
            TaskStateEnum::LATE => $qb->andWhere('t.done = false')
                ->andWhere('t.scheduledAt < :now')
                ->setParameter('now', $now),
            TaskStateEnum::IN_PROGRESS => $qb->andWhere('t.done = false')
                ->andWhere('t.scheduledAt IS NULL OR t.scheduledAt >= :now')
                ->setParameter('now', $now),
            TaskStateEnum::ALL => null,
        };

        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        return new Paginator($qb);
    }

    /**
     * @return array{all: int, in_progress: int, late: int, done: int}
     */
    public function countByState(): array
    {
        $now = new \DateTimeImmutable();

        $result = $this->createQueryBuilder('t')
            ->select(
                'COUNT(t.id) AS all_count',
                'SUM(CASE WHEN t.done = true THEN 1 ELSE 0 END) AS done_count',
                'SUM(CASE WHEN t.done = false AND t.scheduledAt < :now THEN 1 ELSE 0 END) AS late_count',
                'SUM(CASE WHEN t.done = false AND (t.scheduledAt IS NULL OR t.scheduledAt >= :now) THEN 1 ELSE 0 END) AS in_progress_count',
            )
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleResult();

        return [
            TaskStateEnum::ALL->value => (int) $result['all_count'],
            TaskStateEnum::DONE->value => (int) $result['done_count'],
            TaskStateEnum::LATE->value => (int) $result['late_count'],
            TaskStateEnum::IN_PROGRESS->value => (int) $result['in_progress_count'],
        ];
    }
}
