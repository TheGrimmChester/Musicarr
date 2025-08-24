<?php

declare(strict_types=1);

namespace App\TrackMatcher\Calculator;

use App\Entity\Track;
use App\Entity\UnmatchedTrack;
use App\TrackMatcher\Calculator\Strategy\ExactTrackNumberScoringStrategy;
use App\TrackMatcher\Calculator\Strategy\NumericTrackNumberScoringStrategy;
use App\TrackMatcher\Calculator\Strategy\TrackNumberScoringStrategyInterface;
use App\TrackMatcher\Calculator\Strategy\VinylTrackNumberScoringStrategy;

class TrackNumberMatchCalculator extends AbstractScoreCalculator
{
    private TrackNumberScoringStrategyInterface $exactStrategy;
    private TrackNumberScoringStrategyInterface $vinylStrategy;
    private TrackNumberScoringStrategyInterface $numericStrategy;

    public function __construct()
    {
        $this->exactStrategy = new ExactTrackNumberScoringStrategy();
        $this->vinylStrategy = new VinylTrackNumberScoringStrategy();
        $this->numericStrategy = new NumericTrackNumberScoringStrategy();
    }

    public static function getPriority(): int
    {
        return 20; // Lowest priority
    }

    public function getType(): string
    {
        return 'trackNumber';
    }

    public function calculateScore(Track $track, UnmatchedTrack $unmatchedTrack, array $pathInfo): float
    {
        if (!$this->validateEntities($track, $unmatchedTrack)) {
            return 0.0;
        }

        $trackNumber = $track->getTrackNumber();
        $unmatchedTrackNumber = $unmatchedTrack->getTrackNumber();

        // Check if we have track number information
        if (null === $trackNumber || null === $unmatchedTrackNumber) {
            return 0.0;
        }

        // Try exact match first
        $exactScore = $this->exactStrategy->calculateScore($trackNumber, $unmatchedTrackNumber);
        if (0.0 !== $exactScore) {
            return $exactScore;
        }

        // Try vinyl match
        $vinylScore = $this->vinylStrategy->calculateScore($trackNumber, $unmatchedTrackNumber);
        if (0.0 !== $vinylScore) {
            return $vinylScore;
        }

        // Try numeric match
        return $this->numericStrategy->calculateScore($trackNumber, $unmatchedTrackNumber);
    }

    public function getScoreReason(Track $track, UnmatchedTrack $unmatchedTrack, array $pathInfo): ?string
    {
        if (!$this->validateEntities($track, $unmatchedTrack)) {
            return null;
        }

        $trackNumber = $track->getTrackNumber();
        $unmatchedTrackNumber = $unmatchedTrack->getTrackNumber();

        // Check if we have track number information
        if (null === $trackNumber || null === $unmatchedTrackNumber) {
            return null;
        }

        // Try exact match first
        $exactReason = $this->exactStrategy->getScoreReason($trackNumber, $unmatchedTrackNumber);
        if ($exactReason) {
            return $exactReason;
        }

        // Try vinyl match
        $vinylReason = $this->vinylStrategy->getScoreReason($trackNumber, $unmatchedTrackNumber);
        if ($vinylReason) {
            return $vinylReason;
        }

        // Try numeric match
        return $this->numericStrategy->getScoreReason($trackNumber, $unmatchedTrackNumber);
    }
}
