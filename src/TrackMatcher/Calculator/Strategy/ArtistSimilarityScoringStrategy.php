<?php

declare(strict_types=1);

namespace App\TrackMatcher\Calculator\Strategy;

use App\StringSimilarity;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.artist_scoring_strategy')]
class ArtistSimilarityScoringStrategy implements ArtistScoringStrategyInterface
{
    private const HIGH_SIMILARITY_THRESHOLD = 0.8;
    private const LOW_SIMILARITY_THRESHOLD = 0.3;
    private const HIGH_SIMILARITY_MULTIPLIER = 5.0;
    private const LOW_SIMILARITY_PENALTY = -15.0;

    public function __construct(
        private StringSimilarity $stringSimilarity
    ) {
    }

    public static function getPriority(): int
    {
        return 60; // Medium priority for similarity matches
    }

    public function calculateScore(string $artistName, string $unmatchedArtist, array $pathInfo = []): float
    {
        $artistSimilarity = $this->stringSimilarity->calculateSimilarity($artistName, $unmatchedArtist);

        if ($artistSimilarity > self::HIGH_SIMILARITY_THRESHOLD) {
            return $artistSimilarity * self::HIGH_SIMILARITY_MULTIPLIER;
        }

        if ($artistSimilarity < self::LOW_SIMILARITY_THRESHOLD) {
            return self::LOW_SIMILARITY_PENALTY;
        }

        return 0.0;
    }

    public function getScoreReason(string $artistName, string $unmatchedArtist, array $pathInfo = []): ?string
    {
        $artistSimilarity = $this->stringSimilarity->calculateSimilarity($artistName, $unmatchedArtist);

        if ($artistSimilarity > self::HIGH_SIMILARITY_THRESHOLD) {
            return "Artist similarity ({$artistSimilarity})";
        }

        if ($artistSimilarity < self::LOW_SIMILARITY_THRESHOLD) {
            return "Artist mismatch penalty ({$artistSimilarity})";
        }

        return null;
    }
}
