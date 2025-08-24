<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Entity\Track;
use App\Entity\TrackFile;

class TrackDataSerializer
{
    /**
     * Serialize track data for JavaScript consumption.
     */
    public function serializeTrackData(Track $track): array
    {
        $files = $this->serializeTrackFiles($track);

        // Calculate aggregate values from all files
        $totalDuration = 0;
        $totalFileSize = 0;
        $bestQuality = null;
        $bestFormat = null;

        foreach ($track->getFiles() as $file) {
            $totalDuration += $file->getDuration();
            $totalFileSize += $file->getFileSize();

            // Track best quality and format
            if (!$bestQuality || $file->getQualityScore() > ($bestQuality ? $bestQuality->getQualityScore() : 0)) {
                $bestQuality = $file;
            }
            if (!$bestFormat || 'FLAC' === $file->getFormat() || 'ALAC' === $file->getFormat()) {
                $bestFormat = $file;
            }
        }

        $result = [
            'id' => $track->getId(),
            'title' => $track->getTitle(),
            'trackNumber' => $track->getTrackNumber(),
            'hasFile' => $track->isHasFile(),
            'downloaded' => $track->isDownloaded(),
            'duration' => $totalDuration,
            'quality' => $bestQuality ? $bestQuality->getQuality() : null,
            'format' => $bestFormat ? $bestFormat->getFormat() : null,
            'files' => $files,
            'fileCount' => \count($files),
            'totalFileSize' => $totalFileSize,
        ];

        return $result;
    }

    /**
     * Serialize multiple tracks data.
     */
    public function serializeTracksData(array $tracks): array
    {
        $tracksData = [];
        foreach ($tracks as $track) {
            $tracksData[] = $this->serializeTrackData($track);
        }

        return $tracksData;
    }

    /**
     * Serialize track files data.
     */
    public function serializeTrackFiles(Track $track): array
    {
        $files = [];

        foreach ($track->getFiles() as $file) {
            $files[] = $this->serializeTrackFile($file);
        }

        return $files;
    }

    /**
     * Serialize single track file data.
     */
    public function serializeTrackFile(TrackFile $file): array
    {
        $result = [
            'id' => $file->getId(),
            'trackFileId' => $file->getId(), // Unique file ID
            'filePath' => $file->getFilePath(),
            'relativePath' => $file->getRelativePath(),
            'fileSize' => $file->getFileSize(),
            'quality' => $file->getQuality(),
            'format' => $file->getFormat(),
            'duration' => $file->getDuration(),
            'addedAt' => $file->getAddedAt() ? $file->getAddedAt()->format('Y-m-d H:i:s') : null,
            'lyricsPath' => $file->getLyricsPath(),
        ];

        return $result;
    }

    /**
     * Serialize track summary data (minimal information).
     */
    public function serializeTrackSummary(Track $track): array
    {
        // Calculate aggregate values from all files
        $totalDuration = 0;
        $bestQuality = null;
        $bestFormat = null;

        foreach ($track->getFiles() as $file) {
            $totalDuration += $file->getDuration();

            // Track best quality and format
            if (!$bestQuality || $file->getQualityScore() > ($bestQuality ? $bestQuality->getQualityScore() : 0)) {
                $bestQuality = $file;
            }
            if (!$bestFormat || 'FLAC' === $file->getFormat() || 'ALAC' === $file->getFormat()) {
                $bestFormat = $file;
            }
        }

        return [
            'id' => $track->getId(),
            'title' => $track->getTitle(),
            'trackNumber' => $track->getTrackNumber(),
            'hasFile' => $track->isHasFile(),
            'downloaded' => $track->isDownloaded(),
            'duration' => $totalDuration,
            'quality' => $bestQuality ? $bestQuality->getQuality() : null,
            'format' => $bestFormat ? $bestFormat->getFormat() : null,
        ];
    }

    /**
     * Serialize tracks summary data.
     */
    public function serializeTracksSummary(array $tracks): array
    {
        $tracksSummary = [];
        foreach ($tracks as $track) {
            $tracksSummary[] = $this->serializeTrackSummary($track);
        }

        return $tracksSummary;
    }

    /**
     * Get track statistics.
     */
    public function getTrackStats(array $tracks): array
    {
        $stats = [
            'total' => \count($tracks),
            'withFiles' => 0,
            'downloaded' => 0,
            'totalDuration' => 0,
            'qualityDistribution' => [],
            'formatDistribution' => [],
        ];

        foreach ($tracks as $track) {
            if ($track->isHasFile()) {
                ++$stats['withFiles'];
            }
            if ($track->isDownloaded()) {
                ++$stats['downloaded'];
            }

            // Calculate statistics from the best quality file
            $bestQualityFile = null;
            $bestQualityScore = 0;

            foreach ($track->getFiles() as $file) {
                $qualityScore = $file->getQualityScore();
                if ($qualityScore > $bestQualityScore) {
                    $bestQualityScore = $qualityScore;
                    $bestQualityFile = $file;
                }
            }

            if ($bestQualityFile) {
                $stats['totalDuration'] += $bestQualityFile->getDuration() ?? 0;

                // Get quality and format from the best quality file
                $quality = $bestQualityFile->getQuality();
                if ($quality) {
                    $stats['qualityDistribution'][$quality] = ($stats['qualityDistribution'][$quality] ?? 0) + 1;
                }

                $format = $bestQualityFile->getFormat();
                if ($format) {
                    $stats['formatDistribution'][$format] = ($stats['formatDistribution'][$format] ?? 0) + 1;
                }
            }
        }

        return $stats;
    }
}
