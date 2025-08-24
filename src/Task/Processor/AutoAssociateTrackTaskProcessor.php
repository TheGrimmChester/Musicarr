<?php

declare(strict_types=1);

namespace App\Task\Processor;

use App\Configuration\Domain\AssociationConfigurationDomain;
use App\Entity\Task;
use App\Repository\UnmatchedTrackRepository;
use App\UnmatchedTrackAssociation\UnmatchedTrackAssociationChain;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.task_processor')]
class AutoAssociateTrackTaskProcessor implements TaskProcessorInterface
{
    public function __construct(
        private AssociationConfigurationDomain $associationDomain,
        private UnmatchedTrackRepository $unmatchedTrackRepository,
        private UnmatchedTrackAssociationChain $unmatchedTrackAssociationChain,
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

            $unmatchedTrackId = $task->getEntityId();
            $trackTitle = $task->getEntityName();
            $metadata = $task->getMetadata() ?? [];

            $sourceTaskId = $metadata['source_task_id'] ?? null;
            $libraryId = $metadata['library_id'] ?? null;
            $artistName = $metadata['artist'] ?? null;
            $albumName = $metadata['album'] ?? null;

            if (!$unmatchedTrackId) {
                return TaskProcessorResult::failure('No unmatched track ID provided');
            }

            $this->logger->info('Processing auto associate track task', [
                'unmatched_track_id' => $unmatchedTrackId,
                'track_title' => $trackTitle,
                'source_task_id' => $sourceTaskId,
                'library_id' => $libraryId,
                'artist' => $artistName,
                'album' => $albumName,
            ]);

            // Get the unmatched track
            $unmatchedTrack = $this->unmatchedTrackRepository->find($unmatchedTrackId);
            if (!$unmatchedTrack) {
                return TaskProcessorResult::failure(
                    "Unmatched track {$unmatchedTrackId} not found (may have been processed already)",
                    [
                        'unmatchedTrackId' => $unmatchedTrackId,
                        'status' => 'not_found',
                        'trackTitle' => $trackTitle,
                    ]
                );
            }

            // Skip if already matched
            if ($unmatchedTrack->isMatched()) {
                return TaskProcessorResult::success(
                    "Track '{$trackTitle}' is already matched",
                    [
                        'unmatchedTrackId' => $unmatchedTrackId,
                        'status' => 'already_matched',
                        'trackTitle' => $trackTitle,
                    ]
                );
            }

            // Run the association chain
            $result = $this->unmatchedTrackAssociationChain->executeChain([$unmatchedTrack], [], $this->logger);

            if ($result) {
                $this->logger->info('Successfully auto-associated track', [
                    'unmatched_track_id' => $unmatchedTrackId,
                    'track_title' => $trackTitle,
                    'association_result' => $result,
                ]);

                return TaskProcessorResult::success(
                    \sprintf('Successfully auto-associated track "%s"', $trackTitle),
                    [
                        'result' => $result,
                        'unmatchedTrackId' => $unmatchedTrackId,
                        'trackTitle' => $trackTitle,
                        'status' => 'associated',
                        'associationResult' => $result,
                        'sourceTaskId' => $sourceTaskId,
                    ]
                );
            }
            $this->logger->info('Could not auto-associate track', [
                'unmatched_track_id' => $unmatchedTrackId,
                'track_title' => $trackTitle,
            ]);

            return TaskProcessorResult::success(
                \sprintf('Could not auto-associate track "%s" - no suitable match found', $trackTitle),
                [
                    'unmatchedTrackId' => $unmatchedTrackId,
                    'trackTitle' => $trackTitle,
                    'status' => 'no_match',
                    'sourceTaskId' => $sourceTaskId,
                ]
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to auto-associate track', [
                'unmatched_track_id' => $task->getEntityId(),
                'track_title' => $task->getEntityName(),
                'error' => $e->getMessage(),
            ]);

            return TaskProcessorResult::failure($e->getMessage());
        }
    }

    public function getSupportedTaskTypes(): array
    {
        return [Task::TYPE_AUTO_ASSOCIATE_TRACK];
    }

    public function supports(Task $task): bool
    {
        return Task::TYPE_AUTO_ASSOCIATE_TRACK === $task->getType();
    }
}
