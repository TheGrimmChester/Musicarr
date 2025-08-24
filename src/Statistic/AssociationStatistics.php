<?php

declare(strict_types=1);

namespace App\Statistic;

use App\Configuration\Domain\AssociationConfigurationDomain;
use App\Repository\AlbumRepository;
use App\Repository\ArtistRepository;
use App\Repository\TrackRepository;
use App\Repository\UnmatchedTrackRepository;
use DateTime;
use Exception;

class AssociationStatistics
{
    public function __construct(
        private AssociationConfigurationDomain $associationDomain,
        private UnmatchedTrackRepository $unmatchedTrackRepository,
        private TrackRepository $trackRepository,
        private ArtistRepository $artistRepository,
        private AlbumRepository $albumRepository
    ) {
    }

    /**
     * Get comprehensive association statistics.
     */
    public function getAssociationStatistics(): array
    {
        $minScoreThreshold = $this->getMinimumScoreThreshold();

        return [
            'total_tracks' => $this->getTotalTracksCount(),
            'matched_tracks' => $this->getMatchedTracksCount(),
            'unmatched_tracks' => $this->getUnmatchedTracksCount(),
            'match_rate' => $this->calculateMatchRate(),
            'min_score_threshold' => $minScoreThreshold,
            'threshold_effectiveness' => $this->getThresholdEffectiveness(),
            'recent_activity' => $this->getRecentActivity(),
            'quality_distribution' => $this->getQualityDistribution(),
            'recommendations' => $this->getRecommendations($minScoreThreshold),
            'total_configs' => $this->getTotalConfigurationCount(),
        ];
    }

    /**
     * Get minimum score threshold from configuration.
     */
    private function getMinimumScoreThreshold(): float
    {
        return $this->associationDomain->getMinimumScoreThreshold();
    }

    /**
     * Get current count of unmatched tracks.
     */
    private function getUnmatchedTracksCount(): int
    {
        return $this->unmatchedTrackRepository->count(['isMatched' => false]);
    }

    /**
     * Record association attempt (for future implementation).
     */
    public function recordAssociationAttempt(string $_result, ?float $_score = null, ?string $_reason = null): void
    {
        // This would insert into a statistics table
        // For now, we'll just log it
        // Future implementation could store:
        // - timestamp
        // - result (success/rejected_low_score/rejected_no_match/error)
        // - score
        // - reason
        // - threshold used
    }

    /**
     * Get association statistics by date range.
     *
     * @return array{
     *     period: array{start: string, end: string},
     *     total_attempts: int,
     *     successful: int,
     *     rejected_low_score: int,
     *     rejected_no_match: int,
     *     errors: int,
     *     average_score: float
     * }
     */
    public function getStatisticsByDateRange(DateTime $startDate, DateTime $endDate): array
    {
        // Future implementation for detailed statistics
        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'total_attempts' => 0,
            'successful' => 0,
            'rejected_low_score' => 0,
            'rejected_no_match' => 0,
            'errors' => 0,
            'average_score' => 0.0,
        ];
    }

    /**
     * Get score distribution statistics.
     *
     * @return array{
     *     excellent: array{range: string, count: int},
     *     good: array{range: string, count: int},
     *     fair: array{range: string, count: int},
     *     poor: array{range: string, count: int}
     * }
     */
    public function getScoreDistribution(): array
    {
        // Future implementation to show distribution of scores
        return [
            'excellent' => ['range' => '90-100', 'count' => 0],
            'good' => ['range' => '70-89', 'count' => 0],
            'fair' => ['range' => '50-69', 'count' => 0],
            'poor' => ['range' => '0-49', 'count' => 0],
        ];
    }

    /**
     * Get threshold effectiveness analysis.
     *
     * @return array{
     *     current_threshold: float,
     *     recommendation: string,
     *     estimated_impact: array{
     *         estimated_additional_matches: int,
     *         estimated_rejected_matches: int,
     *         confidence: string
     *     }
     * }
     */
    public function getThresholdEffectiveness(): array
    {
        $currentThreshold = $this->getMinimumScoreThreshold();

        return [
            'current_threshold' => $currentThreshold,
            'recommendation' => $this->generateThresholdRecommendation($currentThreshold),
            'estimated_impact' => [
                'estimated_additional_matches' => $this->estimateAdditionalMatches($currentThreshold),
                'estimated_rejected_matches' => $this->estimateRejectedMatches($currentThreshold),
                'confidence' => 'medium',
            ],
        ];
    }

    /**
     * Get total tracks count.
     */
    private function getTotalTracksCount(): int
    {
        return $this->trackRepository->count([]);
    }

    /**
     * Get matched tracks count.
     */
    private function getMatchedTracksCount(): int
    {
        // Count tracks that have files (indicating they are matched)
        return $this->trackRepository->count(['hasFile' => true]);
    }

    /**
     * Calculate match rate percentage.
     */
    private function calculateMatchRate(): float
    {
        $total = $this->getTotalTracksCount();
        if (0 === $total) {
            return 0.0;
        }

        $matched = $this->getMatchedTracksCount();

        return round(($matched / $total) * 100, 2);
    }

    /**
     * Get recent association activity.
     */
    private function getRecentActivity(): array
    {
        // Future implementation to show recent association attempts
        return [
            'last_24_hours' => 0,
            'last_week' => 0,
            'last_month' => 0,
            'trend' => 'stable',
        ];
    }

    /**
     * Get quality distribution of matches.
     */
    private function getQualityDistribution(): array
    {
        // Future implementation to show quality distribution
        return [
            'high_quality' => 0,
            'medium_quality' => 0,
            'low_quality' => 0,
        ];
    }

    /**
     * Generate recommendations based on current state.
     */
    private function getRecommendations(float $currentThreshold): array
    {
        $recommendations = [];
        $matchRate = $this->calculateMatchRate();

        if ($matchRate < 50) {
            $recommendations[] = 'Consider lowering the minimum score threshold to increase match rate';
        }

        if ($matchRate > 90) {
            $recommendations[] = 'High match rate achieved. Consider raising threshold for better quality matches';
        }

        if ($this->getUnmatchedTracksCount() > 1000) {
            $recommendations[] = 'Large number of unmatched tracks. Review association criteria';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Current configuration appears optimal';
        }

        return $recommendations;
    }

    /**
     * Generate threshold recommendation.
     */
    private function generateThresholdRecommendation(float $currentThreshold): string
    {
        $matchRate = $this->calculateMatchRate();

        if ($matchRate < 40) {
            return 'Consider lowering threshold from ' . $currentThreshold . ' to ' . ($currentThreshold - 10);
        }

        if ($matchRate > 95) {
            return 'Consider raising threshold from ' . $currentThreshold . ' to ' . ($currentThreshold + 5);
        }

        return 'Current threshold appears optimal';
    }

    /**
     * Estimate additional matches if threshold is lowered.
     */
    private function estimateAdditionalMatches(float $_currentThreshold): int
    {
        // Future implementation to estimate impact of threshold changes
        return 0;
    }

    /**
     * Estimate rejected matches if threshold is raised.
     */
    private function estimateRejectedMatches(float $_currentThreshold): int
    {
        // Future implementation to estimate impact of threshold changes
        return 0;
    }

    /**
     * Get total configuration count for association domain.
     */
    private function getTotalConfigurationCount(): int
    {
        try {
            return $this->associationDomain->getTotalConfigurationCount();
        } catch (Exception $e) {
            // If the method doesn't exist, return a default value
            return 0;
        }
    }
}
