<?php

declare(strict_types=1);

namespace App\Task\Processor;

use App\Entity\Task;
use App\Entity\TrackFile;
use App\Repository\LibraryRepository;
use App\Repository\TrackRepository;
use App\Task\TaskFactory;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.task_processor')]
class AnalyzeExistingTracksTaskProcessor implements TaskProcessorInterface
{
    public function __construct(
        private TrackRepository $trackRepository,
        private LibraryRepository $libraryRepository,
        private TaskFactory $taskService,
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
            $forceAnalysis = $metadata['force_analysis'] ?? false;

            $this->logger->info('Processing analyze existing tracks task', [
                'library_id' => $libraryId,
                'library_name' => $libraryName,
                'dry_run' => $dryRun,
                'force_analysis' => $forceAnalysis,
            ]);

            if ($dryRun) {
                return TaskProcessorResult::success(
                    'Analyze existing tracks (dry run) - no analysis performed',
                    [
                        'libraryId' => $libraryId,
                        'libraryName' => $libraryName,
                        'dryRun' => true,
                        'tasksCreated' => 0,
                    ]
                );
            }

            $tracksToAnalyze = [];

            if ($libraryId) {
                // Find tracks for specific library
                $library = $this->libraryRepository->find($libraryId);
                if (!$library) {
                    return TaskProcessorResult::failure("Library with ID {$libraryId} not found");
                }

                // Get tracks with files for this library
                $allTracks = $this->trackRepository->findAllWithFilesAndRelations();

                // Library filtering removed - process all tracks when libraryId is specified
                // Note: Artist no longer has library relationship
                $tracksToAnalyze = $allTracks;
            } else {
                // Get all tracks with files
                $tracksToAnalyze = $this->trackRepository->findAllWithFilesAndRelations();
            }

            if (!$forceAnalysis) {
                // Filter out tracks that already have quality analysis for ALL their files
                $tracksToAnalyze = array_filter($tracksToAnalyze, function ($track) {
                    $files = $track->getFiles();
                    if ($files->isEmpty()) {
                        return false;
                    }

                    // Check if ALL files already have quality analysis
                    foreach ($files as $file) {
                        if (!$file->getQuality()) {
                            return true; // This track needs analysis
                        }
                    }

                    return false; // All files already have quality analysis
                });
            }

            if (empty($tracksToAnalyze)) {
                return TaskProcessorResult::success(
                    'No tracks need audio analysis',
                    [
                        'libraryId' => $libraryId,
                        'libraryName' => $libraryName,
                        'tracksFound' => 0,
                        'tasksCreated' => 0,
                        'forceAnalysis' => $forceAnalysis,
                    ]
                );
            }

            $tasksCreated = 0;

            // Create individual audio analysis tasks for each track file
            foreach ($tracksToAnalyze as $track) {
                /** @var TrackFile $trackFile */
                foreach ($track->getFiles() as $trackFile) {
                    try {
                        $filePath = $trackFile->getFilePath();
                        if (null === $filePath || !file_exists($filePath)) {
                            continue;
                        }

                        // Skip if already has quality and not forcing
                        if (!$forceAnalysis && $trackFile->getQuality()) {
                            continue;
                        }

                        $this->taskService->createTask(
                            Task::TYPE_ANALYZE_AUDIO_QUALITY,
                            null,
                            $trackFile->getId(),
                            $trackFile->getFilePath(),
                            ['force_analysis' => $forceAnalysis],
                            2 // Lower priority for bulk analysis
                        );
                        ++$tasksCreated;
                    } catch (Exception $e) {
                        $this->logger->error('Failed to create audio analysis task', [
                            'track_id' => $track->getId(),
                            'track_file_id' => $trackFile->getId(),
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            $message = \sprintf(
                'Created %d audio analysis tasks for existing tracks in %s',
                $tasksCreated,
                $libraryId ? "library \"{$libraryName}\"" : 'all libraries'
            );

            $this->logger->info('Analyze existing tracks task completed', [
                'library_id' => $libraryId,
                'tracks_found' => \count($tracksToAnalyze),
                'tasks_created' => $tasksCreated,
                'force_analysis' => $forceAnalysis,
            ]);

            return TaskProcessorResult::success($message, [
                'libraryId' => $libraryId,
                'libraryName' => $libraryName,
                'tracksFound' => \count($tracksToAnalyze),
                'tasksCreated' => $tasksCreated,
                'forceAnalysis' => $forceAnalysis,
                'dryRun' => false,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to analyze existing tracks', [
                'library_id' => $task->getEntityId(),
                'library_name' => $task->getEntityName(),
                'error' => $e->getMessage(),
            ]);

            return TaskProcessorResult::failure($e->getMessage());
        }
    }

    public function getSupportedTaskTypes(): array
    {
        return [Task::TYPE_ANALYZE_EXISTING_TRACKS];
    }

    public function supports(Task $task): bool
    {
        return Task::TYPE_ANALYZE_EXISTING_TRACKS === $task->getType();
    }
}
