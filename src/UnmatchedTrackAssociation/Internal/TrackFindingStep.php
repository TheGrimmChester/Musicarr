<?php

declare(strict_types=1);

namespace App\UnmatchedTrackAssociation\Internal;

use App\Analyzer\FilePathAnalyzer;
use App\Configuration\Config\ConfigurationFactory;
use App\Entity\UnmatchedTrack;
use App\Repository\TrackRepository;
use App\TrackMatcher\TrackMatcher;
use Psr\Log\LoggerInterface;

class TrackFindingStep extends AbstractAssociationStep
{
    public function __construct(
        private TrackRepository $trackRepository,
        private TrackMatcher $trackMatcher,
        private FilePathAnalyzer $filePathAnalyzer,
        private ConfigurationFactory $configurationFactory
    ) {
    }

    public static function getPriority(): int
    {
        return 70; // Run after album finding
    }

    public function getType(): string
    {
        return 'track_finding';
    }

    /**
     * Normalize apostrophes by replacing straight quotes with curly quotes.
     */
    public function normalizeApostrophes(?string $str): ?string
    {
        if (null === $str) {
            return null;
        }

        // Replace straight apostrophes with curly quotes
        return str_replace("'", "'", $str);
    }

    public function process(UnmatchedTrack $unmatchedTrack, array $context, LoggerInterface $logger): array
    {
        $artist = $context['artist'] ?? null;
        $album = $context['album'] ?? null;

        if (!$artist) {
            return ['errors' => ['No artist available for track search']];
        }

        // Use extracted metadata if available, otherwise fall back to unmatched track metadata
        $trackTitle = $context['title'] ?? $unmatchedTrack->getTitle();

        if (empty($trackTitle)) {
            return ['errors' => ['No track title available']];
        }

        $trackTitle = $this->normalizeApostrophes($trackTitle);
        // Try to find track using flexible matching
        $track = null;

        // Handle case where artist might be a string (from extracted metadata) or an Artist entity
        $artistName = \is_string($artist) ? $artist : $artist->getName();

        // Handle case where album might be a string (from extracted metadata) or an Album entity
        $albumTitle = \is_string($album) ? $album : $album->getTitle();

        if (!$album) {
            // Try flexible matching without album
            $track = $this->trackRepository->findByArtistAndTitleFlexible(
                $artistName,
                $trackTitle
            );
        } else {
            // Try exact match first
            $track = $this->trackRepository->findByArtistAlbumAndTitle(
                $artistName,
                $albumTitle,
                $trackTitle
            );

            if (!$track) {
                // Try flexible matching
                $track = $this->trackRepository->findByArtistAndTitleFlexible(
                    $artistName,
                    $trackTitle
                );
            }
        }

        if (!$track) {
            return ['errors' => ["Track not found: {$trackTitle} for artist: {$artistName}"]];
        }

        // Calculate match score for the found track
        $pathInfo = $this->filePathAnalyzer->extractPathInformation($unmatchedTrack->getFilePath() ?? '');
        $score = $this->trackMatcher->calculateMatchScore($track, $unmatchedTrack, $pathInfo);
        $matchReason = $this->trackMatcher->getMatchReason($track, $unmatchedTrack, $pathInfo);

        // Check if score meets minimum threshold
        $associationConfig = $this->configurationFactory->getDefaultConfiguration('association.');
        $minimumScore = $associationConfig['min_score'] ?? 85.0;

        if ($score < $minimumScore) {
            return [
                'errors' => [
                    "Track found but score too low: {$score} < {$minimumScore}",
                    "Match reason: {$matchReason}",
                ],
            ];
        }

        return [
            'track' => $track,
            'score' => $score,
            'match_reason' => $matchReason,
        ];
    }
}
