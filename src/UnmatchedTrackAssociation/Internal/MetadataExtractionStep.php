<?php

declare(strict_types=1);

namespace App\UnmatchedTrackAssociation\Internal;

use App\Analyzer\Id3AudioQualityAnalyzer;
use App\Entity\UnmatchedTrack;
use App\StringSimilarity;
use Exception;
use Psr\Log\LoggerInterface;

class MetadataExtractionStep extends AbstractAssociationStep
{
    public function __construct(
        private Id3AudioQualityAnalyzer $audioAnalyzer,
        private StringSimilarity $stringSimilarity
    ) {
    }

    public static function getPriority(): int
    {
        return 110; // Highest priority - extract metadata from audio file first
    }

    public function getType(): string
    {
        return 'metadata_extraction';
    }

    public function process(UnmatchedTrack $unmatchedTrack, array $context, LoggerInterface $logger): array
    {
        // Note: $context parameter is required by interface but not used in this step

        $filePath = $unmatchedTrack->getFilePath();
        $metadata = $this->initializeBasicMetadata($unmatchedTrack, $filePath);

        if (!$filePath || !$this->fileExists($filePath)) {
            return $this->buildMetadataResponse($metadata);
        }

        $metadata = $this->extractAudioMetadata($filePath, $metadata);

        return $this->buildMetadataResponse($metadata);
    }

    /**
     * Initialize basic metadata from unmatched track.
     */
    private function initializeBasicMetadata(UnmatchedTrack $unmatchedTrack, ?string $filePath): array
    {
        return [
            'title' => $unmatchedTrack->getTitle(),
            'artist' => $unmatchedTrack->getArtist(),
            'album' => $unmatchedTrack->getAlbum(),
            'file_path' => $filePath,
        ];
    }

    /**
     * Extract metadata from audio file.
     */
    private function extractAudioMetadata(string $filePath, array $metadata): array
    {
        try {
            $audioAnalysis = $this->audioAnalyzer->analyzeAudioFile($filePath);

            if (isset($audioAnalysis['error']) && $audioAnalysis['error']) {
                return $metadata;
            }

            $audioMetadata = $audioAnalysis['metadata'] ?? [];
            $metadata = $this->mergeAudioMetadata($metadata, $audioMetadata);
            $metadata['audio_quality'] = $this->buildAudioQualityInfo($audioAnalysis);
        } catch (Exception $e) {
        }

        return $metadata;
    }

    /**
     * Merge audio metadata with basic metadata.
     */
    private function mergeAudioMetadata(array $metadata, array $audioMetadata): array
    {
        // Use audio file metadata to enhance or override basic metadata
        if (!empty($audioMetadata['title'])) {
            $metadata['title'] = $this->stringSimilarity->normalizeApostrophes($audioMetadata['title']);
        }

        // Find artist from multiple possible fields: artist, album_artist, or performer
        $artist = $this->findArtistFromMetadata($audioMetadata);
        if ($artist) {
            $metadata['artist'] = $this->stringSimilarity->normalizeApostrophes($artist);
        }

        if (!empty($audioMetadata['album'])) {
            $metadata['album'] = $this->stringSimilarity->normalizeApostrophes($audioMetadata['album']);
        }

        // Add additional metadata from audio file
        $metadata['track_number'] = $audioMetadata['track_number'] ?? null;
        $metadata['year'] = $audioMetadata['year'] ?? null;
        $metadata['genre'] = $audioMetadata['genre'] ?? null;
        $metadata['composer'] = $audioMetadata['composer'] ?? null;
        $metadata['album_artist'] = $audioMetadata['album_artist'] ?? null;
        $metadata['performer'] = $audioMetadata['performer'] ?? null;
        $metadata['disc_number'] = $audioMetadata['disc_number'] ?? null;
        $metadata['total_tracks'] = $audioMetadata['total_tracks'] ?? null;
        $metadata['total_discs'] = $audioMetadata['total_discs'] ?? null;
        $metadata['comment'] = $audioMetadata['comment'] ?? null;

        return $metadata;
    }

    /**
     * Build audio quality information.
     */
    private function buildAudioQualityInfo(array $audioAnalysis): array
    {
        return [
            'format' => $audioAnalysis['format'] ?? null,
            'channels' => $audioAnalysis['channels'] ?? null,
            'bitrate' => $audioAnalysis['bitrate'] ?? null,
            'sample_rate' => $audioAnalysis['sample_rate'] ?? null,
            'bits_per_sample' => $audioAnalysis['bits_per_sample'] ?? null,
            'duration' => $audioAnalysis['duration'] ?? null,
            'quality_string' => $audioAnalysis['quality_string'] ?? null,
            'quality_level' => $this->audioAnalyzer->determineQualityLevel($audioAnalysis),
        ];
    }

    /**
     * Build the final metadata response.
     */
    private function buildMetadataResponse(array $metadata): array
    {
        return [
            'metadata' => $metadata,
            // Also return individual metadata fields for easier access by other steps
            'artist' => $metadata['artist'] ?? null,
            'album' => $metadata['album'] ?? null,
            'title' => $metadata['title'] ?? null,
            'track_number' => $metadata['track_number'] ?? null,
        ];
    }

    /**
     * Find artist from multiple possible metadata fields
     * Priority: artist > album_artist > performer.
     */
    private function findArtistFromMetadata(array $audioMetadata): ?string
    {
        // Check artist first (highest priority)
        if (!empty($audioMetadata['artist'])) {
            return $audioMetadata['artist'];
        }

        // Check album_artist second
        if (!empty($audioMetadata['album_artist'])) {
            return $audioMetadata['album_artist'];
        }

        // Check performer third
        if (!empty($audioMetadata['performer'])) {
            return $audioMetadata['performer'];
        }

        return null;
    }

    /**
     * Check if file exists (can be overridden in tests).
     */
    protected function fileExists(string $filePath): bool
    {
        return file_exists($filePath);
    }
}
