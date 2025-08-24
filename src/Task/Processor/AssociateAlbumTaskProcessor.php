<?php

declare(strict_types=1);

namespace App\Task\Processor;

use App\Entity\Task;
use App\Entity\Track;
use App\Entity\TrackFile;
use App\Manager\MusicLibraryManager;
use App\Repository\AlbumRepository;
use App\Repository\TrackRepository;
use App\Repository\UnmatchedTrackRepository;
use App\Task\TaskFactory;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.task_processor')]
class AssociateAlbumTaskProcessor implements TaskProcessorInterface
{
    public function __construct(
        private MusicLibraryManager $musicLibraryManager,
        private UnmatchedTrackRepository $unmatchedTrackRepository,
        private AlbumRepository $albumRepository,
        private TrackRepository $trackRepository,
        private EntityManagerInterface $entityManager,
        private TaskFactory $taskService,
        private LoggerInterface $logger
    ) {
    }

    public function process(Task $task): TaskProcessorResult
    {
        try {
            $unmatchedTrackId = $task->getEntityId();
            $albumTitle = $task->getEntityName();
            $releaseMbid = $task->getEntityMbid();
            $metadata = $task->getMetadata() ?? [];

            $artistName = $metadata['artist_name'] ?? null;
            $artistMbid = $metadata['artist_mbid'] ?? null;
            $releaseGroupMbid = $metadata['release_group_mbid'] ?? null;
            $libraryId = $metadata['library_id'] ?? null;

            if (!$unmatchedTrackId || !$albumTitle || !$releaseMbid) {
                return TaskProcessorResult::failure('Missing required data: unmatched track ID, album title, or release MBID');
            }

            if (!$libraryId) {
                return TaskProcessorResult::failure('No library ID provided');
            }

            $this->logger->info('Processing associate album task', [
                'unmatched_track_id' => $unmatchedTrackId,
                'artist_name' => $artistName,
                'album_title' => $albumTitle,
                'release_mbid' => $releaseMbid,
                'library_id' => $libraryId,
            ]);

            // Check if unmatched track still exists
            $unmatchedTrack = $this->unmatchedTrackRepository->find($unmatchedTrackId);
            if (!$unmatchedTrack) {
                return TaskProcessorResult::success(
                    "Unmatched track {$unmatchedTrackId} no longer exists",
                    ['unmatchedTrackId' => $unmatchedTrackId, 'status' => 'not_found']
                );
            }

            // Check if track is already matched
            if ($unmatchedTrack->isMatched()) {
                return TaskProcessorResult::success(
                    "Track {$unmatchedTrackId} is already matched",
                    ['unmatchedTrackId' => $unmatchedTrackId, 'status' => 'already_matched']
                );
            }

            // Sync artist first if needed
            $artist = null;
            if ($artistName && $libraryId) {
                $artist = $this->musicLibraryManager->syncArtistWithMbid($artistName, $artistMbid);
                if (!$artist) {
                    return TaskProcessorResult::failure("Unable to sync artist: {$artistName}");
                }
            }

            // Sync or add the album using the release MBID
            $album = null;
            if ($artist) {
                try {
                    $album = $this->musicLibraryManager->addAlbumWithMbid(
                        $albumTitle,
                        $releaseMbid,
                        $releaseGroupMbid,
                        $artist->getId()
                    );
                } catch (Exception $e) {
                    $this->logger->warning('Failed to add album with MBID, trying existing albums', [
                        'album_title' => $albumTitle,
                        'release_mbid' => $releaseMbid,
                        'error' => $e->getMessage(),
                    ]);

                    // Try to find existing album
                    $album = $this->albumRepository->findOneBy(['releaseMbid' => $releaseMbid]);
                }
            }

            if (!$album) {
                return TaskProcessorResult::failure("Unable to find or create album: {$albumTitle}");
            }

            // Get album tracks to find matching track
            $albumTracks = $this->trackRepository->findBy(['album' => $album]);

            $matchedTrack = null;
            $unmatchedTitle = $unmatchedTrack->getTitle();

            foreach ($albumTracks as $albumTrack) {
                // Simple title matching - could be enhanced with more sophisticated matching
                if (false !== mb_stripos($albumTrack->getTitle(), $unmatchedTitle)
                    || false !== mb_stripos($unmatchedTitle, $albumTrack->getTitle())) {
                    $matchedTrack = $albumTrack;

                    break;
                }
            }

            if (!$matchedTrack) {
                // Create a new track for this album
                $matchedTrack = new Track();
                $matchedTrack->setTitle($unmatchedTrack->getTitle());
                $matchedTrack->setAlbum($album);
                $matchedTrack->setArtistName($artist ? $artist->getName() : $unmatchedTrack->getArtist());
                $matchedTrack->setAlbumTitle($album->getTitle());
                $matchedTrack->setTrackNumber($unmatchedTrack->getTrackNumber());
                $matchedTrack->setDuration($unmatchedTrack->getDuration());
                $matchedTrack->setMonitored(true);
                $matchedTrack->setHasFile(true);
                $matchedTrack->setDownloaded(false);

                $this->entityManager->persist($matchedTrack);
            }

            // Create or update track file
            $trackFile = $this->entityManager
                ->getRepository(TrackFile::class)
                ->findOneBy(['filePath' => $unmatchedTrack->getFilePath()]);

            if ($trackFile) {
                // Update existing file with new metadata
                $trackFile->setQuality($unmatchedTrack->getQuality() ?? 'Unknown');
                $trackFile->setFileSize($unmatchedTrack->getFileSize());

                // Update lyrics path if available from unmatched track
                if ($unmatchedTrack->getLyricsFilepath() && !$trackFile->getLyricsPath()) {
                    $trackFile->setLyricsPath($unmatchedTrack->getLyricsFilepath());
                }
            } else {
                $trackFile = new TrackFile();
                $trackFile->setFilePath($unmatchedTrack->getFilePath());
                $trackFile->setTrack($matchedTrack);
                $trackFile->setQuality($unmatchedTrack->getQuality() ?? 'Unknown');
                $trackFile->setFileSize($unmatchedTrack->getFileSize());
                $trackFile->setAddedAt(new DateTime());

                // Set lyrics path if available from unmatched track
                if ($unmatchedTrack->getLyricsFilepath()) {
                    $trackFile->setLyricsPath($unmatchedTrack->getLyricsFilepath());
                }

                $this->entityManager->persist($trackFile);
            }

            // Update matched track status
            $matchedTrack->setHasFile(true);

            // Mark unmatched track as matched and remove it
            $unmatchedTrack->setIsMatched(true);
            $this->entityManager->persist($unmatchedTrack);
            $this->entityManager->flush();
            $this->entityManager->remove($unmatchedTrack);
            $this->entityManager->flush();

            // Queue audio analysis for the track file
            if ($trackFile && $trackFile->getId()) {
                $this->taskService->createTask(
                    Task::TYPE_ANALYZE_AUDIO_QUALITY,
                    null,
                    $trackFile->getId(),
                    $trackFile->getFilePath(),
                    [],
                    2 // Lower priority
                );
            }

            $this->logger->info('Album association completed successfully', [
                'unmatched_track_id' => $unmatchedTrackId,
                'album_id' => $album->getId(),
                'track_id' => $matchedTrack->getId(),
                'track_file_id' => $trackFile ? $trackFile->getId() : null,
            ]);

            return TaskProcessorResult::success(
                \sprintf('Successfully associated track with album "%s"', $album->getTitle()),
                [
                    'unmatchedTrackId' => $unmatchedTrackId,
                    'albumId' => $album->getId(),
                    'albumTitle' => $album->getTitle(),
                    'trackId' => $matchedTrack->getId(),
                    'trackTitle' => $matchedTrack->getTitle(),
                    'trackFileId' => $trackFile ? $trackFile->getId() : null,
                    'artistId' => $artist ? $artist->getId() : null,
                    'artistName' => $artist ? $artist->getName() : null,
                ]
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to associate album', [
                'unmatched_track_id' => $task->getEntityId(),
                'album_title' => $task->getEntityName(),
                'error' => $e->getMessage(),
            ]);

            return TaskProcessorResult::failure($e->getMessage());
        }
    }

    public function getSupportedTaskTypes(): array
    {
        return [Task::TYPE_ASSOCIATE_ALBUM];
    }

    public function supports(Task $task): bool
    {
        return Task::TYPE_ASSOCIATE_ALBUM === $task->getType();
    }
}
