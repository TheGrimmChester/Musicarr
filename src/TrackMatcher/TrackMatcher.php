<?php

declare(strict_types=1);

namespace App\TrackMatcher;

use App\Analyzer\FilePathAnalyzer;
use App\Entity\Track;
use App\Entity\UnmatchedTrack;
use App\StringSimilarity;
use App\TrackMatcher\Calculator\ScoreCalculatorChain;

class TrackMatcher
{
    private const MIN_SCORE_THRESHOLD = 0.1;
    private const MAX_SCORE = 100.0;
    private const SIMILARITY_THRESHOLD = 0.8;

    public function __construct(
        private StringSimilarity $stringSimilarity,
        private FilePathAnalyzer $filePathAnalyzer,
        private ScoreCalculatorChain $scoreCalculatorChain
    ) {
    }

    /**
     * Calculate a comprehensive match score based on multiple factors using tagged iterator.
     */
    public function calculateMatchScore(Track $track, UnmatchedTrack $unmatchedTrack, array $pathInfo): float
    {
        $result = $this->scoreCalculatorChain->executeChain($track, $unmatchedTrack, $pathInfo);

        // Ensure score doesn't go below 0, but penalties still affect ranking
        return max(0.0, min(self::MAX_SCORE, $result['score']));
    }

    /**
     * Get match reason for debugging using tagged iterator.
     */
    public function getMatchReason(Track $track, UnmatchedTrack $unmatchedTrack, array $pathInfo): string
    {
        $result = $this->scoreCalculatorChain->executeChain($track, $unmatchedTrack, $pathInfo);

        return empty($result['reasons']) ? 'No significant matches found' : implode(', ', $result['reasons']);
    }

    /**
     * Find best matches for an unmatched track.
     */
    public function findBestMatches(UnmatchedTrack $unmatchedTrack, array $tracks, int $limit = 10): array
    {
        $pathInfo = $this->filePathAnalyzer->extractPathInformation($unmatchedTrack->getFilePath() ?? '');
        $matches = $this->calculateMatchesForTracks($tracks, $unmatchedTrack, $pathInfo);
        $sortedMatches = $this->sortMatchesByScore($matches);

        return \array_slice($sortedMatches, 0, $limit);
    }

    /**
     * Calculate matches for all tracks.
     */
    private function calculateMatchesForTracks(array $tracks, UnmatchedTrack $unmatchedTrack, array $pathInfo): array
    {
        $matches = [];
        foreach ($tracks as $track) {
            $score = $this->calculateMatchScore($track, $unmatchedTrack, $pathInfo);
            if ($score > self::MIN_SCORE_THRESHOLD) {
                $matches[] = [
                    'track' => $track,
                    'score' => $score,
                    'reason' => $this->getMatchReason($track, $unmatchedTrack, $pathInfo),
                ];
            }
        }

        return $matches;
    }

    /**
     * Sort matches by score in descending order.
     */
    private function sortMatchesByScore(array $matches): array
    {
        usort($matches, function ($match1, $match2) {
            return $match2['score'] <=> $match1['score'];
        });

        return $matches;
    }

    /**
     * Check if two tracks are likely the same.
     */
    public function areTracksSimilar(Track $track1, Track $track2, ?float $threshold = null): bool
    {
        $threshold ??= self::SIMILARITY_THRESHOLD;

        if (!$this->validateTracksForComparison($track1, $track2)) {
            return false;
        }

        $similarities = $this->calculateTrackSimilarities($track1, $track2);
        $overallSimilarity = $this->calculateOverallSimilarity($similarities);

        return $overallSimilarity >= $threshold;
    }

    /**
     * Validate that tracks have required data for comparison.
     */
    private function validateTracksForComparison(Track $track1, Track $track2): bool
    {
        $album1 = $track1->getAlbum();
        $album2 = $track2->getAlbum();

        if (!$album1 || !$album2) {
            return false;
        }

        $artist1 = $album1->getArtist();
        $artist2 = $album2->getArtist();

        return $artist1 && $artist2;
    }

    /**
     * Calculate similarities between track components.
     */
    private function calculateTrackSimilarities(Track $track1, Track $track2): array
    {
        $album1 = $track1->getAlbum();
        $album2 = $track2->getAlbum();
        $artist1 = $album1->getArtist();
        $artist2 = $album2->getArtist();

        return [
            'artist' => $this->stringSimilarity->calculateSimilarity(
                $artist1->getName() ?? '',
                $artist2->getName() ?? ''
            ),
            'album' => $this->stringSimilarity->calculateSimilarity(
                $album1->getTitle() ?? '',
                $album2->getTitle() ?? ''
            ),
            'title' => $this->stringSimilarity->calculateSimilarity(
                $track1->getTitle() ?? '',
                $track2->getTitle() ?? ''
            ),
        ];
    }

    /**
     * Calculate overall similarity from individual similarities.
     */
    private function calculateOverallSimilarity(array $similarities): float
    {
        return ($similarities['artist'] + $similarities['album'] + $similarities['title']) / 3;
    }

    /**
     * Calculate match score with custom chain configuration.
     */
    public function calculateMatchScoreWithCustomChain(Track $track, UnmatchedTrack $unmatchedTrack, array $pathInfo, array $calculatorTypes): float
    {
        $result = $this->scoreCalculatorChain->executeChainWithTypes($track, $unmatchedTrack, $pathInfo, $calculatorTypes);

        return min(self::MAX_SCORE, $result['score']);
    }

    /**
     * Get detailed match analysis with all scores.
     */
    public function getDetailedMatchAnalysis(Track $track, UnmatchedTrack $unmatchedTrack, array $pathInfo): array
    {
        $result = $this->scoreCalculatorChain->executeChain($track, $unmatchedTrack, $pathInfo);

        return [
            'totalScore' => min(self::MAX_SCORE, $result['score']),
            'reasons' => $result['reasons'],
            'pathInfo' => $pathInfo,
            'availableTypes' => $this->scoreCalculatorChain->getAvailableTypes(),
            'trackInfo' => $this->extractTrackInfo($track),
            'unmatchedTrackInfo' => $this->extractUnmatchedTrackInfo($unmatchedTrack),
        ];
    }

    /**
     * Extract track information for analysis.
     */
    private function extractTrackInfo(Track $track): array
    {
        return [
            'title' => $track->getTitle(),
            'trackNumber' => $track->getTrackNumber(),
            'album' => $track->getAlbum()?->getTitle(),
            'artist' => $track->getAlbum()?->getArtist()?->getName(),
        ];
    }

    /**
     * Extract unmatched track information for analysis.
     */
    private function extractUnmatchedTrackInfo(UnmatchedTrack $unmatchedTrack): array
    {
        return [
            'title' => $unmatchedTrack->getTitle(),
            'artist' => $unmatchedTrack->getArtist(),
            'album' => $unmatchedTrack->getAlbum(),
        ];
    }
}
