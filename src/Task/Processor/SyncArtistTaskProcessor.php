<?php

declare(strict_types=1);

namespace App\Task\Processor;

use App\Entity\Task;
use App\Manager\MusicLibraryManager;
use App\Task\TaskFactory;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.task_processor')]
class SyncArtistTaskProcessor implements TaskProcessorInterface
{
    public function __construct(
        private MusicLibraryManager $musicLibraryManager,
        private TaskFactory $taskService,
        private LoggerInterface $logger
    ) {
    }

    public function process(Task $task): TaskProcessorResult
    {
        try {
            $artistMbid = $task->getEntityMbid();
            $artistName = $task->getEntityName();
            $metadata = $task->getMetadata() ?? [];

            if (!$artistMbid && !$artistName) {
                return TaskProcessorResult::failure('No artist MBID or name provided');
            }

            $this->logger->info("Processing sync artist task: {$artistName}");

            // Extract library ID from metadata, default to library ID 1 if not specified
            $libraryId = $metadata['library_id'] ?? 1;

            // Sync the artist using the music library manager (create or update)
            $artist = $this->musicLibraryManager->syncArtistWithMbid(
                $artistName,
                $artistMbid,
            );

            if (!$artist) {
                return TaskProcessorResult::failure("Failed to sync artist: {$artistName}");
            }

            $this->logger->info("Artist synced successfully: {$artist->getName()} (ID: {$artist->getId()})");

            // If the artist has an MBID, create a task to sync albums
            if ($artist->getMbid()) {
                $this->logger->info("Creating sync albums task for artist: {$artist->getName()}");
                $artistId = $artist->getId();
                if (null !== $artistId) {
                    $this->taskService->createTask(
                        Task::TYPE_SYNC_ARTIST_ALBUMS,
                        null,
                        $artistId,
                        $artist->getName(),
                        ['source_task_id' => $task->getId()],
                        3 // Medium priority
                    );
                } else {
                    $this->logger->error("Artist ID is null for artist: {$artist->getName()}");
                }
            } else {
                $this->logger->info("No MBID for artist {$artist->getName()}, skipping album sync");
            }

            return TaskProcessorResult::success(
                \sprintf('Successfully synced artist "%s"', $artist->getName()),
                [
                    'artistId' => $artist->getId(),
                    'artistMbid' => $artist->getMbid(),
                    'artistName' => $artist->getName(),
                ]
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to sync artist', [
                'artistMbid' => $task->getEntityMbid(),
                'artistName' => $task->getEntityName(),
                'error' => $e->getMessage(),
            ]);

            return TaskProcessorResult::failure($e->getMessage());
        }
    }

    public function getSupportedTaskTypes(): array
    {
        // Support legacy add_artist tasks for backward compatibility
        return [Task::TYPE_SYNC_ARTIST, Task::TYPE_ADD_ARTIST];
    }

    public function supports(Task $task): bool
    {
        return \in_array($task->getType(), [Task::TYPE_SYNC_ARTIST, Task::TYPE_ADD_ARTIST], true);
    }
}
