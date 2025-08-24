<?php

declare(strict_types=1);

namespace App\Task\Processor;

use App\Client\MusicBrainzApiClient;
use App\Entity\Album;
use App\Entity\Task;
use App\Manager\AlbumMediaProcessor;
use App\Repository\AlbumRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.task_processor')]
class SyncAlbumTaskProcessor implements TaskProcessorInterface
{
    public function __construct(
        private AlbumRepository $albumRepository,
        private MusicBrainzApiClient $musicBrainzApiClient,
        private EntityManagerInterface $entityManager,
        private AlbumMediaProcessor $albumMediaProcessor,
        private LoggerInterface $logger
    ) {
    }

    public function process(Task $task): TaskProcessorResult
    {
        try {
            $albumId = $task->getEntityId();
            $metadata = $task->getMetadata() ?? [];
            $newMbid = $metadata['new_mbid'] ?? null;

            if (!$albumId) {
                return TaskProcessorResult::failure('No album ID provided');
            }

            if (!$newMbid) {
                return TaskProcessorResult::success('No new MBID provided in metadata');
            }

            $this->logger->info('Starting album resynchronization', [
                'album_id' => $albumId,
                'new_mbid' => $newMbid,
            ]);

            // Get the album
            $album = $this->albumRepository->find($albumId);
            if (!$album) {
                return TaskProcessorResult::failure("Album {$albumId} not found");
            }

            // Synchronize with MusicBrainz
            $this->syncAlbumWithMusicBrainz($album, $newMbid);

            $this->logger->info('Album resynchronization completed successfully', [
                'album_id' => $albumId,
                'album_title' => $album->getTitle(),
                'new_mbid' => $newMbid,
            ]);

            return TaskProcessorResult::success(
                \sprintf('Successfully synced album "%s"', $album->getTitle()),
                [
                    'albumId' => $album->getId(),
                    'albumTitle' => $album->getTitle(),
                    'newMbid' => $newMbid,
                ]
            );
        } catch (Exception $e) {
            $this->logger->error('Error during album resynchronization', [
                'album_id' => $task->getEntityId(),
                'error' => $e->getMessage(),
            ]);

            return TaskProcessorResult::failure($e->getMessage());
        }
    }

    private function syncAlbumWithMusicBrainz(Album $album, string $releaseMbid): void
    {
        $this->logger->info('Syncing with release MBID', [
            'album_id' => $album->getId(),
            'release_mbid' => $releaseMbid,
        ]);

        // Get release details
        $releaseData = $this->musicBrainzApiClient->getRelease($releaseMbid);
        if (!$releaseData) {
            throw new Exception("Unable to get release details for {$releaseMbid}");
        }

        // Update album information
        $album->setTitle($releaseData['title'] ?? $album->getTitle());
        $album->setReleaseDate(new DateTime($releaseData['date'] ?? 'now'));

        $album->setReleaseMbid($releaseMbid);
        $album->setReleaseGroupMbid($releaseData['release-group']['id'] ?? null);

        // Update description
        if (isset($releaseData['annotation'])) {
            $album->setOverview($releaseData['annotation']);
        }

        // Update last sync
        $album->setLastInfoSync(new DateTime());

        // Get media and tracks from the release
        $mediaData = $this->musicBrainzApiClient->getReleaseMedia($releaseMbid);
        // Use the safer method to prevent accidental track deletion
        $this->albumMediaProcessor->processAlbumMedia($mediaData, $album);

        $this->entityManager->flush();
    }

    public function getSupportedTaskTypes(): array
    {
        return [Task::TYPE_SYNC_ALBUM];
    }

    public function supports(Task $task): bool
    {
        return Task::TYPE_SYNC_ALBUM === $task->getType();
    }
}
