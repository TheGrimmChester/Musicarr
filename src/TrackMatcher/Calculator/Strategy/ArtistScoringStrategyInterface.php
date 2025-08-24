<?php

declare(strict_types=1);

namespace App\TrackMatcher\Calculator\Strategy;

interface ArtistScoringStrategyInterface
{
    public static function getPriority(): int;

    public function calculateScore(string $artistName, string $unmatchedArtist, array $pathInfo = []): float;

    public function getScoreReason(string $artistName, string $unmatchedArtist, array $pathInfo = []): ?string;
}
