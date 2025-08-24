<?php

declare(strict_types=1);

namespace App\Task\Processor;

use App\Entity\Task;
use App\Manager\MusicLibraryManager;
use App\Repository\ArtistRepository;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.task_processor')]
class SyncArtistAlbumsTaskProcessor implements TaskProcessorInterface
{
    public function __construct(
        private MusicLibraryManager $musicLibraryManager,
        private ArtistRepository $artistRepository,
        private LoggerInterface $logger
    ) {
    }

    public function process(Task $task): TaskProcessorResult
    {
        try {
            $artistId = $task->getEntityId();

            if (!$artistId) {
                return TaskProcessorResult::failure('No artist ID provided');
            }

            $artist = $this->artistRepository->find($artistId);
            if (!$artist) {
                return TaskProcessorResult::failure("Artist with ID {$artistId} not found");
            }

            $this->logger->info("Syncing albums for artist: {$artist->getName()} (ID: {$artistId})");

            // Sync artist albums using the music library manager
            $result = $this->musicLibraryManager->syncArtistAlbums($artist);

            if (!$result) {
                return TaskProcessorResult::failure("Failed to sync albums for artist: {$artist->getName()}");
            }

            $this->logger->info("Successfully synced albums for artist: {$artist->getName()}");

            return TaskProcessorResult::success(
                \sprintf('Successfully synced albums for artist "%s"', $artist->getName()),
                [
                    'artistId' => $artist->getId(),
                    'artistName' => $artist->getName(),
                    'artistMbid' => $artist->getMbid(),
                ]
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to sync artist albums', [
                'artistId' => $task->getEntityId(),
                'error' => $e->getMessage(),
            ]);

            return TaskProcessorResult::failure($e->getMessage());
        }
    }

    public function getSupportedTaskTypes(): array
    {
        return [Task::TYPE_SYNC_ARTIST_ALBUMS];
    }

    public function supports(Task $task): bool
    {
        return Task::TYPE_SYNC_ARTIST_ALBUMS === $task->getType();
    }
}
