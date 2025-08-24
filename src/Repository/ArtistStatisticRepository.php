<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Artist;
use App\Entity\ArtistStatistic;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArtistStatistic>
 */
class ArtistStatisticRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArtistStatistic::class);
    }

    /**
     * Find statistics for a specific artist.
     */
    public function findByArtist(Artist $artist): ?ArtistStatistic
    {
        return $this->findOneBy(['artist' => $artist]);
    }

    /**
     * Find statistics for an artist by ID.
     */
    public function findByArtistId(int $artistId): ?ArtistStatistic
    {
        return $this->createQueryBuilder('as')
            ->join('as.artist', 'a')
            ->where('a.id = :artistId')
            ->setParameter('artistId', $artistId)
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

        /** @var ArtistStatistic[] $result */
        $result = $this->createQueryBuilder('as')
            ->where('as.updatedAt < :threshold OR as.updatedAt IS NULL')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Create or update statistics for an artist.
     */
    public function createOrUpdate(Artist $artist, array $stats): ArtistStatistic
    {
        $statistic = $this->findByArtist($artist);

        if (!$statistic) {
            $statistic = new ArtistStatistic();
            $statistic->setArtist($artist);
        }

        $statistic->setTotalAlbums($stats['totalAlbums'] ?? 0)
            ->setTotalSingles($stats['totalSingles'] ?? 0)
            ->setTotalTracks($stats['totalTracks'] ?? 0)
            ->setDownloadedAlbums($stats['downloadedAlbums'] ?? 0)
            ->setDownloadedSingles($stats['downloadedSingles'] ?? 0)
            ->setDownloadedTracks($stats['downloadedTracks'] ?? 0)
            ->setMonitoredAlbums($stats['monitoredAlbums'] ?? 0)
            ->setMonitoredSingles($stats['monitoredSingles'] ?? 0)
            ->touch();

        $this->getEntityManager()->persist($statistic);
        $this->getEntityManager()->flush();

        return $statistic;
    }

    /**
     * Delete statistics for an artist.
     */
    public function deleteByArtist(Artist $artist): void
    {
        $statistic = $this->findByArtist($artist);
        if ($statistic) {
            $this->getEntityManager()->remove($statistic);
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Get all statistics with artist information.
     */
    public function findAllWithArtist(): array
    {
        /** @var ArtistStatistic[] $result */
        $result = $this->createQueryBuilder('as')
            ->join('as.artist', 'a')
            ->addSelect('a')
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Get statistics for artists in a specific library.
     */
    public function findByLibraryId(int $libraryId): array
    {
        /** @var ArtistStatistic[] $result */
        $result = $this->createQueryBuilder('as')
            ->join('as.artist', 'a')
            ->join('a.library', 'l')
            ->addSelect('a')
            ->where('l.id = :libraryId')
            ->setParameter('libraryId', $libraryId)
            ->orderBy('a.name', 'ASC')
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
        $result = $this->createQueryBuilder('as')
            ->select('COUNT(as.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return $result;
    }

    /**
     * Get top artists by total albums.
     */
    public function findTopArtistsByAlbums(int $limit = 10): array
    {
        /** @var ArtistStatistic[] $result */
        $result = $this->createQueryBuilder('as')
            ->join('as.artist', 'a')
            ->addSelect('a')
            ->orderBy('as.totalAlbums', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Get top artists by total tracks.
     */
    public function findTopArtistsByTracks(int $limit = 10): array
    {
        /** @var ArtistStatistic[] $result */
        $result = $this->createQueryBuilder('as')
            ->join('as.artist', 'a')
            ->addSelect('a')
            ->orderBy('as.totalTracks', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }
}
