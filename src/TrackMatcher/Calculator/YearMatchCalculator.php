<?php

declare(strict_types=1);

namespace App\TrackMatcher\Calculator;

use App\Entity\Track;
use App\Entity\UnmatchedTrack;
use Doctrine\ORM\EntityManagerInterface;

class YearMatchCalculator extends AbstractScoreCalculator
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public static function getPriority(): int
    {
        return 40; // Lower priority
    }

    public function getType(): string
    {
        return 'year';
    }

    public function calculateScore(Track $track, UnmatchedTrack $unmatchedTrack, array $pathInfo): float
    {
        if (!$this->validateEntities($track, $unmatchedTrack)) {
            return 0.0;
        }

        $unmatchedYear = $unmatchedTrack->getYear();
        $album = $track->getAlbum();

        if (!$album || !$album->getReleaseDate()) {
            return 0.0;
        }

        $trackYear = (int) $album->getReleaseDate()->format('Y');

        // If we don't have year information for either, return neutral score
        if (null === $unmatchedYear || null === $trackYear) {
            return 0.0;
        }

        // Exact year match gets maximum score
        if ($unmatchedYear === $trackYear) {
            return 100.0;
        }

        // Calculate year difference
        $yearDifference = abs($unmatchedYear - $trackYear);

        // Small differences (1-2 years) always get scores
        if (1 === $yearDifference) {
            return 80.0;
        } elseif (2 === $yearDifference) {
            return 60.0;
        }

        // Medium differences (3 years) always get scores
        if (3 === $yearDifference) {
            return 40.0;
        }

        // Large differences (4+ years) always get low scores
        if ($yearDifference >= 4) {
            return 20.0;
        }

        return 0.0;
    }

    public function getScoreReason(Track $track, UnmatchedTrack $unmatchedTrack, array $pathInfo): ?string
    {
        if (!$this->validateEntities($track, $unmatchedTrack)) {
            return null;
        }

        $unmatchedYear = $unmatchedTrack->getYear();
        $album = $track->getAlbum();

        if (!$album || !$album->getReleaseDate()) {
            return null;
        }

        $trackYear = (int) $album->getReleaseDate()->format('Y');

        if (null === $unmatchedYear || null === $trackYear) {
            return null;
        }

        if ($unmatchedYear === $trackYear) {
            return "Exact year match: {$unmatchedYear}";
        }

        $yearDifference = abs($unmatchedYear - $trackYear);

        // Small differences (1-2 years) get "Close year match"
        if (1 === $yearDifference || 2 === $yearDifference) {
            return "Close year match: {$yearDifference} year difference";
        }

        // Medium differences (3 years) get "Year match"
        if (3 === $yearDifference) {
            return "Year match: {$yearDifference} year difference";
        }

        // Large differences (4+ years) get "Year match"
        if ($yearDifference >= 4) {
            return "Year match: {$yearDifference} year difference";
        }

        return "Year difference: {$yearDifference} years";
    }
}
