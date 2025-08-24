<?php

declare(strict_types=1);

namespace App\TrackMatcher\Calculator\Strategy;

class VinylTrackNumberScoringStrategy implements TrackNumberScoringStrategyInterface
{
    public function calculateScore(string $trackNumber, string $unmatchedTrackNumber): float
    {
        // Handle vinyl record track numbers (A1, B1, etc.)
        if (preg_match('/^([A-Z])(\d+)$/', $trackNumber, $trackMatches)
            && preg_match('/^([A-Z])(\d+)$/', $unmatchedTrackNumber, $unmatchedMatches)) {
            // Same side (A, B, C, etc.)
            if ($trackMatches[1] === $unmatchedMatches[1]) {
                // Same side, check track number within that side
                $trackNum = (int) $trackMatches[2];
                $unmatchedNum = (int) $unmatchedMatches[2];
                $difference = abs($trackNum - $unmatchedNum);

                if (0 === $difference) {
                    return 15.0; // Exact match
                } elseif ($difference <= 2) {
                    return 10.0; // Close match on same side
                } elseif ($difference <= 5) {
                    return 5.0; // Reasonable match on same side
                }
            }
        }

        return 0.0;
    }

    public function getScoreReason(string $trackNumber, string $unmatchedTrackNumber): ?string
    {
        // Handle vinyl record track numbers (A1, B1, etc.)
        if (preg_match('/^([A-Z])(\d+)$/', $trackNumber, $trackMatches)
            && preg_match('/^([A-Z])(\d+)$/', $unmatchedTrackNumber, $unmatchedMatches)) {
            // Same side (A, B, C, etc.)
            if ($trackMatches[1] === $unmatchedMatches[1]) {
                $trackNum = (int) $trackMatches[2];
                $unmatchedNum = (int) $unmatchedMatches[2];
                $difference = abs($trackNum - $unmatchedNum);

                if (0 === $difference) {
                    return 'Track number match on same side';
                } elseif ($difference <= 2) {
                    return "Close track number match on same side (difference: {$difference})";
                } elseif ($difference <= 5) {
                    return "Reasonable track number match on same side (difference: {$difference})";
                }
            }
        }

        return null;
    }
}
