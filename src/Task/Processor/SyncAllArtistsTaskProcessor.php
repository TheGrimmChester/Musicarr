<?php

declare(strict_types=1);

namespace App\Task\Processor;

use App\Entity\Task;
use App\Repository\ArtistRepository;
use App\Task\TaskFactory;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.task_processor')]
class SyncAllArtistsTaskProcessor implements TaskProcessorInterface
{
    public function __construct(
        private ArtistRepository $artistRepository,
        private TaskFactory $taskService,
        private LoggerInterface $logger
    ) {
    }

    public function process(Task $task): TaskProcessorResult
    {
        try {
            $metadata = $task->getMetadata() ?? [];
            $maxRetries = $metadata['max_retries'] ?? 3;

            $this->logger->info('Processing sync all artists task', [
                'max_retries' => $maxRetries,
            ]);

            // Get all artists for the library (or all if no library specified)
            $queryBuilder = $this->artistRepository->createQueryBuilder('a');

            $artists = $queryBuilder->where('a.monitored = true')->getQuery()->getResult();

            if (empty($artists)) {
                return TaskProcessorResult::success(
                    'No artists found to sync',
                    ['artistCount' => 0]
                );
            }

            $tasksCreated = 0;
            $errors = [];

            foreach ($artists as $artist) {
                try {
                    $artistId = $artist->getId();
                    if (null !== $artistId && $artist->getMbid()) {
                        // Create sync artist albums task for each artist
                        $this->taskService->createTask(
                            Task::TYPE_SYNC_ARTIST,
                            null,
                            $artistId,
                            $artist->getName(),
                            ['source_task_id' => $task->getId(), 'max_retries' => $maxRetries],
                            3 // Medium priority
                        );
                        // Create sync artist albums task for each artist
                        $this->taskService->createTask(
                            Task::TYPE_SYNC_ARTIST_ALBUMS,
                            null,
                            $artistId,
                            $artist->getName(),
                            ['source_task_id' => $task->getId(), 'max_retries' => $maxRetries],
                            3 // Medium priority
                        );
                        ++$tasksCreated;
                    }
                } catch (Exception $e) {
                    $errors[] = \sprintf(
                        'Failed to create sync task for artist %s: %s',
                        $artist->getName(),
                        $e->getMessage()
                    );
                    $this->logger->error('Failed to create sync task for artist', [
                        'artist_id' => $artist->getId(),
                        'artist_name' => $artist->getName(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $message = \sprintf('Created %d sync tasks for %d artists', $tasksCreated, \count($artists));
            if (!empty($errors)) {
                $message .= \sprintf(' (%d errors)', \count($errors));
            }

            $this->logger->info('Sync all artists task completed', [
                'artists_total' => \count($artists),
                'tasks_created' => $tasksCreated,
                'errors' => \count($errors),
            ]);

            return TaskProcessorResult::success($message, [
                'artistsTotal' => \count($artists),
                'tasksCreated' => $tasksCreated,
                'errors' => $errors,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to sync all artists', [
                'library_id' => $task->getEntityId(),
                'error' => $e->getMessage(),
            ]);

            return TaskProcessorResult::failure($e->getMessage());
        }
    }

    public function getSupportedTaskTypes(): array
    {
        return [Task::TYPE_SYNC_ALL_ARTISTS];
    }

    public function supports(Task $task): bool
    {
        return Task::TYPE_SYNC_ALL_ARTISTS === $task->getType();
    }
}
