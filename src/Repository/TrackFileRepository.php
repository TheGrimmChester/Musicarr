<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TrackFile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrackFile>
 *
 * @method TrackFile|null find($id, $lockMode = null, $lockVersion = null)
 * @method TrackFile|null findOneBy(array $criteria, array $orderBy = null)
 * @method TrackFile[]    findAll()
 * @method TrackFile[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TrackFileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrackFile::class);
    }

    public function save(TrackFile $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TrackFile $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find a TrackFile by its filepath.
     */
    public function findByFilePath(string $filePath): ?TrackFile
    {
        return $this->findOneBy(['filePath' => $filePath]);
    }

    /**
     * Find all files for a track ordered by quality preference.
     */
    public function findByTrackOrderedByQuality(int $trackId): array
    {
        return $this->createQueryBuilder('tf')
            ->andWhere('tf.track = :trackId')
            ->setParameter('trackId', $trackId)
            ->orderBy('tf.quality', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find TrackFile entities by an array of IDs.
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->createQueryBuilder('tf')
            ->where('tf.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find TrackFile entities that need renaming with filtering and pagination.
     */
    public function findFilteredFilesForRenaming(
        int $page = 1,
        int $limit = 50,
        string $search = '',
        string $artistFilter = '',
        string $albumFilter = '',
        string $titleFilter = ''
    ): array {
        $qb = $this->createQueryBuilder('tf')
            ->join('tf.track', 't')
            ->join('t.album', 'a')
            ->join('a.artist', 'ar')
            ->andWhere('tf.needRename = true')
            ->andWhere('tf.filePath IS NOT NULL')
            ->andWhere('tf.filePath != :emptyString')
            ->setParameter('emptyString', '');

        // Apply search filter
        if (!empty($search)) {
            $qb->andWhere('(ar.name LIKE :search OR a.title LIKE :search OR t.title LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        // Apply artist filter
        if (!empty($artistFilter)) {
            $qb->andWhere('ar.name LIKE :artistFilter')
                ->setParameter('artistFilter', '%' . $artistFilter . '%');
        }

        // Apply album filter
        if (!empty($albumFilter)) {
            $qb->andWhere('a.title LIKE :albumFilter')
                ->setParameter('albumFilter', '%' . $albumFilter . '%');
        }

        // Apply title filter
        if (!empty($titleFilter)) {
            $qb->andWhere('t.title LIKE :titleFilter')
                ->setParameter('titleFilter', '%' . $titleFilter . '%');
        }

        // Get total count for pagination
        $countQb = clone $qb;
        $total = $countQb->select('COUNT(tf.id)')->getQuery()->getSingleScalarResult();

        // Apply pagination and ordering
        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->orderBy('ar.name', 'ASC')
            ->addOrderBy('a.title', 'ASC')
            ->addOrderBy('t.trackNumber', 'ASC');

        $files = $qb->getQuery()->getResult();

        return [
            'files' => $files,
            'total' => $total,
        ];
    }
}
