<?php

namespace App\Repository;

use App\Dto\PullDto;
use App\Entity\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
}
