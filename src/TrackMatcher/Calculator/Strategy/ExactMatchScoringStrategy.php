<?php

declare(strict_types=1);

namespace App\TrackMatcher\Calculator\Strategy;

use App\Configuration\Config\ConfigurationFactory;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.artist_scoring_strategy')]
class ExactMatchScoringStrategy implements ArtistScoringStrategyInterface
{
    private const EXACT_MATCH_SCORE = 25.0;
    private const EXACT_MATCH_REQUIRED_PENALTY = -50.0;

    public function __construct(
        private ConfigurationFactory $configurationFactory
    ) {
    }

    public static function getPriority(): int
    {
        return 90; // High priority for exact matches
    }

    public function calculateScore(string $artistName, string $unmatchedArtist, array $pathInfo = []): float
    {
        if (0 === strcasecmp($artistName, $unmatchedArtist)) {
            return self::EXACT_MATCH_SCORE;
        }

        if ($this->requiresExactMatch()) {
            return self::EXACT_MATCH_REQUIRED_PENALTY;
        }

        return 0.0;
    }

    public function getScoreReason(string $artistName, string $unmatchedArtist, array $pathInfo = []): ?string
    {
        if (0 === strcasecmp($artistName, $unmatchedArtist)) {
            return 'Exact artist match';
        }

        if ($this->requiresExactMatch()) {
            return 'Artist mismatch (exact match required)';
        }

        return null;
    }

    private function requiresExactMatch(): bool
    {
        $associationConfig = $this->configurationFactory->getDefaultConfiguration('association.');

        return $associationConfig['exact_artist_match'] ?? false;
    }
}
