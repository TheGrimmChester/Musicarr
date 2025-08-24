<?php

declare(strict_types=1);

namespace App\Task\Processor;

use App\Entity\Task;
use App\Manager\MusicLibraryManager;
use App\Repository\UnmatchedTrackRepository;
use App\Task\TaskFactory;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.task_processor')]
class AssociateArtistTaskProcessor implements TaskProcessorInterface
{
    public function __construct(
        private UnmatchedTrackRepository $unmatchedTrackRepository,
        private MusicLibraryManager $musicLibraryManager,
        private TaskFactory $taskService,
        private LoggerInterface $logger
    ) {
    }

    public function process(Task $task): TaskProcessorResult
    {
        try {
            $unmatchedTrackId = $task->getEntityId();
            $artistName = $task->getEntityName();
            $metadata = $task->getMetadata() ?? [];
            $mbid = $metadata['mbid'] ?? null;
            $libraryId = $metadata['library_id'] ?? null;
            $maxRetries = $metadata['max_retries'] ?? 3;

            if (!$unmatchedTrackId || !$artistName) {
                return TaskProcessorResult::failure('No unmatched track ID or artist name provided');
            }

            if (!$libraryId) {
                return TaskProcessorResult::failure('No library ID provided');
            }

            $this->logger->info('Processing associate artist task', [
                'unmatched_track_id' => $unmatchedTrackId,
                'artist_name' => $artistName,
                'mbid' => $mbid,
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

            // Sync the artist (create or update)
            $artist = $this->musicLibraryManager->syncArtistWithMbid($artistName, $mbid);

            if (!$artist) {
                return TaskProcessorResult::failure("Unable to sync artist {$artistName}");
            }

            // Mark track as matched
            $unmatchedTrack->setIsMatched(true);
            $this->unmatchedTrackRepository->save($unmatchedTrack, true);
            $this->unmatchedTrackRepository->remove($unmatchedTrack, true);

            $this->logger->info('Artist associated successfully', [
                'artist_id' => $artist->getId(),
                'artist_name' => $artist->getName(),
                'unmatched_track_id' => $unmatchedTrackId,
            ]);

            // Launch album synchronization in background if artist has MBID
            $artistId = $artist->getId();
            if (null !== $artistId && $artist->getMbid()) {
                $this->taskService->createTask(
                    Task::TYPE_SYNC_ARTIST_ALBUMS,
                    null,
                    $artistId,
                    $artist->getName(),
                    ['source_task_id' => $task->getId(), 'max_retries' => $maxRetries],
                    3 // Medium priority
                );

                $this->logger->info('Created sync albums task for associated artist', [
                    'artist_id' => $artistId,
                    'artist_name' => $artist->getName(),
                ]);
            }

            return TaskProcessorResult::success(
                \sprintf('Successfully associated artist "%s" with unmatched track', $artist->getName()),
                [
                    'artistId' => $artist->getId(),
                    'artistName' => $artist->getName(),
                    'artistMbid' => $artist->getMbid(),
                    'unmatchedTrackId' => $unmatchedTrackId,
                ]
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to associate artist', [
                'unmatched_track_id' => $task->getEntityId(),
                'artist_name' => $task->getEntityName(),
                'error' => $e->getMessage(),
            ]);

            return TaskProcessorResult::failure($e->getMessage());
        }
    }

    public function getSupportedTaskTypes(): array
    {
        return [Task::TYPE_ASSOCIATE_ARTIST];
    }

    public function supports(Task $task): bool
    {
        return Task::TYPE_ASSOCIATE_ARTIST === $task->getType();
    }
}
