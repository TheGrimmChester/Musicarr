<?php

declare(strict_types=1);

namespace App\TrackMatcher\Calculator\Strategy;

use App\Entity\Configuration;
use Doctrine\ORM\EntityManagerInterface;

class ExactDurationScoringStrategy implements DurationScoringStrategyInterface
{
    private const EXACT_MATCH_SCORE = 100.0;
    private const EXACT_MATCH_REQUIRED_PENALTY = -1.0;

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function calculateScore(int $trackDuration, int $unmatchedDuration): float
    {
        if ($trackDuration === $unmatchedDuration) {
            return self::EXACT_MATCH_SCORE;
        }

        if ($this->requiresExactDurationMatch()) {
            return self::EXACT_MATCH_REQUIRED_PENALTY;
        }

        return 0.0;
    }

    public function getScoreReason(int $trackDuration, int $unmatchedDuration): ?string
    {
        if ($trackDuration === $unmatchedDuration) {
            return 'Exact duration match';
        }

        if ($this->requiresExactDurationMatch()) {
            return 'Duration mismatch (exact match required)';
        }

        return null;
    }

    private function requiresExactDurationMatch(): bool
    {
        $config = $this->entityManager->getRepository(Configuration::class)
            ->findByKey('association.exact_duration_match');

        if (!$config) {
            return false; // Default to false if not configured
        }

        return true === $config->getParsedValue();
    }
}
