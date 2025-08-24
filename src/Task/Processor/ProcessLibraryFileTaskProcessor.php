<?php

declare(strict_types=1);

namespace App\Task\Processor;

use App\Analyzer\Id3AudioQualityAnalyzer;
use App\Client\MusicBrainzApiClient;
use App\Entity\Library;
use App\Entity\Task;
use App\Entity\TrackFile;
use App\Entity\UnmatchedTrack;
use App\Repository\ArtistRepository;
use App\Repository\LibraryRepository;
use App\Repository\TrackFileRepository;
use App\Repository\TrackRepository;
use App\Repository\UnmatchedTrackRepository;
use App\StringSimilarity;
use App\Task\TaskFactory;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Throwable;

#[AutoconfigureTag('app.task_processor')]
class ProcessLibraryFileTaskProcessor implements TaskProcessorInterface
{
    public function __construct(
        private LibraryRepository $libraryRepository,
        private UnmatchedTrackRepository $unmatchedTrackRepository,
        private TrackRepository $trackRepository,
        private TrackFileRepository $trackFileRepository,
        private ArtistRepository $artistRepository,
        private Id3AudioQualityAnalyzer $audioAnalyzer,
        private MusicBrainzApiClient $musicBrainzApiClient,
        private TaskFactory $taskService,
        private LoggerInterface $logger,
        private StringSimilarity $stringSimilarityService
    ) {
    }

    public function process(Task $task): TaskProcessorResult
    {
        try {
            $metadata = $task->getMetadata() ?? [];
            $filePath = $metadata['file_path'] ?? null;
            $libraryId = $task->getEntityId();
            $dryRun = $metadata['dry_run'] ?? false;

            // Get library
            $library = $this->libraryRepository->find($libraryId);
            if (!$library) {
                $this->logger->error("Library not found: {$libraryId}");

                return TaskProcessorResult::failure('Library not found');
            }

            if (!$filePath || !$libraryId) {
                return TaskProcessorResult::failure('Missing file path or library ID');
            }

            // Extract metadata and analyze audio
            $analysis = $this->audioAnalyzer->analyzeAudioFile($filePath);
            $metadata = $this->extractMetadata($analysis['metadata']);

            // Check for existing TrackFile with different path
            $existingFiles = $this->trackFileRepository->findBy(['filePath' => $filePath]);
            foreach ($existingFiles as $existingFile) {
                $track = $existingFile->getTrack();
                if ($track && $track->getAlbum() && $track->getAlbum()->getArtist()) {
                    if ($existingFile->getFilePath() !== $filePath) {
                        $old = $existingFile->getFilePath();
                        if (!$dryRun) {
                            $existingFile->setFilePath($filePath);
                            $this->trackFileRepository->save($existingFile, true);
                            $this->logger->info('Chemin mis à jour pour: ' . basename($filePath));
                        } else {
                            $this->logger->info('[DRY RUN] Chemin mis à jour pour: ' . basename($filePath));
                        }

                        return TaskProcessorResult::success(
                            \sprintf('Chemin mis à jour pour: %s', basename($filePath)),
                            [
                                'old_filepath' => $old,
                                'new_filePath' => $filePath,
                            ]
                        );
                    }
                }
            }

            // Check if already in unmatched tracks
            $existingUnmatched = $this->unmatchedTrackRepository->findByFilePath($filePath);
            if ($existingUnmatched) {
                $this->updateExistingUnmatchedTrack($existingUnmatched, $metadata, $analysis, $library, $dryRun);

                return TaskProcessorResult::success('updateExistingUnmatchedTrack');
            }

            $this->createUnmatchedTrack($filePath, $metadata, $analysis, $library, $dryRun);

            return TaskProcessorResult::success('createUnmatchedTrack');
        } catch (Exception $e) {
            $this->logger->error('Error processing library file', [
                'file_path' => $task->getMetadata()['file_path'] ?? 'unknown',
                'library_id' => $task->getEntityId(),
                'error' => $e->getMessage(),
            ]);

            return TaskProcessorResult::failure($e->getMessage());
        }
    }

