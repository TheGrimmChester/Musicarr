<?php

declare(strict_types=1);

namespace App\TrackMatcher\Calculator\Strategy;

class NumericTrackNumberScoringStrategy implements TrackNumberScoringStrategyInterface
{
    public function calculateScore(string $trackNumber, string $unmatchedTrackNumber): float
    {
        // Try to convert to integers for numeric comparison
        $trackNumInt = (int) $trackNumber;
        $unmatchedNumInt = (int) $unmatchedTrackNumber;

        // Only do numeric comparison if both are actually numeric
        if ($trackNumInt > 0 && $unmatchedNumInt > 0) {
            $difference = abs($trackNumInt - $unmatchedNumInt);
            if ($difference > 5) {
                return -5.0; // Penalty for significant track number difference
            }
        }

        return 0.0;
    }

    public function getScoreReason(string $trackNumber, string $unmatchedTrackNumber): ?string
    {
        // Try to convert to integers for numeric comparison
        $trackNumInt = (int) $trackNumber;
        $unmatchedNumInt = (int) $unmatchedTrackNumber;

        // Only do numeric comparison if both are actually numeric
        if ($trackNumInt > 0 && $unmatchedNumInt > 0) {
            $difference = abs($trackNumInt - $unmatchedNumInt);
            if ($difference > 5) {
                return "Track number mismatch (difference: {$difference})";
            }
        }

        return null;
    }
}
