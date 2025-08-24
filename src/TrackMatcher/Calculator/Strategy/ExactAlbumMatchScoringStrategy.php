<?php

declare(strict_types=1);

namespace App\TrackMatcher\Calculator\Strategy;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.album_scoring_strategy')]
class ExactAlbumMatchScoringStrategy implements AlbumScoringStrategyInterface
{
    private const EXACT_MATCH_SCORE = 25.0;

    public static function getPriority(): int
    {
        return 100; // Highest priority for exact matches
    }

    public function calculateScore(string $albumTitle, string $unmatchedAlbum, array $pathInfo = []): float
    {
        if ($this->isExactMatch($albumTitle, $unmatchedAlbum)) {
            return self::EXACT_MATCH_SCORE;
        }

        return 0.0;
    }

    public function getScoreReason(string $albumTitle, string $unmatchedAlbum, array $pathInfo = []): ?string
    {
        if ($this->isExactMatch($albumTitle, $unmatchedAlbum)) {
            return 'Exact album match';
        }

        return null;
    }

    private function isExactMatch(string $albumTitle, string $unmatchedAlbum): bool
    {
        return 0 === strcasecmp(mb_trim($albumTitle), mb_trim($unmatchedAlbum));
    }
}
