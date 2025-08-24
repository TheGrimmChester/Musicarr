<?php

declare(strict_types=1);

namespace App\TrackMatcher\Calculator;

use App\Entity\Track;
use App\Entity\UnmatchedTrack;
use App\TrackMatcher\Calculator\Strategy\ApproximateDurationScoringStrategy;
use App\TrackMatcher\Calculator\Strategy\DurationScoringStrategyInterface;
use App\TrackMatcher\Calculator\Strategy\ExactDurationScoringStrategy;
use Doctrine\ORM\EntityManagerInterface;

class DurationMatchCalculator extends AbstractScoreCalculator
{
    private DurationScoringStrategyInterface $exactStrategy;
    private DurationScoringStrategyInterface $approximateStrategy;

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        $this->exactStrategy = new ExactDurationScoringStrategy($this->entityManager);
        $this->approximateStrategy = new ApproximateDurationScoringStrategy();
    }

    public static function getPriority(): int
    {
        return 50; // Medium priority, between year and other factors
    }

    public function getType(): string
    {
        return 'duration';
    }

    public function calculateScore(Track $track, UnmatchedTrack $unmatchedTrack, array $pathInfo): float
    {
        if (!$this->validateEntities($track, $unmatchedTrack)) {
            return 0.0;
        }

        $unmatchedDuration = $unmatchedTrack->getDuration();
        $trackDuration = $track->getDuration();

        // If we don't have duration information for either, return neutral score
        if (null === $unmatchedDuration || null === $trackDuration) {
            return 0.0;
        }

        // Try exact match first
        $exactScore = $this->exactStrategy->calculateScore($trackDuration, $unmatchedDuration);
        if (100.0 === $exactScore) {
            return $exactScore;
        }
        if (-1.0 === $exactScore) {
            return 0.0; // Exact duration required but not matched
        }

        // Try approximate match
        return $this->approximateStrategy->calculateScore($trackDuration, $unmatchedDuration);
    }

    public function getScoreReason(Track $track, UnmatchedTrack $unmatchedTrack, array $pathInfo): ?string
    {
        if (!$this->validateEntities($track, $unmatchedTrack)) {
            return null;
        }

        $unmatchedDuration = $unmatchedTrack->getDuration();
        $trackDuration = $track->getDuration();

        if (null === $unmatchedDuration || null === $trackDuration) {
            return null;
        }

        // Try exact match first
        $exactReason = $this->exactStrategy->getScoreReason($trackDuration, $unmatchedDuration);
        if ($exactReason) {
            return $exactReason;
        }

        // Try approximate match
        return $this->approximateStrategy->getScoreReason($trackDuration, $unmatchedDuration);
    }
}
