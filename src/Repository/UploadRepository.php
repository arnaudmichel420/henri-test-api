<?php

namespace App\Repository;

use App\Dto\Upload\PullDto;
use App\Entity\Upload;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Upload>
 */
class UploadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Upload::class);
    }

    /**
     * @return Upload[]
     */
    public function pullUpload(PullDto $dto): array
    {
        $this->getEntityManager()->getFilters()->disable('soft_delete');

        $qb = $this->createQueryBuilder('u');

        return $qb->where(
            $qb->expr()->orX(
                'u.updatedAt > :updatedAt',
                $qb->expr()->andX(
                    'u.updatedAt = :updatedAt',
                    'u.id > :id'
                )
            )
        )
            ->setParameter('updatedAt', $dto->updatedAt)
            ->setParameter('id', $dto->id)
            ->addOrderBy('u.updatedAt', 'ASC')
            ->addOrderBy('u.id', 'ASC')
            ->setMaxResults($dto->limit)
            ->getQuery()
            ->getResult();
    }
}