    private function createUnmatchedTrack(
        string $filePath,
        array $metadata,
        array $analysis,
        Library $library,
        bool $dryRun
    ): void {
        if (!$dryRun) {
            // Defensive check in case of race conditions: ensure not already existing
            $existing = $this->unmatchedTrackRepository->findByFilePath($filePath);
            if ($existing) {
                $this->updateExistingUnmatchedTrack($existing, $metadata, $analysis, $library, $dryRun);
                $this->logger->info('UnmatchedTrack already exists for path, updated instead: ' . basename($filePath));

                return;
            }

            $unmatchedTrack = new UnmatchedTrack();
            $unmatchedTrack->setFileName(basename($filePath));
            $unmatchedTrack->setFilePath($filePath);
            $unmatchedTrack->setLibrary($library);
            $unmatchedTrack->setArtist($this->stringSimilarityService->normalizeApostrophes($metadata['artist'] ?? ''));
            $unmatchedTrack->setAlbum($this->stringSimilarityService->normalizeApostrophes($metadata['album'] ?? ''));
            $unmatchedTrack->setTitle($this->stringSimilarityService->normalizeApostrophes($metadata['title'] ?? ''));
            $unmatchedTrack->setTrackNumber($metadata['track_number'] ?? null);
            $unmatchedTrack->setYear($metadata['year'] ?? null);
            $unmatchedTrack->setDuration((int) $analysis['duration'] ?? 0);

            $fileSize = filesize($filePath);
            if (false !== $fileSize) {
                $unmatchedTrack->setFileSize($fileSize);
            }

            // Discover lyrics filepath if available
            $unmatchedTrack->discoverLyricsFilepath();

            try {
                $this->unmatchedTrackRepository->save($unmatchedTrack, true);
            } catch (Throwable $e) {
                // Handle potential unique constraint violation due to concurrent insert
                $this->logger->warning('Failed to create UnmatchedTrack (possible duplicate), attempting update: ' . $e->getMessage());
                $existing = $this->unmatchedTrackRepository->findByFilePath($filePath);
                if ($existing) {
                    $this->updateExistingUnmatchedTrack($existing, $metadata, $analysis, $library, $dryRun);
                } else {
                    throw $e;
                }
            }

            // Search for artist on MusicBrainz
            $this->searchAndDispatchArtist($metadata['artist'] ?? '');

            $this->logger->info('Nouvelle piste non associée créée: ' . basename($filePath));
        } else {
            $this->logger->info('[DRY RUN] Nouvelle piste non associée créée: ' . basename($filePath));
        }
    }

    private function updateExistingUnmatchedTrack(
        UnmatchedTrack $existingUnmatched,
        array $metadata,
        array $analysis,
        Library $library,
        bool $dryRun
    ): void {
        if (!$dryRun) {
            $existingUnmatched->setFileName(basename($existingUnmatched->getFilePath()));
            $existingUnmatched->setLibrary($library);
            $existingUnmatched->setArtist($this->stringSimilarityService->normalizeApostrophes($metadata['artist'] ?? ''));
            $existingUnmatched->setAlbum($this->stringSimilarityService->normalizeApostrophes($metadata['album'] ?? ''));
            $existingUnmatched->setTitle($this->stringSimilarityService->normalizeApostrophes($metadata['title'] ?? ''));
            $existingUnmatched->setTrackNumber($metadata['track_number'] ?? null);
            $existingUnmatched->setYear($metadata['year'] ?? null);
            $existingUnmatched->setDuration((int) $analysis['duration'] ?? 0);

            $fileSize = filesize($existingUnmatched->getFilePath());
            if (false !== $fileSize) {
                $existingUnmatched->setFileSize($fileSize);
            }

            // Discover lyrics filepath if available
            $existingUnmatched->discoverLyricsFilepath();

            $this->unmatchedTrackRepository->save($existingUnmatched, true);

            // Search for artist on MusicBrainz if not already matched
            $this->searchAndDispatchArtist($metadata['artist'] ?? '');
        }

        $this->logger->info('Informations mises à jour pour la piste non associée: ' . basename($existingUnmatched->getFilePath()));
    }

    private function extractMetadata($rawMetadata): array
    {
        // Clean and normalize metadata
        if (isset($rawMetadata['artist'])) {
            $rawMetadata['artist'] = $this->stringSimilarityService->normalizeApostrophes(mb_trim($rawMetadata['artist']));
        }
        if (isset($rawMetadata['album'])) {
            $rawMetadata['album'] = $this->stringSimilarityService->normalizeApostrophes(mb_trim($rawMetadata['album']));
        }
        if (isset($rawMetadata['title'])) {
            $rawMetadata['title'] = $this->stringSimilarityService->normalizeApostrophes(mb_trim($rawMetadata['title']));
        }

        return $rawMetadata;
    }

    public function getSupportedTaskTypes(): array
    {
        return [Task::TYPE_PROCESS_LIBRARY_FILE];
    }

    public function supports(Task $task): bool
    {
        return Task::TYPE_PROCESS_LIBRARY_FILE === $task->getType();
    }

    private function searchAndDispatchArtist(string $artistName): void
    {
        if (empty($artistName)) {
            return;
        }

        // Check if artist already exists
        $existingArtist = $this->artistRepository->findOneBy([
            'name' => $artistName,
        ]);

        if ($existingArtist) {
            return; // Artist already exists
        }

        try {
            // Search MusicBrainz for exact match
            $searchResults = $this->musicBrainzApiClient->searchArtist($artistName);

            if (empty($searchResults)) {
                return;
            }

            // Look for exact name match (case insensitive)
            $normalizedName = mb_strtolower(mb_trim($artistName));
            foreach ($searchResults as $result) {
                if (isset($result['name']) && mb_strtolower(mb_trim($result['name'])) === $normalizedName) {
                    $mbid = $result['id'] ?? null;
                    if ($mbid) {
                        // Dispatch AddArtistMessage for exact match
                        $this->taskService->createTask(
                            Task::TYPE_SYNC_ARTIST,
                            $mbid,
                            null,
                            $result['name'],
                            [],
                            5
                        );
                        $this->logger->info("Dispatched SyncArtist task for exact match: {$artistName} (MBID: {$mbid})");

                        break;
                    }
                }
            }
        } catch (Exception $e) {
            $this->logger->error("Error searching MusicBrainz for artist {$artistName}: " . $e->getMessage());
        }
    }
}
