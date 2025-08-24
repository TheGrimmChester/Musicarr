<?php

declare(strict_types=1);

namespace App\TrackMatcher\Calculator\Strategy;

use App\StringSimilarity;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.album_scoring_strategy')]
class SimilarityScoringStrategy implements AlbumScoringStrategyInterface
{
    private const HIGH_SIMILARITY_THRESHOLD = 0.8;
    private const LOW_SIMILARITY_THRESHOLD = 0.3;
    private const HIGH_SIMILARITY_MULTIPLIER = 5.0;
    private const LOW_SIMILARITY_PENALTY = -10.0;

    public function __construct(
        private StringSimilarity $stringSimilarity
    ) {
    }

    public static function getPriority(): int
    {
        return 60; // Medium priority for similarity matches
    }

    public function calculateScore(string $albumTitle, string $unmatchedAlbum, array $pathInfo = []): float
    {
        $albumSimilarity = $this->stringSimilarity->calculateSimilarity($albumTitle, $unmatchedAlbum);

        if ($albumSimilarity >= self::HIGH_SIMILARITY_THRESHOLD) {
            return $albumSimilarity * self::HIGH_SIMILARITY_MULTIPLIER;
        }

        if ($albumSimilarity < self::LOW_SIMILARITY_THRESHOLD) {
            return self::LOW_SIMILARITY_PENALTY;
        }

        return 0.0;
    }

    public function getScoreReason(string $albumTitle, string $unmatchedAlbum, array $pathInfo = []): ?string
    {
        $albumSimilarity = $this->stringSimilarity->calculateSimilarity($albumTitle, $unmatchedAlbum);

        if ($albumSimilarity >= self::HIGH_SIMILARITY_THRESHOLD) {
            return "Album similarity ({$albumSimilarity})";
        }

        if ($albumSimilarity < self::LOW_SIMILARITY_THRESHOLD) {
            return "Album mismatch penalty ({$albumSimilarity})";
        }

        return null;
    }
}
