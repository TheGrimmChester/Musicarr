<?php

declare(strict_types=1);

namespace App\TrackMatcher\Calculator\Strategy;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.artist_scoring_strategy')]
class ArtistPathMatchScoringStrategy implements ArtistScoringStrategyInterface
{
    private const PATH_MATCH_SCORE = 20.0;
    private const PATH_INFO_ARTIST_KEY = 'artist';

    public static function getPriority(): int
    {
        return 80; // High priority for path matches
    }

    public function calculateScore(string $artistName, string $unmatchedArtist, array $pathInfo = []): float
    {
        if (!$this->hasPathMatch($artistName, $pathInfo)) {
            return 0.0;
        }

        return self::PATH_MATCH_SCORE;
    }

    public function getScoreReason(string $artistName, string $unmatchedArtist, array $pathInfo = []): ?string
    {
        if (!$this->hasPathMatch($artistName, $pathInfo)) {
            return null;
        }

        return 'Directory artist match';
    }

    private function hasPathMatch(string $artistName, array $pathInfo): bool
    {
        return isset($pathInfo[self::PATH_INFO_ARTIST_KEY])
            && 0 === strcasecmp($artistName, $pathInfo[self::PATH_INFO_ARTIST_KEY]);
    }
}
