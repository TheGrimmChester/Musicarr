<?php

declare(strict_types=1);

namespace App\TrackMatcher\Calculator;

use App\Entity\Track;
use App\Entity\UnmatchedTrack;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.score_calculator')]
interface ScoreCalculatorInterface
{
    /**
     * Calculate score contribution for this specific matching criteria.
     */
    public function calculateScore(Track $track, UnmatchedTrack $unmatchedTrack, array $pathInfo): float;

    /**
     * Get the reason for the score contribution.
     */
    public function getScoreReason(Track $track, UnmatchedTrack $unmatchedTrack, array $pathInfo): ?string;

    /**
     * Get the priority of this calculator (higher number = higher priority).
     */
    public static function getPriority(): int;

    /**
     * Get the type/name of this calculator.
     */
    public function getType(): string;
}
