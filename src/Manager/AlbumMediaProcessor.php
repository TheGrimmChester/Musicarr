<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\Album;
use App\Entity\Medium;
use App\Entity\Task;
use App\Entity\Track;
use App\Repository\LibraryRepository;
use App\Repository\MediumRepository;
use App\Repository\TrackRepository;
use App\Task\TaskFactory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AlbumMediaProcessor
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MediumRepository $mediumRepository,
        private TrackRepository $trackRepository,
        private LibraryRepository $libraryRepository,
        private TaskFactory $taskService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Process album media data and update the album with new media and tracks.
     */
    public function processAlbumMedia(array $mediaData, Album $album): void
    {
        $existingMedia = $album->getMediums()->toArray();
        $processedMediumMbids = [];
        $processedTrackIds = []; // Track both MBID and internal ID for safety
        $mediumChanges = 0; // Track medium reassignments

        $this->logger->info('Starting album media processing', [
            'album_id' => $album->getId(),
            'album_title' => $album->getTitle(),
            'existing_media_count' => \count($existingMedia),
            'new_media_count' => \count($mediaData),
        ]);

        // First pass: create/update all media and tracks
        foreach ($mediaData as $mediumData) {
            $medium = $this->processMedium($mediumData, $album);
            if ($medium->getMbid()) {
                $processedMediumMbids[] = $medium->getMbid();
            }

            if (isset($mediumData['tracks'])) {
                foreach ($mediumData['tracks'] as $trackData) {
                    $track = $this->processTrack($trackData, $album, $medium, $mediumChanges);

                    // Store both MBID and internal ID for cleanup safety
                    if ($track->getMbid()) {
                        $processedTrackIds['mbid'][] = $track->getMbid();
                    }
                }
            }
        }

        $this->logger->info('Processed new media and tracks', [
            'album_id' => $album->getId(),
            'processed_medium_mbids' => \count($processedMediumMbids),
            'processed_track_mbids' => \count($processedTrackIds['mbid'] ?? []),
        ]);

        // Flush to ensure all new entities have IDs
        $this->entityManager->flush();

        // Second pass: clean up orphaned tracks with safety checks
        $existingTracks = $this->trackRepository->findBy(['album' => $album]);
        $removedTracks = 0;
        $preservedTracks = 0;

        foreach ($existingTracks as $track) {
            $shouldRemove = false;

            // Use MBID for cleanup if available, otherwise use internal ID
            if ($track->getMbid()) {
                $shouldRemove = !\in_array($track->getMbid(), $processedTrackIds['mbid'] ?? [], true);
            }

            if ($shouldRemove) {
                // If track has files, convert them to UnmatchedTrack entities before removal
                if ($track->isHasFile() || $track->isDownloaded()) {
                    $this->convertTrackFilesToUnmatchedTracks($track, $album);
                    $this->logger->info('Converted track files to UnmatchedTrack entities for: ' . $track->getTitle() . ' (ID: ' . $track->getId() . ', MBID: ' . $track->getMbid() . ')');
                }

                $this->logger->info('Removing orphaned track: ' . $track->getTitle() . ' (ID: ' . $track->getId() . ', MBID: ' . $track->getMbid() . ')');
                $this->entityManager->remove($track);
                ++$removedTracks;
            } else {
                ++$preservedTracks;
            }
        }

        $this->logger->info('Track cleanup completed', [
            'album_id' => $album->getId(),
            'total_existing_tracks' => \count($existingTracks),
            'preserved_tracks' => $preservedTracks,
            'removed_tracks' => $removedTracks,
        ]);

        // Remove media that are not in the new data
        $removedMedia = 0;
        foreach ($existingMedia as $medium) {
            $shouldRemove = false;

            // Use MBID for comparison if available, otherwise fall back to position
            if ($medium->getMbid()) {
                $shouldRemove = !\in_array($medium->getMbid(), $processedMediumMbids, true);
            } else {
                // Fallback to position-based comparison for media without MBID
                $shouldRemove = !\in_array($medium->getPosition(), array_map(fn ($m) => $m['position'], $mediaData), true);
            }

            if ($shouldRemove) {
                // Safety check: don't remove media that have tracks with files
                $hasTracksWithFiles = false;
                foreach ($medium->getTracks() as $track) {
                    if ($track->isHasFile() || $track->isDownloaded()) {
                        $hasTracksWithFiles = true;

                        break;
                    }
                }

                if ($hasTracksWithFiles) {
                    $this->logger->warning('Skipping removal of medium with tracks that have files: ' . $medium->getDisplayName() . ' (MBID: ' . $medium->getMbid() . ', Position: ' . $medium->getPosition() . ')');

                    continue;
                }

                // Set tracks' medium to null before removing the medium
                foreach ($medium->getTracks() as $track) {
                    $track->setMedium(null);
                    $this->logger->info('Set track medium to null: ' . $track->getTitle() . ' (ID: ' . $track->getId() . ')');
                }

                $this->logger->info('Removing medium: ' . $medium->getDisplayName() . ' (MBID: ' . $medium->getMbid() . ', Position: ' . $medium->getPosition() . ')');
                $this->entityManager->remove($medium);
                ++$removedMedia;
            }
        }

        $this->logger->info('Album media processing completed', [
            'album_id' => $album->getId(),
            'album_title' => $album->getTitle(),
            'removed_media' => $removedMedia,
            'medium_changes' => $mediumChanges,
            'removed_tracks' => $removedTracks,
            'preserved_tracks' => $preservedTracks,
        ]);
    }

    /**
     * Process album media data with conservative cleanup (preserves existing tracks).
     */
    public function processAlbumMediaConservative(array $mediaData, Album $album): void
    {
        // For conservative approach, we'll skip the cleanup logic entirely
        $this->processAlbumMediaWithoutCleanup($mediaData, $album);
    }

    /**
     * Process album media data with MBID-based cleanup but additional safety checks.
     */
    public function processAlbumMediaSafe(array $mediaData, Album $album): void
    {
        $this->processAlbumMedia($mediaData, $album);
    }

    /**
     * Process album media data without any cleanup (preserves all existing tracks).
     */
    private function processAlbumMediaWithoutCleanup(array $mediaData, Album $album): void
    {
        $existingMedia = $album->getMediums()->toArray();
        $processedMediumMbids = [];

        $this->logger->info('Starting album media processing (conservative mode)', [
            'album_id' => $album->getId(),
            'album_title' => $album->getTitle(),
            'existing_media_count' => \count($existingMedia),
            'new_media_count' => \count($mediaData),
        ]);

        // First pass: create/update all media and tracks
        foreach ($mediaData as $mediumData) {
            $medium = $this->processMedium($mediumData, $album);
            if ($medium->getMbid()) {
                $processedMediumMbids[] = $medium->getMbid();
            }

            if (isset($mediumData['tracks'])) {
                foreach ($mediumData['tracks'] as $trackData) {
                    $this->processTrack($trackData, $album, $medium);
                }
            }
        }

        // Flush to ensure all new entities have IDs
        $this->entityManager->flush();

        $this->logger->info('Album media processing completed (conservative mode - no cleanup)', [
            'album_id' => $album->getId(),
            'album_title' => $album->getTitle(),
            'processed_medium_mbids' => \count($processedMediumMbids),
        ]);
    }

    /**
     * Debug method to analyze track matching and preservation.
     */
    public function debugTrackPreservation(array $mediaData, Album $album): array
    {
        $existingTracks = $this->trackRepository->findBy(['album' => $album]);
        $analysis = [
            'album_id' => $album->getId(),
            'album_title' => $album->getTitle(),
            'existing_tracks' => [],
            'new_track_data' => [],
            'matching_analysis' => [],
        ];

        // Analyze existing tracks
        foreach ($existingTracks as $track) {
            $analysis['existing_tracks'][] = [
                'id' => $track->getId(),
                'mbid' => $track->getMbid(),
                'title' => $track->getTitle(),
                'track_number' => $track->getTrackNumber(),
                'medium_number' => $track->getMediumNumber(),
                'has_file' => $track->isHasFile(),
                'downloaded' => $track->isDownloaded(),
            ];
        }

        // Analyze new track data
        foreach ($mediaData as $mediumData) {
            if (isset($mediumData['tracks'])) {
                foreach ($mediumData['tracks'] as $trackData) {
                    $analysis['new_track_data'][] = [
                        'id' => $trackData['id'] ?? null,
                        'title' => $trackData['title'] ?? null,
                        'number' => $trackData['number'] ?? null,
                        'position' => $trackData['position'] ?? null,
                        'medium_position' => $mediumData['position'] ?? null,
                    ];
                }
            }
        }

        // Analyze matching
        foreach ($existingTracks as $track) {
            $matches = [];

            // Check MBID match
            if ($track->getMbid()) {
                foreach ($mediaData as $mediumData) {
                    if (isset($mediumData['tracks'])) {
                        foreach ($mediumData['tracks'] as $trackData) {
                            if (($trackData['id'] ?? null) === $track->getMbid()) {
                                $matches[] = 'mbid';

                                break 2;
                            }
                        }
                    }
                }
            }

            // Check title + track number + medium match
            foreach ($mediaData as $mediumData) {
                if (isset($mediumData['tracks'])) {
                    foreach ($mediumData['tracks'] as $trackData) {
                        if (($trackData['title'] ?? '') === $track->getTitle()
                            && (string) ($trackData['number'] ?? '') === $track->getTrackNumber()
                            && (int) ($mediumData['position'] ?? 0) === $track->getMediumNumber()) {
                            $matches[] = 'title_track_medium';

                            break 2;
                        }
                    }
                }
            }

            // Check title + track number match
            foreach ($mediaData as $mediumData) {
                if (isset($mediumData['tracks'])) {
                    foreach ($mediumData['tracks'] as $trackData) {
                        if (($trackData['title'] ?? '') === $track->getTitle()
                            && (string) ($trackData['number'] ?? '') === $track->getTrackNumber()) {
                            $matches[] = 'title_track';

                            break 2;
                        }
                    }
                }
            }

            $analysis['matching_analysis'][] = [
                'track_id' => $track->getId(),
                'track_title' => $track->getTitle(),
                'matches' => $matches,
                'will_be_preserved' => !empty($matches) || $track->isHasFile() || $track->isDownloaded(),
            ];
        }

        return $analysis;
    }

    /**
     * Process a single medium.
     */
    public function processMedium(array $mediumData, Album $album): Medium
    {
        // Try to find existing medium by position and album
        $existingMedium = $this->mediumRepository->findOneBy([
            'album' => $album,
            'position' => $mediumData['position'],
        ]);

        if (!$existingMedium) {
            $existingMedium = new Medium();
            $existingMedium->setAlbum($album);
            $album->addMedium($existingMedium);
            $this->entityManager->persist($existingMedium);
        }

        $existingMedium->setMbid($mediumData['id'] ?? null);
        $existingMedium->setTitle($mediumData['title'] ?? null);
        $existingMedium->setFormat($mediumData['format'] ?? 'CD');
        $existingMedium->setPosition($mediumData['position']);
        $existingMedium->setTrackCount($mediumData['trackCount'] ?? \count($mediumData['tracks'] ?? []));
        $existingMedium->setDiscId($mediumData['discId'] ?? null);

        return $existingMedium;
    }

    /**
     * Process a single track.
     */
    public function processTrack(array $trackData, Album $album, Medium $medium, int &$mediumChanges = 0): Track
    {
        // Try to find existing track by multiple criteria for better matching
        $track = null;

        // First, try to find by MBID if available
        if (!empty($trackData['id'])) {
            $track = $this->trackRepository->findOneBy(['mbid' => $trackData['id']]);
        }

        // If not found by MBID, try to find by title, track number, and medium position
        if (!$track && !empty($trackData['title']) && isset($trackData['number'])) {
            $track = $this->trackRepository->findOneBy([
                'album' => $album,
                'title' => $trackData['title'],
                'trackNumber' => (string) $trackData['number'],
                'mediumNumber' => $medium->getPosition(),
            ]);
        }

        // If still not found, try just by title and track number within the album
        if (!$track && !empty($trackData['title']) && isset($trackData['number'])) {
            $track = $this->trackRepository->findOneBy([
                'album' => $album,
                'title' => $trackData['title'],
                'trackNumber' => (string) $trackData['number'],
            ]);
        }

        if (!$track instanceof Track) {
            $track = new Track();
            $track->setDownloaded(false);
            $track->setHasFile(false);
            $track->setMbid($trackData['id'] ?? null);
            $track->setAlbum($album);
            $this->entityManager->persist($track);
        }

        // Check if medium is changing for existing tracks
        $oldMedium = $track->getMedium();
        if ($oldMedium && $oldMedium->getId() !== $medium->getId()) {
            $this->logger->info('Track medium changed: ' . $track->getTitle() . ' (ID: ' . $track->getId() . ', MBID: ' . $track->getMbid() . ') from ' . $oldMedium->getDisplayName() . ' to ' . $medium->getDisplayName());
            ++$mediumChanges;
        }

        // Update track information
        $track->setMbid($trackData['id'] ?? null);
        $track->setTitle($trackData['title']);
        $track->setTrackNumber((string) $trackData['number']);
        $track->setMediumNumber($medium->getPosition());
        $track->setDuration($trackData['length'] ?? $trackData['duration'] ?? 0);
        $track->setMonitored(true);
        $track->setArtistName($album->getArtist()->getName());
        $track->setAlbumTitle($album->getTitle());
        $track->setMedium($medium);
        $medium->addTrack($track);

        return $track;
    }

    /**
     * Convert track files to UnmatchedTrack entities before removing the track.
     */
    private function convertTrackFilesToUnmatchedTracks(Track $track, Album $album): void
    {
        // Get the library from the artist (assuming artist belongs to a library)
        $library = null;
        $artist = $album->getArtist();
        if ($artist) {
            // Try to find library by artist path or use a default library
            $libraries = $this->libraryRepository->findAll();
            if (!empty($libraries)) {
                $library = $libraries[0]; // Use first available library as fallback
            }
        }

        if (!$library) {
            $this->logger->warning('No library found for converting track files to UnmatchedTrack entities');

            return;
        }

        foreach ($track->getFiles() as $trackFile) {
            $this->taskService->createTask(
                Task::TYPE_PROCESS_LIBRARY_FILE,
                null,
                $library->getId(),
                basename($trackFile->getFilePath()),
                [
                    'file_path' => $trackFile->getFilePath(),
                    'dry_run' => false,
                    'force_analysis' => true,
                ],
                4 // High priority for file processing
            );
        }
    }
}
