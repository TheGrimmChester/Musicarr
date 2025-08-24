<?php

declare(strict_types=1);

namespace App\Task\Processor;

use App\Configuration\Domain\AssociationConfigurationDomain;
use App\Entity\Task;
use App\Repository\UnmatchedTrackRepository;
use App\Task\TaskFactory;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.task_processor')]
class AutoAssociateTracksTaskProcessor implements TaskProcessorInterface
{
    public function __construct(
        private AssociationConfigurationDomain $associationDomain,
        private UnmatchedTrackRepository $unmatchedTrackRepository,
        private TaskFactory $taskService,
        private LoggerInterface $logger
    ) {
    }

    public function process(Task $task): TaskProcessorResult
    {
        try {
            // Check if auto association is enabled
            if (!$this->associationDomain->isAutoAssociationEnabled()) {
                return TaskProcessorResult::failure('Auto association is disabled in configuration');
            }

            $libraryId = $task->getEntityId();
            $libraryName = $task->getEntityName();
            $metadata = $task->getMetadata() ?? [];
            $dryRun = $metadata['dry_run'] ?? false;
            $limit = $metadata['limit'] ?? null;

            $this->logger->info('Processing auto associate tracks task', [
                'library_id' => $libraryId,
                'library_name' => $libraryName,
                'dry_run' => $dryRun,
                'limit' => $limit,
            ]);

            // Get unmatched tracks
            if ($libraryId) {
                $unmatchedTracks = $this->unmatchedTrackRepository->findUnmatchedByLibrary($libraryId);
            } else {
                $criteria = ['isMatched' => false];
                $orderBy = ['discoveredAt' => 'DESC'];
                $unmatchedTracks = $this->unmatchedTrackRepository->findBy($criteria, $orderBy, $limit);
            }

            if (empty($unmatchedTracks)) {
                return TaskProcessorResult::success(
                    'No unmatched tracks found for auto association',
                    [
                        'libraryId' => $libraryId,
                        'libraryName' => $libraryName,
                        'unmatchedTracksCount' => 0,
                        'dryRun' => $dryRun,
                    ]
                );
            }

            $processedCount = 0;
            $successCount = 0;
            $skippedCount = 0;
            $errors = [];

            foreach ($unmatchedTracks as $unmatchedTrack) {
                try {
                    if ($limit && $processedCount >= $limit) {
                        break;
                    }

                    ++$processedCount;

                    // Skip if already matched
                    if ($unmatchedTrack->isMatched()) {
                        ++$skippedCount;

                        continue;
                    }

                    if ($dryRun) {
                        // In dry run mode, just log what would be processed
                        $this->logger->info('Would process unmatched track (dry run)', [
                            'unmatched_track_id' => $unmatchedTrack->getId(),
                            'title' => $unmatchedTrack->getTitle(),
                            'artist' => $unmatchedTrack->getArtist(),
                            'album' => $unmatchedTrack->getAlbum(),
                        ]);
                        ++$successCount;
                    } else {
                        // Create individual auto association task for each track
                        $this->taskService->createTask(
                            Task::TYPE_AUTO_ASSOCIATE_TRACK,
                            null,
                            $unmatchedTrack->getId(),
                            $unmatchedTrack->getTitle(),
                            [
                                'source_task_id' => $task->getId(),
                                'library_id' => $libraryId,
                                'artist' => $unmatchedTrack->getArtist(),
                                'album' => $unmatchedTrack->getAlbum(),
                            ],
                            2 // Lower priority for individual track association
                        );
                        ++$successCount;
                    }
                } catch (Exception $e) {
                    $errors[] = \sprintf(
                        'Failed to process track %s: %s',
                        $unmatchedTrack->getTitle(),
                        $e->getMessage()
                    );

                    $this->logger->error('Error processing unmatched track', [
                        'unmatched_track_id' => $unmatchedTrack->getId(),
                        'title' => $unmatchedTrack->getTitle(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $message = \sprintf(
                'Auto association %s: processed %d tracks, %d successful, %d skipped',
                $dryRun ? 'dry run completed' : 'completed',
                $processedCount,
                $successCount,
                $skippedCount
            );

            if (!empty($errors)) {
                $message .= \sprintf(' (%d errors)', \count($errors));
            }

            $this->logger->info('Auto association task completed', [
                'library_id' => $libraryId,
                'processed_count' => $processedCount,
                'success_count' => $successCount,
                'skipped_count' => $skippedCount,
                'errors_count' => \count($errors),
                'dry_run' => $dryRun,
            ]);

            return TaskProcessorResult::success($message, [
                'libraryId' => $libraryId,
                'libraryName' => $libraryName,
                'unmatchedTracksTotal' => \count($unmatchedTracks),
                'processedCount' => $processedCount,
                'successCount' => $successCount,
                'skippedCount' => $skippedCount,
                'errorsCount' => \count($errors),
                'errors' => $errors,
                'dryRun' => $dryRun,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to auto associate tracks', [
                'library_id' => $task->getEntityId(),
                'error' => $e->getMessage(),
            ]);

            return TaskProcessorResult::failure($e->getMessage());
        }
    }

    public function getSupportedTaskTypes(): array
    {
        return [Task::TYPE_AUTO_ASSOCIATE_TRACKS];
    }

    public function supports(Task $task): bool
    {
        return Task::TYPE_AUTO_ASSOCIATE_TRACKS === $task->getType();
    }
}
