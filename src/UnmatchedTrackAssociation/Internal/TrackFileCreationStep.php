<?php

declare(strict_types=1);

namespace App\UnmatchedTrackAssociation\Internal;

use App\Entity\Track;
use App\Entity\TrackFile;
use App\Entity\UnmatchedTrack;
use App\Repository\TrackFileRepository;
use App\Repository\TrackRepository;
use Psr\Log\LoggerInterface;

class TrackFileCreationStep extends AbstractAssociationStep
{
    public function __construct(
        private TrackFileRepository $trackFileRepository,
        private TrackRepository $trackRepository
    ) {
    }

    public static function getPriority(): int
    {
        return 60; // Run after track finding
    }

    public function getType(): string
    {
        return 'track_file_creation';
    }

    public function process(UnmatchedTrack $unmatchedTrack, array $context, LoggerInterface $logger): array
    {
        /** @var Track $track */
        $track = $context['track'] ?? null;

        if (!$track) {
            return ['errors' => ['No track available for TrackFile creation']];
        }

        $filePath = $unmatchedTrack->getFilePath();
        if (empty($filePath)) {
            return ['errors' => ['No file path available']];
        }

        // Check if TrackFile already exists
        $existingTrackFile = $this->trackFileRepository->findOneBy(['filePath' => $filePath]);
        if ($existingTrackFile) {
            // Ensure the track file is associated with the track and update track status
            if ($existingTrackFile->getTrack() !== $track) {
                $existingTrackFile->setTrack($track);
                $this->trackFileRepository->save($existingTrackFile, true);
            }

            // Update lyrics path if available from unmatched track
            if ($unmatchedTrack->getLyricsFilepath() && !$existingTrackFile->getLyricsPath()) {
                $existingTrackFile->setLyricsPath($unmatchedTrack->getLyricsFilepath());
                $this->trackFileRepository->save($existingTrackFile, true);
            }

            $track->addFile($existingTrackFile);
            $track->setHasFile(true);
            $track->setDownloaded(true);

            // Save track changes
            $this->trackRepository->save($track, true);

            return [
                'track_file' => $existingTrackFile,
                'metadata' => ['track_file_exists' => true],
            ];
        }

        // Create the track file
        $trackFile = new TrackFile();
        $trackFile->setFilePath($unmatchedTrack->getFilePath());
        $trackFile->setFileSize($unmatchedTrack->getFileSize());
        $trackFile->setFormat($unmatchedTrack->getFormat());
        $trackFile->setQuality($unmatchedTrack->getQuality());
        $trackFile->setDuration($unmatchedTrack->getDuration());
        $trackFile->setTrack($track);

        $this->trackFileRepository->save($trackFile, true);

        $this->trackRepository->save($track, true);

        return [
            'track_file' => $trackFile,
            'metadata' => ['track_file_created' => true],
        ];
    }
}
