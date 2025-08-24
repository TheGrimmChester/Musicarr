<?php

declare(strict_types=1);

namespace App\TrackMatcher\Calculator\Strategy;

class ApproximateDurationScoringStrategy implements DurationScoringStrategyInterface
{
    public function calculateScore(int $trackDuration, int $unmatchedDuration): float
    {
        // Calculate duration difference in seconds
        $durationDifference = abs($trackDuration - $unmatchedDuration);

        // Score decreases as duration difference increases
        // 1 second difference: 90 points
        // 2-3 seconds: 80 points
        // 4-5 seconds: 70 points
        // 6-10 seconds: 50 points
        // 11-30 seconds: 30 points
        // 31+ seconds: 10 points
        if (1 === $durationDifference) {
            return 90.0;
        } elseif ($durationDifference <= 3) {
            return 80.0;
        } elseif ($durationDifference <= 5) {
            return 70.0;
        } elseif ($durationDifference <= 10) {
            return 50.0;
        } elseif ($durationDifference <= 30) {
            return 30.0;
        }

        return 10.0;
    }

    public function getScoreReason(int $trackDuration, int $unmatchedDuration): ?string
    {
        $durationDifference = abs($trackDuration - $unmatchedDuration);

        if (1 === $durationDifference) {
            return "Close duration match: {$unmatchedDuration}s vs {$trackDuration}s (1s difference)";
        } elseif ($durationDifference <= 3) {
            return "Duration match: {$unmatchedDuration}s vs {$trackDuration}s ({$durationDifference}s difference)";
        } elseif ($durationDifference <= 5) {
            return "Duration match: {$unmatchedDuration}s vs {$trackDuration}s ({$durationDifference}s difference)";
        } elseif ($durationDifference <= 10) {
            return "Duration match: {$unmatchedDuration}s vs {$trackDuration}s ({$durationDifference}s difference)";
        } elseif ($durationDifference <= 30) {
            return "Duration match: {$unmatchedDuration}s vs {$trackDuration}s ({$durationDifference}s difference)";
        }

        return "Duration match: {$unmatchedDuration}s vs {$trackDuration}s ({$durationDifference}s difference)";
    }
}
