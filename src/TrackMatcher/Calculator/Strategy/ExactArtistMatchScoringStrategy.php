<?php

declare(strict_types=1);

namespace App\TrackMatcher\Calculator\Strategy;

use App\Configuration\Config\ConfigurationFactory;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.artist_scoring_strategy')]
class ExactArtistMatchScoringStrategy implements ArtistScoringStrategyInterface
{
    private const EXACT_MATCH_SCORE = 30.0;
    private const EXACT_MATCH_REQUIRED_PENALTY = -50.0;

    public function __construct(
        private ConfigurationFactory $configurationFactory
    ) {
    }

    public static function getPriority(): int
    {
        return 100; // Highest priority for exact matches
    }

    public function calculateScore(string $artistName, string $unmatchedArtist, array $pathInfo = []): float
    {
        if (0 === strcasecmp($artistName, $unmatchedArtist)) {
            return self::EXACT_MATCH_SCORE;
        }

        if ($this->requiresExactArtistMatch()) {
            return self::EXACT_MATCH_REQUIRED_PENALTY;
        }

        return 0.0;
    }

    public function getScoreReason(string $artistName, string $unmatchedArtist, array $pathInfo = []): ?string
    {
        if (0 === strcasecmp($artistName, $unmatchedArtist)) {
            return 'Artist match';
        }

        if ($this->requiresExactArtistMatch()) {
            return 'Artist mismatch (exact match required)';
        }

        return null;
    }

    private function requiresExactArtistMatch(): bool
    {
        $associationConfig = $this->configurationFactory->getDefaultConfiguration('association.');

        return isset($associationConfig['exact_artist_match']) && true === $associationConfig['exact_artist_match'];
    }
}
