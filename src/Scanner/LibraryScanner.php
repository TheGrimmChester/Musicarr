<?php

declare(strict_types=1);

namespace App\Scanner;

use App\Entity\Library;
use App\Entity\Task;
use App\Repository\TrackFileRepository;
use App\Repository\UnmatchedTrackRepository;
use App\Task\TaskFactory;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class LibraryScanner
{
    private array $unmatchedArtists;

    public function __construct(
        private TaskFactory $taskService,
        private LoggerInterface $logger,
        private TrackFileRepository $trackFileRepository,
        private UnmatchedTrackRepository $unmatchedTrackRepository
    ) {
    }

    /**
     * Scanne une bibliothèque et retourne les résultats.
     */
    public function scanLibrary(Library $library, bool $dryRun = false, bool $forceAnalysis = false): array
    {
        $path = $library->getPath();
        if (null === $path) {
            $this->logger->error('Library path is null');

            return ['unmatched' => [], 'matched' => 0, 'path_updates' => 0, 'removed_files' => 0, 'updated_files' => 0, 'track_files_created' => 0, 'analysis_sent' => 0, 'track_status_fixes' => 0];
        }

        $removedFiles = 0;

        if (!is_dir($path)) {
            $this->logger->error("Le chemin {$path} n'existe pas");

            return ['unmatched' => [], 'matched' => 0, 'path_updates' => 0, 'removed_files' => 0, 'updated_files' => 0, 'track_files_created' => 0, 'analysis_sent' => 0, 'track_status_fixes' => 0];
        }

        $audioExtensions = ['mp3', 'flac', 'm4a', 'wav', 'ogg', 'aac'];
        $files = $this->findAudioFiles($path, $audioExtensions);

        // Nettoyer les TrackFile qui n'existent plus
        $removedFiles = $this->cleanMissingTrackFiles($library, $dryRun);

        // Nettoyer les unmatched tracks dont les fichiers n'existent plus
        $removedUnmatchedFiles = $this->cleanMissingUnmatchedTracks($library, $dryRun);
        $removedFiles += $removedUnmatchedFiles;

        $libraryId = $library->getId();
        if (null === $libraryId) {
            $this->logger->error('Library ID is null');

            return ['files_dispatched' => 0, 'sync_count' => 0, 'fix_count' => 0, 'track_status_fixes' => 0, 'auto_associations' => 0, 'album_updates' => 0];
        }

        $this->logger->info("Scanning library: {$library->getName()} ({$path})");
        $this->logger->info('Found ' . \count($files) . ' audio files');

        // Create tasks for each file to be processed asynchronously
        $filesDispatched = 0;
        foreach ($files as $filePath) {
            $this->taskService->createTask(
                Task::TYPE_PROCESS_LIBRARY_FILE,
                null,
                $libraryId,
                basename($filePath),
                [
                    'file_path' => $filePath,
                    'dry_run' => $dryRun,
                    'force_analysis' => $forceAnalysis,
                ],
                4 // High priority for file processing
            );
            ++$filesDispatched;
        }

        $this->logger->info("Created {$filesDispatched} file processing tasks");

        // Create task to analyze existing tracks asynchronously
        $this->taskService->createTask(
            Task::TYPE_ANALYZE_EXISTING_TRACKS,
            null,
            $libraryId,
            $library->getName(),
            ['dry_run' => $dryRun, 'force_analysis' => $forceAnalysis],
            3
        );
        $this->logger->info("Created analyze existing tracks task for library {$libraryId}");

        // Create tasks for all post-processing operations
        $this->taskService->createTask(
            Task::TYPE_SYNC_TRACK_STATUSES,
            null,
            $libraryId,
            $library->getName(),
            ['dry_run' => $dryRun],
            2
        );
        $this->logger->info("Created sync track statuses task for library {$libraryId}");

        $this->taskService->createTask(
            Task::TYPE_FIX_MATCHED_TRACKS_WITHOUT_FILES,
            null,
            $libraryId,
            $library->getName(),
            ['dry_run' => $dryRun],
            2
        );
        $this->logger->info("Created fix matched tracks without files task for library {$libraryId}");

        $this->taskService->createTask(
            Task::TYPE_AUTO_ASSOCIATE_TRACKS,
            null,
            $libraryId,
            $library->getName(),
            ['dry_run' => $dryRun],
            1
        );
        $this->logger->info("Created auto associate tracks task for library {$libraryId}");

        $this->taskService->createTask(
            Task::TYPE_UPDATE_ALBUM_STATUSES,
            null,
            $libraryId,
            $library->getName(),
            ['dry_run' => $dryRun],
            1
        );
        $this->logger->info("Created update album statuses task for library {$libraryId}");

        return [
            'files_dispatched' => $filesDispatched,
            'removed_files' => $removedFiles,
            'tasks_created' => 6, // AnalyzeExistingTracks + 5 post-processing tasks
        ];
    }

    /**
     * Trouve tous les fichiers audio dans un répertoire.
     */
    private function findAudioFiles(string $directory, array $extensions): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $extension = mb_strtolower(pathinfo($file->getPathname(), \PATHINFO_EXTENSION));
                if (\in_array($extension, $extensions, true)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    /**
     * Nettoie les TrackFile qui n'existent plus.
     */
    private function cleanMissingTrackFiles(Library $library, bool $dryRun): int
    {
        $removedCount = 0;
        $missingTracksCount = 0;

        // Récupérer tous les TrackFile de cette bibliothèque
        $trackFiles = $this->trackFileRepository->createQueryBuilder('tf')->getQuery()->getResult();

        $this->logger->info("Vérification de {$library->getName()}: " . \count($trackFiles) . ' fichiers enregistrés');

        // Grouper les fichiers par track pour traiter chaque track une seule fois
        $tracksToProcess = [];
        foreach ($trackFiles as $trackFile) {
            $trackId = $trackFile->getTrack()->getId();
            if (!isset($tracksToProcess[$trackId])) {
                $tracksToProcess[$trackId] = [
                    'track' => $trackFile->getTrack(),
                    'files' => [],
                ];
            }
            $tracksToProcess[$trackId]['files'][] = $trackFile;
        }

        // Traiter chaque track
        foreach ($tracksToProcess as $trackId => $trackData) {
            $track = $trackData['track'];
            $files = $trackData['files'];
            $filesToRemove = [];

            // Identifier les fichiers manquants pour cette track
            foreach ($files as $trackFile) {
                $filePath = $trackFile->getFilePath();

                if (!file_exists($filePath)) {
                    $filesToRemove[] = $trackFile;
                }
            }

            // Si des fichiers sont manquants, les supprimer
            if (!empty($filesToRemove)) {
                if (!$dryRun) {
                    foreach ($filesToRemove as $trackFile) {
                        $fileName = basename($trackFile->getFilePath());
                        $this->trackFileRepository->remove($trackFile);
                        $this->logger->info("Fichier supprimé: {$fileName} (Track: {$track->getTitle()})");
                    }

                    // Vérifier si la track a encore des fichiers après suppression
                    $remainingFiles = $track->getFiles();
                    if (0 === $remainingFiles->count()) {
                        // Tous les fichiers ont été supprimés, marquer la track comme manquante
                        $track->setHasFile(false);
                        $track->setDownloaded(false);
                        ++$missingTracksCount;
                        $this->logger->info("Track marquée comme manquante: {$track->getTitle()} (Album: {$track->getAlbum()->getTitle()})");
                    } else {
                        // Il reste des fichiers, vérifier s'ils existent sur le disque et définir un nouveau fichier préféré si nécessaire
                        $hasExistingFiles = false;
                        $newPreferredFile = null;

                        foreach ($remainingFiles as $file) {
                            if (file_exists($file->getFilePath())) {
                                $hasExistingFiles = true;
                                if (!$newPreferredFile) {
                                    $newPreferredFile = $file;
                                }
                            }
                        }

                        // Update track status
                        $track->setHasFile(true);
                        $track->setDownloaded(true);

                        // No need to update preferred file status anymore - it's determined by quality

                        // Log if we have new files available
                        if ($newPreferredFile) {
                            $this->logger->info("New files available for: {$track->getTitle()}");
                        }

                        if (!$hasExistingFiles) {
                            $this->logger->info("Track a des fichiers en DB mais pas sur disque: {$track->getTitle()}");
                        }
                    }
                } else {
                    foreach ($filesToRemove as $trackFile) {
                        $fileName = basename($trackFile->getFilePath());
                        $this->logger->info("[DRY RUN] Fichier supprimé: {$fileName}");
                    }

                    // Vérifier si la track serait marquée comme manquante
                    $remainingFilesCount = $track->getFiles()->count() - \count($filesToRemove);
                    if (0 === $remainingFilesCount) {
                        ++$missingTracksCount;
                        $this->logger->info("[DRY RUN] Track serait marquée comme manquante: {$track->getTitle()}");
                    }
                }
            }
        }

        if ($removedCount > 0) {
            $this->logger->info("{$removedCount} fichiers manquants supprimés de la base de données");
        } else {
            $this->logger->info('Tous les fichiers enregistrés existent encore');
        }

        if ($missingTracksCount > 0) {
            $this->logger->info("{$missingTracksCount} pistes marquées comme manquantes (tous leurs fichiers supprimés)");
        }

        return $removedCount;
    }

    /**
     * Nettoie les unmatched tracks dont les fichiers n'existent plus.
     */
    private function cleanMissingUnmatchedTracks(Library $library, bool $dryRun): int
    {
        $removedCount = 0;

        // Récupérer tous les unmatched tracks de cette bibliothèque
        $libraryId = $library->getId();
        if (null === $libraryId) {
            $this->logger->error('Library has no ID');

            return 0;
        }

        $unmatchedTracks = $this->unmatchedTrackRepository->findUnmatchedByLibrary($libraryId);

        $this->logger->info("Vérification des unmatched tracks de {$library->getName()}: " . \count($unmatchedTracks) . ' pistes non associées');

        $tracksToRemove = [];

        foreach ($unmatchedTracks as $unmatchedTrack) {
            $filePath = $unmatchedTrack->getFilePath();

            if (!file_exists($filePath)) {
                $tracksToRemove[] = $unmatchedTrack;
                $fileName = basename($filePath);
                $this->logger->info("Fichier non existant: {$fileName}");
            }
        }

        if (empty($tracksToRemove)) {
            $this->logger->info('Tous les fichiers des unmatched tracks existent encore');

            return 0;
        }

        $this->logger->info(\count($tracksToRemove) . ' unmatched tracks à supprimer (fichiers manquants)');

        if (!$dryRun) {
            foreach ($tracksToRemove as $unmatchedTrack) {
                $fileName = basename($unmatchedTrack->getFilePath());
                $this->unmatchedTrackRepository->remove($unmatchedTrack);
                $this->logger->info("Unmatched track supprimé: {$fileName}");
            }

            $this->logger->info(\count($tracksToRemove) . ' unmatched tracks supprimés');
        } else {
            foreach ($tracksToRemove as $unmatchedTrack) {
                $fileName = basename($unmatchedTrack->getFilePath());
                $this->logger->info("[DRY RUN] Unmatched track supprimé: {$fileName}");
            }
            $this->logger->info('[DRY RUN] ' . \count($tracksToRemove) . ' unmatched tracks seraient supprimés');
        }

        return \count($tracksToRemove);
    }
}
