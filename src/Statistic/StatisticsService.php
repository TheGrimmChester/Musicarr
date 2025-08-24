<?php

declare(strict_types=1);

namespace App\Statistic;

use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Library;
use App\Repository\AlbumStatisticRepository;
use App\Repository\ArtistStatisticRepository;
use App\Repository\LibraryStatisticRepository;

class StatisticsService
{
    public function __construct(
        private LibraryStatisticRepository $libraryStatisticRepository,
        private ArtistStatisticRepository $artistStatisticRepository,
        private AlbumStatisticRepository $albumStatisticRepository
    ) {
    }

    /**
     * Get library statistics.
     */
    public function getLibraryStatistics(Library $library): ?array
    {
        $stats = $this->libraryStatisticRepository->findByLibrary($library);

        return $stats ? $stats->toArray() : null;
    }

    /**
     * Get all library statistics.
     */
    public function getAllLibraryStatistics(): array
    {
        $statistics = $this->libraryStatisticRepository->findAllWithLibrary();

        return array_map(fn ($stat) => $stat->toArray(), $statistics);
    }

    /**
     * Get artist statistics.
     */
    public function getArtistStatistics(Artist $artist): ?array
    {
        $stats = $this->artistStatisticRepository->findByArtist($artist);

        return $stats ? $stats->toArray() : null;
    }

    /**
     * Get all artist statistics for a library.
     */
    public function getArtistStatisticsByLibrary(int $libraryId): array
    {
        $statistics = $this->artistStatisticRepository->findByLibraryId($libraryId);

        return array_map(fn ($stat) => $stat->toArray(), $statistics);
    }

    /**
     * Get top artists by total albums.
     */
    public function getTopArtistsByAlbums(int $limit = 10): array
    {
        $statistics = $this->artistStatisticRepository->findTopArtistsByAlbums($limit);

        return array_map(fn ($stat) => $stat->toArray(), $statistics);
    }

    /**
     * Get top artists by total tracks.
     */
    public function getTopArtistsByTracks(int $limit = 10): array
    {
        $statistics = $this->artistStatisticRepository->findTopArtistsByTracks($limit);

        return array_map(fn ($stat) => $stat->toArray(), $statistics);
    }

    /**
     * Get album statistics.
     */
    public function getAlbumStatistics(Album $album): ?array
    {
        $stats = $this->albumStatisticRepository->findByAlbum($album);

        return $stats ? $stats->toArray() : null;
    }

    /**
     * Get all album statistics for an artist.
     */
    public function getAlbumStatisticsByArtist(int $artistId): array
    {
        $statistics = $this->albumStatisticRepository->findByArtistId($artistId);

        return array_map(fn ($stat) => $stat->toArray(), $statistics);
    }

    /**
     * Get all album statistics for a library.
     */
    public function getAlbumStatisticsByLibrary(int $libraryId): array
    {
        $statistics = $this->albumStatisticRepository->findByLibraryId($libraryId);

        return array_map(fn ($stat) => $stat->toArray(), $statistics);
    }

    /**
     * Get most complete albums.
     */
    public function getMostCompleteAlbums(int $limit = 10): array
    {
        $statistics = $this->albumStatisticRepository->findMostCompleteAlbums($limit);

        return array_map(fn ($stat) => $stat->toArray(), $statistics);
    }

    /**
     * Get least complete albums.
     */
    public function getLeastCompleteAlbums(int $limit = 10): array
    {
        $statistics = $this->albumStatisticRepository->findLeastCompleteAlbums($limit);

        return array_map(fn ($stat) => $stat->toArray(), $statistics);
    }

    /**
     * Get albums with most tracks.
     */
    public function getAlbumsWithMostTracks(int $limit = 10): array
    {
        $statistics = $this->albumStatisticRepository->findAlbumsWithMostTracks($limit);

        return array_map(fn ($stat) => $stat->toArray(), $statistics);
    }

    /**
     * Get longest albums.
     */
    public function getLongestAlbums(int $limit = 10): array
    {
        $statistics = $this->albumStatisticRepository->findLongestAlbums($limit);

        return array_map(fn ($stat) => $stat->toArray(), $statistics);
    }

    /**
     * Check if library statistics are stale.
     */
    public function areLibraryStatisticsStale(Library $library, int $maxAgeMinutes = 60): bool
    {
        $stats = $this->libraryStatisticRepository->findByLibrary($library);

        return !$stats || $stats->isStale($maxAgeMinutes);
    }

    /**
     * Check if artist statistics are stale.
     */
    public function areArtistStatisticsStale(Artist $artist, int $maxAgeMinutes = 60): bool
    {
        $stats = $this->artistStatisticRepository->findByArtist($artist);

        return !$stats || $stats->isStale($maxAgeMinutes);
    }

    /**
     * Check if album statistics are stale.
     */
    public function areAlbumStatisticsStale(Album $album, int $maxAgeMinutes = 60): bool
    {
        $stats = $this->albumStatisticRepository->findByAlbum($album);

        return !$stats || $stats->isStale($maxAgeMinutes);
    }

    /**
     * Get comprehensive statistics summary.
     */
    public function getStatisticsSummary(): array
    {
        $libraryStats = $this->libraryStatisticRepository->findAllWithLibrary();
        $totalLibraries = \count($libraryStats);

        $totalArtists = 0;
        $totalAlbums = 0;
        $totalSingles = 0;
        $totalTracks = 0;
        $downloadedAlbums = 0;
        $downloadedSingles = 0;
        $downloadedTracks = 0;

        foreach ($libraryStats as $libStat) {
            $totalArtists += $libStat->getTotalArtists();
            $totalAlbums += $libStat->getTotalAlbums();
            $totalSingles += $libStat->getTotalSingles();
            $totalTracks += $libStat->getTotalTracks();
            $downloadedAlbums += $libStat->getDownloadedAlbums();
            $downloadedSingles += $libStat->getDownloadedSingles();
            $downloadedTracks += $libStat->getDownloadedTracks();
        }

        return [
            'libraries' => $totalLibraries,
            'artists' => $totalArtists,
            'albums' => $totalAlbums,
            'singles' => $totalSingles,
            'tracks' => $totalTracks,
            'downloaded_albums' => $downloadedAlbums,
            'downloaded_singles' => $downloadedSingles,
            'downloaded_tracks' => $downloadedTracks,
            'album_completion_rate' => $totalAlbums > 0 ? round(($downloadedAlbums / $totalAlbums) * 100, 2) : 0,
            'single_completion_rate' => $totalSingles > 0 ? round(($downloadedSingles / $totalSingles) * 100, 2) : 0,
            'track_completion_rate' => $totalTracks > 0 ? round(($downloadedTracks / $totalTracks) * 100, 2) : 0,
        ];
    }

    /**
     * Get statistics counts.
     */
    public function getStatisticsCounts(): array
    {
        return [
            'library_statistics' => $this->libraryStatisticRepository->countAll(),
            'artist_statistics' => $this->artistStatisticRepository->countAll(),
            'album_statistics' => $this->albumStatisticRepository->countAll(),
        ];
    }
}
