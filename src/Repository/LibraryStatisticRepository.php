<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Library;
use App\Entity\LibraryStatistic;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LibraryStatistic>
 */
class LibraryStatisticRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LibraryStatistic::class);
    }

    /**
     * Find statistics for a specific library.
     */
    public function findByLibrary(Library $library): ?LibraryStatistic
    {
        return $this->findOneBy(['library' => $library]);
    }

    /**
     * Find statistics for a library by ID.
     */
    public function findByLibraryId(int $libraryId): ?LibraryStatistic
    {
        return $this->createQueryBuilder('ls')
            ->join('ls.library', 'l')
            ->where('l.id = :libraryId')
            ->setParameter('libraryId', $libraryId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all statistics that are stale (older than specified minutes).
     */
    public function findStaleStatistics(int $maxAgeMinutes = 60): array
    {
        $threshold = new DateTime();
        $threshold->modify("-{$maxAgeMinutes} minutes");

        /** @var LibraryStatistic[] $result */
        $result = $this->createQueryBuilder('ls')
            ->where('ls.updatedAt < :threshold OR ls.updatedAt IS NULL')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Create or update statistics for a library.
     */
    public function createOrUpdate(Library $library, array $stats): LibraryStatistic
    {
        $statistic = $this->findByLibrary($library);

        if (!$statistic) {
            $statistic = new LibraryStatistic();
            $statistic->setLibrary($library);
        }

        $statistic->setTotalArtists($stats['totalArtists'] ?? 0)
            ->setTotalAlbums($stats['totalAlbums'] ?? 0)
            ->setTotalTracks($stats['totalTracks'] ?? 0)
            ->setDownloadedAlbums($stats['downloadedAlbums'] ?? 0)
            ->setDownloadedTracks($stats['downloadedTracks'] ?? 0)
            ->setTotalSingles($stats['totalSingles'] ?? 0)
            ->setDownloadedSingles($stats['downloadedSingles'] ?? 0)
            ->touch();

        $this->getEntityManager()->persist($statistic);
        $this->getEntityManager()->flush();

        return $statistic;
    }

    /**
     * Delete statistics for a library.
     */
    public function deleteByLibrary(Library $library): void
    {
        $statistic = $this->findByLibrary($library);
        if ($statistic) {
            $this->getEntityManager()->remove($statistic);
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Get all statistics with library information.
     */
    public function findAllWithLibrary(): array
    {
        /** @var LibraryStatistic[] $result */
        $result = $this->createQueryBuilder('ls')
            ->join('ls.library', 'l')
            ->addSelect('l')
            ->orderBy('l.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Count total statistics records.
     */
    public function countAll(): int
    {
        /** @var int $result */
        $result = $this->createQueryBuilder('ls')
            ->select('COUNT(ls.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return $result;
    }
}
