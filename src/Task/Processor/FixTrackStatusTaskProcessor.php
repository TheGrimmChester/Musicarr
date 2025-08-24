<?php

declare(strict_types=1);

namespace App\Task\Processor;

use App\Entity\Task;
use App\Entity\Track;
use App\Entity\TrackFile;
use App\Repository\TrackRepository;
use App\Repository\UnmatchedTrackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.task_processor')]
class FixTrackStatusTaskProcessor implements TaskProcessorInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
        private UnmatchedTrackRepository $unmatchedTrackRepository,
        private TrackRepository $trackRepository,
    ) {
    }

    public function process(Task $task): TaskProcessorResult
    {
        try {
            // Fix all tracks that have isMatched unmatched tracks but no files
            $tracks = $this->trackRepository->findAllIterable();
            $fixedCount = 0;

            foreach ($tracks as $track) {
                if ($this->fixTrack($track)) {
                    ++$fixedCount;
                }
            }

            return TaskProcessorResult::success(
                'Successfully fixed statuses',
                [
                    'fixed_count' => $fixedCount,
                ]
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to fix status', [
                'error' => $e->getMessage(),
            ]);

            return TaskProcessorResult::failure($e->getMessage());
        }
    }

    public function getSupportedTaskTypes(): array
    {
        return [Task::TYPE_FIX_TRACK_STATUSES];
    }

    public function supports(Task $task): bool
    {
        return Task::TYPE_FIX_TRACK_STATUSES === $task->getType();
    }

    private function fixTrack(Track $track): bool
    {
        $trackTitle = $track->getTitle();
        $artistName = $track->getArtistName();

        // Check if there are unmatched tracks for this track (including matched ones that might not have files)
        $unmatchedTracks = $this->unmatchedTrackRepository->findByArtistAndTitle($artistName, $trackTitle);

        // Also check matched tracks that might not have files associated
        $matchedTracks = $this->unmatchedTrackRepository->createQueryBuilder('ut')
            ->andWhere('ut.artist LIKE :artist')
            ->andWhere('ut.title LIKE :title')
            ->andWhere('ut.isMatched = true')
            ->setParameter('artist', '%' . $artistName . '%')
            ->setParameter('title', '%' . $trackTitle . '%')
            ->getQuery()
            ->getResult();

        $unmatchedTracks = array_merge($unmatchedTracks, $matchedTracks);

        if (empty($unmatchedTracks)) {
            return false;
        }

        foreach ($unmatchedTracks as $unmatchedTrack) {
            if ($unmatchedTrack->isMatched()) {
                // Check if the file is actually associated with the track
                $fileExists = false;
                foreach ($track->getFiles() as $file) {
                    if ($file->getFilePath() === $unmatchedTrack->getFilePath()) {
                        $fileExists = true;

                        break;
                    }
                }

                if (!$fileExists) {
                    // Create the TrackFile
                    $trackFile = new TrackFile();
                    $trackFile->setFilePath($unmatchedTrack->getFilePath());
                    $trackFile->setFileSize($unmatchedTrack->getFileSize());
                    $trackFile->setFormat($unmatchedTrack->getExtension());
                    $trackFile->setDuration($unmatchedTrack->getDuration() ?? 0);
                    // Set the first file as preferred (this will be the best quality file)
                    $firstFile = $track->getFiles()->first();
                    if ($firstFile) {
                        $track->setHasFile(true);
                        $track->setDownloaded(true);

                        // No need to set preferred status anymore - it's determined by quality
                        $this->logger->info('Track {trackId} status updated', [
                            'trackId' => $track->getId(),
                            'hasFile' => true,
                            'downloaded' => true,
                        ]);
                    }
                    $trackFile->setTrack($track);

                    // Set lyrics path if available from unmatched track
                    if ($unmatchedTrack->getLyricsFilepath()) {
                        $trackFile->setLyricsPath($unmatchedTrack->getLyricsFilepath());
                    }

                    $track->addFile($trackFile);
                    $track->setHasFile(true);
                    $track->setDownloaded(true);

                    $this->entityManager->persist($trackFile);
                    $this->entityManager->flush();
                }
            }
        }

        return true;
    }
}
