<?php

declare(strict_types=1);

namespace App\Task\Processor;

use App\Entity\Task;
use App\Manager\MusicLibraryManager;
use App\Repository\AlbumRepository;
use App\Repository\ArtistRepository;
use App\Task\TaskFactory;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.task_processor')]
class AddAlbumTaskProcessor implements TaskProcessorInterface
{
    public function __construct(
        private MusicLibraryManager $musicLibraryManager,
        private AlbumRepository $albumRepository,
        private ArtistRepository $artistRepository,
        private EntityManagerInterface $entityManager,
        private TaskFactory $taskService,
        private LoggerInterface $logger
    ) {
    }

    public function process(Task $task): TaskProcessorResult
    {
        try {
            $releaseMbid = $task->getEntityMbid();
            $albumTitle = $task->getEntityName();
            $metadata = $task->getMetadata() ?? [];

            $artistId = $metadata['artist_id'] ?? null;
            $artistName = $metadata['artist_name'] ?? null;
            $releaseGroupId = $metadata['release_group_id'] ?? null;

            if (!$releaseMbid || !$albumTitle || !$artistId) {
                return TaskProcessorResult::failure('Missing required data: release MBID, album title, or artist ID');
            }

            $this->logger->info('Processing add album task', [
                'release_mbid' => $releaseMbid,
                'album_title' => $albumTitle,
                'artist_id' => $artistId,
                'artist_name' => $artistName,
                'release_group_id' => $releaseGroupId,
            ]);

            // Check if artist exists
            $artist = $this->artistRepository->find($artistId);
            if (!$artist) {
                return TaskProcessorResult::failure("Artist with ID {$artistId} not found");
            }

            // Check if album already exists by release MBID
            $existingAlbum = $this->albumRepository->findOneBy(['releaseMbid' => $releaseMbid]);
            if ($existingAlbum) {
                return TaskProcessorResult::success(
                    "Album '{$albumTitle}' already exists with release MBID {$releaseMbid}",
                    [
                        'albumId' => $existingAlbum->getId(),
                        'albumTitle' => $existingAlbum->getTitle(),
                        'status' => 'already_exists',
                    ]
                );
            }

            // Add the album using the MusicLibraryManager
            $album = $this->musicLibraryManager->addAlbumWithMbid(
                $albumTitle,
                $releaseMbid,
                $releaseGroupId,
                $artistId
            );

            if (!$album) {
                return TaskProcessorResult::failure("Failed to add album '{$albumTitle}' to artist '{$artistName}'");
            }

            $this->logger->info('Album added successfully', [
                'album_id' => $album->getId(),
                'album_title' => $album->getTitle(),
                'artist_id' => $artistId,
                'artist_name' => $artistName,
                'release_mbid' => $releaseMbid,
                'release_group_id' => $releaseGroupId,
            ]);

            // Create a task to sync the album to get track information
            $this->taskService->createTask(
                Task::TYPE_SYNC_ALBUM,
                $releaseMbid,
                $album->getId(),
                $albumTitle,
                [
                    'artist_id' => $artistId,
                    'artist_name' => $artistName,
                ],
                2 // Lower priority
            );

            return TaskProcessorResult::success(
                \sprintf('Successfully added album "%s" to artist "%s"', $albumTitle, $artistName),
                [
                    'albumId' => $album->getId(),
                    'albumTitle' => $album->getTitle(),
                    'artistId' => $artistId,
                    'artistName' => $artistName,
                    'releaseMbid' => $releaseMbid,
                    'releaseGroupId' => $releaseGroupId,
                    'status' => 'added',
                ]
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to add album', [
                'release_mbid' => $task->getEntityMbid(),
                'album_title' => $task->getEntityName(),
                'error' => $e->getMessage(),
            ]);

            return TaskProcessorResult::failure($e->getMessage());
        }
    }

    public function getSupportedTaskTypes(): array
    {
        return [Task::TYPE_ADD_ALBUM];
    }

    public function supports(Task $task): bool
    {
        return Task::TYPE_ADD_ALBUM === $task->getType();
    }
}
