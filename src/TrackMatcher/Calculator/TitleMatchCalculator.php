<?php

declare(strict_types=1);

namespace App\TrackMatcher\Calculator;

use App\Entity\Configuration;
use App\Entity\Track;
use App\Entity\UnmatchedTrack;
use App\StringSimilarity;
use Doctrine\ORM\EntityManagerInterface;

class TitleMatchCalculator extends AbstractScoreCalculator
{
    public function __construct(
        private StringSimilarity $stringSimilarity,
        private EntityManagerInterface $entityManager
    ) {
    }

    public static function getPriority(): int
    {
        return 100; // Highest priority for title matching
    }

    public function getType(): string
    {
        return 'title';
    }

    public function calculateScore(Track $track, UnmatchedTrack $unmatchedTrack, array $pathInfo): float
    {
        if (!$this->validateEntities($track, $unmatchedTrack)) {
            return 0.0;
        }

        $trackTitle = $track->getTitle();
        $unmatchedTitle = $unmatchedTrack->getTitle();

        if (empty($trackTitle) || empty($unmatchedTitle)) {
            return 0.0;
        }

        // Check for exact title match first
        if ($trackTitle === $unmatchedTitle) {
            return 100.0; // Exact match always gets perfect score
        }

        // Check if exact title match is required
        if ($this->requiresExactTitleMatch()) {
            return 0.0; // Exact match required but not found
        }

        // Calculate similarity score
        $similarity = $this->stringSimilarity->calculateSimilarity($trackTitle, $unmatchedTitle);

        // Convert similarity to score (0-100)
        return $similarity * 100;
    }

    public function getScoreReason(Track $track, UnmatchedTrack $unmatchedTrack, array $pathInfo): ?string
    {
        if (!$this->validateEntities($track, $unmatchedTrack)) {
            return null;
        }

        $trackTitle = $track->getTitle();
        $unmatchedTitle = $unmatchedTrack->getTitle();

        if (empty($trackTitle) || empty($unmatchedTitle)) {
            return null;
        }

        // Check for exact title match first
        if ($trackTitle === $unmatchedTitle) {
            return 'Exact title match';
        }

        // Check if exact title match is required
        if ($this->requiresExactTitleMatch()) {
            return 'Exact title match required but not found';
        }

        $similarity = $this->stringSimilarity->calculateSimilarity($trackTitle, $unmatchedTitle);

        // If similarity is very low, consider it a penalty case
        if ($similarity < 0.2) { // Less than 20% similarity
            return 'Title mismatch penalty (very low similarity)';
        }

        return \sprintf('Title similarity: %.1f%%', $similarity * 100);
    }

    private function requiresExactTitleMatch(): bool
    {
        $config = $this->entityManager->getRepository(Configuration::class)
            ->findByKey('association.require_exact_title_match');

        if (!$config) {
            return false; // Default to false if not configured
        }

        return true === $config->getParsedValue();
    }
}
