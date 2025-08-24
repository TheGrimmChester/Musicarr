<?php

declare(strict_types=1);

namespace App\TrackMatcher\Calculator\Strategy;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.album_scoring_strategy')]
class PathMatchScoringStrategy implements AlbumScoringStrategyInterface
{
    private const PATH_MATCH_SCORE = 15.0;
    private const PATH_INFO_ALBUM_KEY = 'album';

    public static function getPriority(): int
    {
        return 80; // High priority for path matches
    }

    public function calculateScore(string $albumTitle, string $unmatchedAlbum, array $pathInfo = []): float
    {
        if (!$this->hasPathMatch($albumTitle, $pathInfo)) {
            return 0.0;
        }

        return self::PATH_MATCH_SCORE;
    }

    public function getScoreReason(string $albumTitle, string $unmatchedAlbum, array $pathInfo = []): ?string
    {
        if (!$this->hasPathMatch($albumTitle, $pathInfo)) {
            return null;
        }

        return 'Directory album match';
    }

    private function hasPathMatch(string $albumTitle, array $pathInfo): bool
    {
        return isset($pathInfo[self::PATH_INFO_ALBUM_KEY])
            && 0 === strcasecmp($albumTitle, $pathInfo[self::PATH_INFO_ALBUM_KEY]);
    }
}
