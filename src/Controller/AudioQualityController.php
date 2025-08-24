<?php

declare(strict_types=1);

namespace App\Controller;

use App\Configuration\Domain\ConfigurationDomainRegistry;
use App\Entity\Album;
use App\Entity\Configuration;
use App\Entity\Task;
use App\Entity\Track;
use App\Repository\ConfigurationRepository;
use App\Repository\TrackRepository;
use App\Task\TaskFactory;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/audio-quality')]
class AudioQualityController extends AbstractController
{
    public function __construct(
        private TrackRepository $trackRepository,
        private TaskFactory $taskService,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
        private ConfigurationDomainRegistry $domainRegistry,
        private EntityManagerInterface $entityManager,
        private ConfigurationRepository $configurationRepository
    ) {
    }

    #[Route('/', name: 'audio_quality_index', methods: ['GET'])]
    public function index(): Response
    {
        // Get the audio quality domain from the registry
        $audioQualityDomain = $this->domainRegistry->getDomain('audio_quality.');

        // Initialize audio quality configuration with defaults
        if ($audioQualityDomain) {
            $audioQualityDomain->initializeDefaults();
        }

        // Get configuration using the new domain system
        $config = $audioQualityDomain ? $audioQualityDomain->getAllConfig() : [];

        return $this->render('audio_quality/index.html.twig', [
            'config' => $config,
        ]);
    }

    #[Route('/save', name: 'audio_quality_save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        try {
            // Handle both JSON and form data
            $content = $request->getContent();
            if ('json' === $request->getContentTypeFormat() || (!empty($content) && null !== json_decode($content))) {
                $data = json_decode($content, true);
                // For JSON data, wrap it in audio_quality_config if it's not already wrapped
                if (\is_array($data) && !isset($data['audio_quality_config'])) {
                    $data = ['audio_quality_config' => $data];
                }
            } else {
                $data = $request->request->all();
            }

