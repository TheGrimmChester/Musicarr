<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Album;
use App\Entity\AlbumStatistic;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AlbumStatistic>
 */
class AlbumStatisticRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AlbumStatistic::class);
    }

    /**
     * Find statistics for a specific album.
     */
    public function findByAlbum(Album $album): ?AlbumStatistic
    {
        return $this->findOneBy(['album' => $album]);
    }

    /**
     * Find statistics for an album by ID.
     */
    public function findByAlbumId(int $albumId): ?AlbumStatistic
    {
        return $this->createQueryBuilder('als')
            ->join('als.album', 'al')
            ->where('al.id = :albumId')
            ->setParameter('albumId', $albumId)
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

        /** @var AlbumStatistic[] $result */
        $result = $this->createQueryBuilder('als')
            ->where('als.updatedAt < :threshold OR als.updatedAt IS NULL')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Create or update statistics for an album.
     */
    public function createOrUpdate(Album $album, array $stats): AlbumStatistic
    {
        $statistic = $this->findByAlbum($album);

        if (!$statistic) {
            $statistic = new AlbumStatistic();
            $statistic->setAlbum($album);
        }

        $statistic->setTotalTracks($stats['totalTracks'] ?? 0)
            ->setDownloadedTracks($stats['downloadedTracks'] ?? 0)
            ->setMonitoredTracks($stats['monitoredTracks'] ?? 0)
            ->setTracksWithFiles($stats['tracksWithFiles'] ?? 0)
            ->setTotalDuration($stats['totalDuration'] ?? null)
            ->setAverageTrackDuration($stats['averageTrackDuration'] ?? null)
            ->setCompletionPercentage($stats['completionPercentage'] ?? null)
            ->touch();

        $this->getEntityManager()->persist($statistic);
        $this->getEntityManager()->flush();

        return $statistic;
    }

    /**
     * Delete statistics for an album.
     */
    public function deleteByAlbum(Album $album): void
    {
        $statistic = $this->findByAlbum($album);
        if ($statistic) {
            $this->getEntityManager()->remove($statistic);
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Get all statistics with album information.
     */
    public function findAllWithAlbum(): array
    {
        /** @var AlbumStatistic[] $result */
        $result = $this->createQueryBuilder('als')
            ->join('als.album', 'al')
            ->join('al.artist', 'a')
            ->addSelect('al', 'a')
            ->orderBy('a.name', 'ASC')
            ->addOrderBy('al.title', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Get statistics for albums by artist.
     */
    public function findByArtistId(int $artistId): array
    {
        /** @var AlbumStatistic[] $result */
        $result = $this->createQueryBuilder('als')
            ->join('als.album', 'al')
            ->join('al.artist', 'a')
            ->addSelect('al')
            ->where('a.id = :artistId')
            ->setParameter('artistId', $artistId)
            ->orderBy('al.title', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Get statistics for albums in a specific library.
     */
    public function findByLibraryId(int $libraryId): array
    {
        /** @var AlbumStatistic[] $result */
        $result = $this->createQueryBuilder('als')
            ->join('als.album', 'al')
            ->join('al.artist', 'a')
            ->join('a.library', 'l')
            ->addSelect('al', 'a')
            ->where('l.id = :libraryId')
            ->setParameter('libraryId', $libraryId)
            ->orderBy('a.name', 'ASC')
            ->addOrderBy('al.title', 'ASC')
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
        $result = $this->createQueryBuilder('als')
            ->select('COUNT(als.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return $result;
    }

    /**
     * Get albums with highest completion percentage.
     */
    public function findMostCompleteAlbums(int $limit = 10): array
    {
        /** @var AlbumStatistic[] $result */
        $result = $this->createQueryBuilder('als')
            ->join('als.album', 'al')
            ->join('al.artist', 'a')
            ->addSelect('al', 'a')
            ->where('als.completionPercentage IS NOT NULL')
            ->orderBy('als.completionPercentage', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Get albums with lowest completion percentage.
     */
    public function findLeastCompleteAlbums(int $limit = 10): array
    {
        /** @var AlbumStatistic[] $result */
        $result = $this->createQueryBuilder('als')
            ->join('als.album', 'al')
            ->join('al.artist', 'a')
            ->addSelect('al', 'a')
            ->where('als.completionPercentage IS NOT NULL')
            ->orderBy('als.completionPercentage', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Get albums with most tracks.
     */
    public function findAlbumsWithMostTracks(int $limit = 10): array
    {
        /** @var AlbumStatistic[] $result */
        $result = $this->createQueryBuilder('als')
            ->join('als.album', 'al')
            ->join('al.artist', 'a')
            ->addSelect('al', 'a')
            ->orderBy('als.totalTracks', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Get albums with longest duration.
     */
    public function findLongestAlbums(int $limit = 10): array
    {
        /** @var AlbumStatistic[] $result */
        $result = $this->createQueryBuilder('als')
            ->join('als.album', 'al')
            ->join('al.artist', 'a')
            ->addSelect('al', 'a')
            ->where('als.totalDuration IS NOT NULL')
            ->orderBy('als.totalDuration', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }
}
