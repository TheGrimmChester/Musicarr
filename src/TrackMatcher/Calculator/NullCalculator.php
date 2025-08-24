<?php

declare(strict_types=1);

namespace App\TrackMatcher\Calculator;

use App\Entity\Track;
use App\Entity\UnmatchedTrack;

class NullCalculator extends AbstractScoreCalculator
{
    public static function getPriority(): int
    {
        return 0; // No priority
    }

    public function getType(): string
    {
        return 'null';
    }

    public function calculateScore(Track $track, UnmatchedTrack $unmatchedTrack, array $pathInfo): float
    {
        return 0.0;
    }

    public function getScoreReason(Track $track, UnmatchedTrack $unmatchedTrack, array $pathInfo): ?string
    {
        return null;
    }
}
