<?php

declare(strict_types=1);

namespace App\Task\Processor;

use App\Entity\Task;
use App\Manager\AlbumStatusManager;
use App\Repository\LibraryRepository;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.task_processor')]
class UpdateAlbumStatusesTaskProcessor implements TaskProcessorInterface
{
    public function __construct(
        private AlbumStatusManager $albumStatusManager,
        private LibraryRepository $libraryRepository,
        private LoggerInterface $logger
    ) {
    }

    public function process(Task $task): TaskProcessorResult
    {
        try {
            $libraryId = $task->getEntityId();
            $libraryName = $task->getEntityName();
            $metadata = $task->getMetadata() ?? [];
            $dryRun = $metadata['dry_run'] ?? false;

            $this->logger->info('Processing update album statuses task', [
                'library_id' => $libraryId,
                'library_name' => $libraryName,
                'dry_run' => $dryRun,
            ]);

            if ($dryRun) {
                return TaskProcessorResult::success(
                    'Album status update (dry run) - no changes made',
                    [
                        'libraryId' => $libraryId,
                        'libraryName' => $libraryName,
                        'dryRun' => true,
                        'updatedCount' => 0,
                    ]
                );
            }

            $updatedCount = 0;

            if ($libraryId) {
                // Update album statuses for specific library
                $library = $this->libraryRepository->find($libraryId);
                if (!$library) {
                    return TaskProcessorResult::failure("Library with ID {$libraryId} not found");
                }

                // Update album statuses for this library's artists
                $artists = $library->getArtists();
                foreach ($artists as $artist) {
                    $artistUpdatedCount = $this->albumStatusManager->updateArtistAlbumStatuses($artist->getId());
                    $updatedCount += $artistUpdatedCount;
                }

                $this->logger->info("Updated album statuses for library: {$library->getName()}", [
                    'library_id' => $libraryId,
                    'artists_count' => $artists->count(),
                    'albums_updated' => $updatedCount,
                ]);

                return TaskProcessorResult::success(
                    \sprintf('Updated album statuses for %d albums in library "%s"', $updatedCount, $library->getName()),
                    [
                        'libraryId' => $libraryId,
                        'libraryName' => $library->getName(),
                        'artistsCount' => $artists->count(),
                        'updatedCount' => $updatedCount,
                        'dryRun' => false,
                    ]
                );
            }
            // Update all album statuses globally
            $updatedCount = $this->albumStatusManager->updateAllAlbumStatuses();

            $this->logger->info('Updated all album statuses globally', [
                'albums_updated' => $updatedCount,
            ]);

            return TaskProcessorResult::success(
                \sprintf('Updated album statuses for %d albums globally', $updatedCount),
                [
                    'updatedCount' => $updatedCount,
                    'scope' => 'global',
                    'dryRun' => false,
                ]
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to update album statuses', [
                'library_id' => $task->getEntityId(),
                'library_name' => $task->getEntityName(),
                'error' => $e->getMessage(),
            ]);

            return TaskProcessorResult::failure($e->getMessage());
        }
    }

    public function getSupportedTaskTypes(): array
    {
        return [Task::TYPE_UPDATE_ALBUM_STATUSES];
    }

    public function supports(Task $task): bool
    {
        return Task::TYPE_UPDATE_ALBUM_STATUSES === $task->getType();
    }
}