            // Check for invalid data
            if (null === $data || false === $data) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid data',
                ], 400);
            }

            // Get the audio quality domain from the registry
            $audioQualityDomain = $this->domainRegistry->getDomain('audio_quality.');

            if (!$audioQualityDomain) {
                throw new Exception('Audio quality domain not found');
            }

            // Initialize the config array to save
            $configToSave = [];

            // Process the submitted data with normalization
            $audioQualityConfig = \is_array($data) ? ($data['audio_quality_config'] ?? []) : [];
            foreach ($audioQualityConfig as $key => $value) {
                // Normalize boolean-like values
                if (\is_string($value)) {
                    $lower = mb_strtolower($value);
                    if (\in_array($lower, ['on', 'true', '1'], true)) {
                        $value = true;
                    } elseif (\in_array($lower, ['off', 'false', '0'], true)) {
                        $value = false;
                    }
                }

                // Store the value to save to database later
                $configToSave[$key] = $value;
            }

            // Validate provided configuration values (partial updates allowed)
            $validationErrors = $this->validateAudioQualityData($configToSave);
            if (!empty($validationErrors)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid data',
                    'details' => $validationErrors,
                ], 400);
            }

            // Save all configurations to database
            $this->saveConfigurationToDatabase($configToSave);

            return $this->json(['success' => true]);
        } catch (Exception $e) {
            $this->logger->error('Error saving audio quality configuration: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/get', name: 'audio_quality_get', methods: ['GET'])]
    public function getConfiguration(): JsonResponse
    {
        try {
            // Get the audio quality domain from the registry
            $audioQualityDomain = $this->domainRegistry->getDomain('audio_quality.');

            if (!$audioQualityDomain) {
                throw new Exception('Audio quality domain not found');
            }

            // Get configuration using the new domain system
            $config = $audioQualityDomain->getAllConfig();

            return $this->json([
                'success' => true,
                'data' => $config,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error fetching audio quality configuration: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'Error fetching configuration',
            ], 500);
        }
    }

    #[Route('/stats', name: 'audio_quality_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        return $this->json([
            'total_tracks' => 0,
            'analyzed_tracks' => 0,
            'pending_tracks' => 0,
        ]);
    }

    #[Route('/delete', name: 'audio_quality_delete', methods: ['DELETE'])]
    public function deleteConfiguration(): JsonResponse
    {
        try {
            // Get the audio quality domain from the registry
            $audioQualityDomain = $this->domainRegistry->getDomain('audio_quality.');

            if (!$audioQualityDomain) {
                throw new Exception('Audio quality domain not found');
            }

            // Delete all audio quality configurations
            $configurations = $this->configurationRepository->findByKeyPrefix('audio_quality.');

            foreach ($configurations as $config) {
                $this->entityManager->remove($config);
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Audio quality configuration deleted successfully',
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error deleting audio quality configuration: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'Error deleting configuration',
            ], 500);
        }
    }

    #[Route('/analyze/{id}', name: 'audio_quality_analyze', methods: ['POST'])]
    public function analyzeTrack(Track $track): JsonResponse
    {
        $files = $track->getFiles();
        if ($files->isEmpty()) {
            return $this->json([
                'success' => false,
                'error' => $this->translator->trans('audio_quality.no_files_found'),
            ], 404);
        }

        $tasksCreated = 0;
        $errors = [];

        // Analyze all files for the track, not just the preferred file
        foreach ($files as $file) {
            $filePath = $file->getFilePath();
            if (null === $filePath || !file_exists($filePath)) {
                $errors[] = 'File not found: ' . basename($filePath);

                continue;
            }

            try {
                $trackFileId = $file->getId();
                if (null === $trackFileId) {
                    $errors[] = 'File ID not found for: ' . basename($filePath);

                    continue;
                }

                $this->taskService->createTask(
                    Task::TYPE_ANALYZE_AUDIO_QUALITY,
                    null,
                    $trackFileId,
                    $filePath,
                    [],
                    3
                );
                ++$tasksCreated;
            } catch (Exception $e) {
                $errors[] = 'Error creating task for ' . basename($filePath) . ': ' . $e->getMessage();
            }
        }

        if (0 === $tasksCreated) {
            return $this->json([
                'success' => false,
                'error' => 'No analysis tasks could be created: ' . implode('; ', $errors),
            ], 500);
        }

        return $this->json([
            'success' => true,
            'message' => $this->translator->trans('audio_quality.analysis_tasks_created', ['%count%' => $tasksCreated]),
            'task_type' => 'audio_quality_analysis',
            'track_id' => $track->getId(),
            'tasks_created' => $tasksCreated,
            'errors' => $errors,
        ]);
    }

    #[Route('/analyze-batch', name: 'audio_quality_analyze_batch', methods: ['POST'])]
    public function analyzeBatch(Request $request): JsonResponse
    {
        $trackIds = $request->request->all('track_ids');

        if (empty($trackIds)) {
            return $this->json([
                'success' => false,
                'error' => $this->translator->trans('audio_quality.no_tracks_specified'),
            ], 400);
        }

        $tracks = $this->trackRepository->findByIdsWithRelations($trackIds);
        $taskCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($tracks as $track) {
            $files = $track->getFiles();
            if ($files->isEmpty()) {
                ++$errorCount;
                $errors[] = "Track {$track->getId()} has no files";

                continue;
            }

            // Analyze all files for each track, not just the preferred file
            foreach ($files as $file) {
                $filePath = $file->getFilePath();
                if (null === $filePath || !file_exists($filePath)) {
                    ++$errorCount;
                    $errors[] = "File not found for track {$track->getId()}: " . basename($filePath);

                    continue;
                }

                try {
                    $trackFileId = $file->getId();
                    if (null === $trackFileId) {
                        ++$errorCount;
                        $errors[] = "File ID not found for track {$track->getId()}: " . basename($filePath);

                        continue;
                    }

                    $this->taskService->createTask(
                        Task::TYPE_ANALYZE_AUDIO_QUALITY,
                        null,
                        $trackFileId,
                        $filePath,
                        [],
                        3
                    );
                    ++$taskCount;
                } catch (Exception $e) {
                    ++$errorCount;
                    $errors[] = "Error creating task for track {$track->getId()}, file " . basename($filePath) . ': ' . $e->getMessage();
                }
            }
        }

        return $this->json([
            'success' => true,
            'message' => $this->translator->trans('audio_quality.batch_analysis_completed', [
                '%success%' => $taskCount,
                '%errors%' => $errorCount,
            ]),
            'tasks_created' => $taskCount,
            'errors_count' => $errorCount,
            'errors' => $errors,
        ]);
    }

    #[Route('/status/{id}', name: 'audio_quality_status', methods: ['GET'])]
    public function getTrackAnalysisStatus(Track $track): JsonResponse
    {
        $files = $track->getFiles();
        if ($files->isEmpty()) {
            return $this->json([
                'success' => false,
                'error' => $this->translator->trans('audio_quality.no_files_found'),
            ], 404);
        }

        $fileStatuses = [];
        $totalFiles = 0;
        $filesWithQuality = 0;
        $activeTasks = 0;

        foreach ($files as $file) {
            $trackFileId = $file->getId();
            if (null === $trackFileId) {
                continue;
            }

            try {
                // Check if there are any pending or in-progress tasks for this track file
                $pendingTasks = $this->taskService->findTasksByEntityId($trackFileId, Task::TYPE_ANALYZE_AUDIO_QUALITY);

                $hasActiveTask = false;
                $taskStatus = null;

                foreach ($pendingTasks as $task) {
                    if (\in_array($task->getStatus(), ['pending', 'in_progress'], true)) {
                        $hasActiveTask = true;
                        $taskStatus = $task->getStatus();
                        ++$activeTasks;

                        break;
                    }
                }

                // Check if the track file already has quality information
                $hasQuality = null !== $file->getQuality();
                if ($hasQuality) {
                    ++$filesWithQuality;
                }

                $fileStatuses[] = [
                    'file_id' => $trackFileId,
                    'file_path' => basename($file->getFilePath()),
                    'has_active_task' => $hasActiveTask,
                    'task_status' => $taskStatus,
                    'has_quality' => $hasQuality,
                    'quality' => $file->getQuality(),
                    'format' => $file->getFormat(),
                    'duration' => $file->getDuration(),
                ];

                ++$totalFiles;
            } catch (Exception $e) {
                $fileStatuses[] = [
                    'file_id' => $trackFileId,
                    'file_path' => basename($file->getFilePath()),
                    'error' => 'Error checking status: ' . $e->getMessage(),
                ];
            }
        }

        $overallStatus = [
            'track_id' => $track->getId(),
            'total_files' => $totalFiles,
            'files_with_quality' => $filesWithQuality,
            'files_without_quality' => $totalFiles - $filesWithQuality,
            'active_tasks' => $activeTasks,
            'overall_completed' => $filesWithQuality > 0 && 0 === $activeTasks,
            'overall_in_progress' => $activeTasks > 0,
            'completion_percentage' => $totalFiles > 0 ? round(($filesWithQuality / $totalFiles) * 100, 2) : 0,
        ];

        return $this->json([
            'success' => true,
            'overall_status' => $overallStatus,
            'file_statuses' => $fileStatuses,
        ]);
    }

    #[Route('/analyze-album/{id}', name: 'audio_quality_analyze_album', methods: ['POST'])]
    public function analyzeAlbum(int $id): JsonResponse
    {
        try {
            // Get the album with all its tracks
            $album = $this->entityManager->getRepository(Album::class)->find($id);

            if (!$album) {
                return $this->json([
                    'success' => false,
                    'error' => 'Album not found',
                ], 404);
            }

            // Use MusicLibraryManager to get tracks properly
            $tracks = $this->trackRepository->findBy(['album' => $id], ['mediumNumber' => 'ASC', 'trackNumber' => 'ASC']);

            if (empty($tracks)) {
                return $this->json([
                    'success' => false,
                    'error' => 'No tracks found for this album',
                ], 400);
            }

            $this->logger->info('Analyzing album audio quality', [
                'album_id' => $id,
                'album_title' => $album->getTitle(),
                'tracks_count' => \count($tracks),
            ]);

            // Get all tracks for this album and analyze all their files
            $tracksWithFiles = [];
            $taskCount = 0;
            $errorCount = 0;
            $errors = [];
            $tracksWithoutFiles = 0;

            foreach ($tracks as $track) {
                $files = $track->getFiles();
                if ($files->isEmpty()) {
                    ++$tracksWithoutFiles;

                    continue;
                }

                $trackTaskCount = 0;
                $trackErrors = [];

                // Analyze all files for each track, not just the preferred file
                foreach ($files as $file) {
                    $filePath = $file->getFilePath();
                    if (null === $filePath || !file_exists($filePath)) {
                        $trackErrors[] = 'File not found: ' . ($filePath ? basename($filePath) : 'unknown');
                        $this->logger->warning('File not found on disk', [
                            'track_id' => $track->getId(),
                            'file_path' => $filePath,
                        ]);

                        continue;
                    }

                    try {
                        $trackFileId = $file->getId();
                        if (null === $trackFileId) {
                            $trackErrors[] = 'File ID not found for: ' . basename($filePath);

                            continue;
                        }

                        // Create audio quality analysis task for this file
                        $this->taskService->createTask(
                            Task::TYPE_ANALYZE_AUDIO_QUALITY,
                            null,
                            $trackFileId,
                            $filePath,
                            ['album_id' => $id, 'track_id' => $track->getId()],
                            2 // Lower priority for album-wide analysis
                        );

                        ++$trackTaskCount;
                        ++$taskCount;
                    } catch (Exception $e) {
                        $trackErrors[] = 'Error creating task for ' . ($filePath ? basename($filePath) : 'unknown') . ': ' . $e->getMessage();
                        ++$errorCount;
                        $this->logger->error('Failed to create audio analysis task', [
                            'track_id' => $track->getId(),
                            'track_file_id' => $file->getId(),
                            'file_path' => $filePath,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                if ($trackTaskCount > 0) {
                    $tracksWithFiles[] = [
                        'id' => $track->getId(),
                        'title' => $track->getTitle(),
                        'tasks_created' => $trackTaskCount,
                        'errors' => $trackErrors,
                    ];
                }

                if (!empty($trackErrors)) {
                    $errors = array_merge($errors, $trackErrors);
                }
            }

            $this->logger->info('Album audio analysis completed', [
                'album_id' => $id,
                'tracks_total' => \count($tracks),
                'tracks_with_files' => \count($tracksWithFiles),
                'tracks_without_files' => $tracksWithoutFiles,
                'tasks_created' => $taskCount,
                'errors' => $errorCount,
            ]);

            if (0 === $taskCount) {
                return $this->json([
                    'success' => false,
                    'error' => \sprintf('No tracks with files found for analysis. Total tracks: %d, tracks without files: %d', \count($tracks), $tracksWithoutFiles),
                    'details' => [
                        'total_tracks' => \count($tracks),
                        'tracks_with_files' => \count($tracksWithFiles),
                        'tracks_without_files' => $tracksWithoutFiles,
                    ],
                ], 400);
            }

            return $this->json([
                'success' => true,
                'message' => \sprintf('Audio quality analysis tasks created for %d files across %d tracks', $taskCount, \count($tracksWithFiles)),
                'album_id' => $id,
                'album_title' => $album->getTitle() ?? 'Unknown',
                'files_analyzed' => $taskCount,
                'tracks_analyzed' => \count($tracksWithFiles),
                'total_tracks' => \count($tracks),
                'tracks_without_files' => $tracksWithoutFiles,
                'errors' => $errors,
                'tracks' => $tracksWithFiles,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error creating album analysis tasks', [
                'album_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Error creating album analysis tasks: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/album-status/{id}', name: 'audio_quality_album_status', methods: ['GET'])]
    public function getAlbumAnalysisStatus(int $id): JsonResponse
    {
        try {
            // Get the album
            $album = $this->trackRepository->getEntityManager()->getRepository(Album::class)->find($id);

            if (!$album) {
                return $this->json([
                    'success' => false,
                    'error' => 'Album not found',
                ], 404);
            }

            // Get all tracks for this album
            $tracks = $this->trackRepository->findBy(['album' => $id], ['mediumNumber' => 'ASC', 'trackNumber' => 'ASC']);

            if (empty($tracks)) {
                return $this->json([
                    'success' => false,
                    'error' => 'No tracks found for this album',
                ], 400);
            }

            $totalTracks = 0;
            $tracksWithFiles = 0;
            $tracksWithQuality = 0;
            $activeTasks = 0;
            $overallStatus = 'unknown';

            foreach ($tracks as $track) {
                $files = $track->getFiles();
                if ($files->isEmpty()) {
                    continue;
                }

                ++$totalTracks;
                ++$tracksWithFiles;
                $trackHasQuality = false;
                $trackHasActiveTask = false;

                foreach ($files as $file) {
                    if ($file->getQuality()) {
                        $trackHasQuality = true;
                    }

                    try {
                        $trackFileId = $file->getId();
                        if ($trackFileId) {
                            $pendingTasks = $this->taskService->findTasksByEntityId($trackFileId, Task::TYPE_ANALYZE_AUDIO_QUALITY);
                            foreach ($pendingTasks as $task) {
                                if (\in_array($task->getStatus(), ['pending', 'in_progress'], true)) {
                                    $trackHasActiveTask = true;
                                    ++$activeTasks;

                                    break;
                                }
                            }
                        }
                    } catch (Exception $e) {
                        $this->logger->warning('Error checking task status for file', [
                            'track_id' => $track->getId(),
                            'file_id' => $file->getId(),
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                if ($trackHasQuality) {
                    ++$tracksWithQuality;
                }
            }

            // Determine overall status
            if (0 === $tracksWithFiles) {
                $overallStatus = 'no_files';
            } elseif ($activeTasks > 0) {
                $overallStatus = 'in_progress';
            } elseif ($tracksWithQuality === $tracksWithFiles) {
                $overallStatus = 'completed';
            } else {
                $overallStatus = 'partial';
            }

            $completionPercentage = $tracksWithFiles > 0 ? round(($tracksWithQuality / $tracksWithFiles) * 100, 2) : 0;

            return $this->json([
                'success' => true,
                'album_id' => $id,
                'album_title' => $album->getTitle() ?? 'Unknown',
                'total_tracks' => $totalTracks,
                'tracks_with_files' => $tracksWithFiles,
                'tracks_with_quality' => $tracksWithQuality,
                'active_tasks' => $activeTasks,
                'overall_status' => $overallStatus,
                'completion_percentage' => $completionPercentage,
                'completed' => 'completed' === $overallStatus,
                'in_progress' => 'in_progress' === $overallStatus,
                'no_files' => 'no_files' === $overallStatus,
                'partial' => 'partial' === $overallStatus,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error getting album analysis status', [
                'album_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Error getting album analysis status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save configuration to database.
     */
    private function saveConfigurationToDatabase(array $config): void
    {
        foreach ($config as $key => $value) {
            $fullKey = 'audio_quality.' . $key;

            // Find existing configuration or create new one
            $existingConfig = $this->entityManager->getRepository(Configuration::class)
                ->findOneBy(['key' => $fullKey]);

            if (null !== $existingConfig) {
                // Update existing configuration
                $existingConfig->setParsedValue($value);
                $this->entityManager->persist($existingConfig);
            } else {
                // Create new configuration
                $newConfig = new Configuration();
                $newConfig->setKey($fullKey);
                $newConfig->setParsedValue($value);
                $newConfig->setDescription('Audio quality configuration');

                $this->entityManager->persist($newConfig);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Validate audio quality configuration payload (partial updates allowed).
     */
    private function validateAudioQualityData(array $data): array
    {
        $errors = [];

        if (\array_key_exists('enabled', $data) && !\is_bool($data['enabled'])) {
            $errors['enabled'] = 'must be a boolean';
        }

        if (\array_key_exists('analyze_existing', $data) && !\is_bool($data['analyze_existing'])) {
            $errors['analyze_existing'] = 'must be a boolean';
        }

        if (\array_key_exists('auto_convert', $data) && !\is_bool($data['auto_convert'])) {
            $errors['auto_convert'] = 'must be a boolean';
        }

        if (\array_key_exists('min_bitrate', $data)) {
            if (!\is_int($data['min_bitrate'])) {
                $errors['min_bitrate'] = 'must be an integer';
            } elseif ($data['min_bitrate'] < 32 || $data['min_bitrate'] > 2000) {
                $errors['min_bitrate'] = 'out of range (32-2000)';
            }
        }

        if (\array_key_exists('quality_threshold', $data)) {
            if (!\is_float($data['quality_threshold']) && !\is_int($data['quality_threshold'])) {
                $errors['quality_threshold'] = 'must be a number';
            } elseif ($data['quality_threshold'] < 0 || $data['quality_threshold'] > 1) {
                $errors['quality_threshold'] = 'out of range (0-1)';
            }
        }

        if (\array_key_exists('preferred_format', $data)) {
            $allowed = ['mp3', 'flac', 'aac', 'ogg', 'wav'];
            if (!\is_string($data['preferred_format']) || !\in_array(mb_strtolower($data['preferred_format']), $allowed, true)) {
                $errors['preferred_format'] = 'invalid value';
            }
        }

        if (\array_key_exists('convert_to_format', $data)) {
            $allowed = ['mp3', 'flac', 'aac', 'ogg', 'wav'];
            if (!\is_string($data['convert_to_format']) || !\in_array(mb_strtolower($data['convert_to_format']), $allowed, true)) {
                $errors['convert_to_format'] = 'invalid value';
            }
        }

        if (\array_key_exists('max_bitrate', $data)) {
            if (!\is_int($data['max_bitrate'])) {
                $errors['max_bitrate'] = 'must be an integer';
            } elseif ($data['max_bitrate'] < 32 || $data['max_bitrate'] > 5000) {
                $errors['max_bitrate'] = 'out of range (32-5000)';
            }
        }

        if (\array_key_exists('compression_level', $data)) {
            if (!\is_int($data['compression_level'])) {
                $errors['compression_level'] = 'must be an integer';
            } elseif ($data['compression_level'] < 0 || $data['compression_level'] > 12) {
                $errors['compression_level'] = 'out of range (0-12)';
            }
        }

        return $errors;
    }
}
