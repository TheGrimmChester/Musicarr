<?php

declare(strict_types=1);

namespace App\TrackMatcher\Calculator\Strategy;

interface DurationScoringStrategyInterface
{
    /**
     * Calculate score for duration matching.
     */
    public function calculateScore(int $trackDuration, int $unmatchedDuration): float;

    /**
     * Get reason for the calculated score.
     */
    public function getScoreReason(int $trackDuration, int $unmatchedDuration): ?string;
}
