<?php

declare(strict_types=1);

namespace App\TrackMatcher\Calculator\Strategy;

interface TrackNumberScoringStrategyInterface
{
    /**
     * Calculate score for track number matching.
     */
    public function calculateScore(string $trackNumber, string $unmatchedTrackNumber): float;

    /**
     * Get reason for the calculated score.
     */
    public function getScoreReason(string $trackNumber, string $unmatchedTrackNumber): ?string;
}
