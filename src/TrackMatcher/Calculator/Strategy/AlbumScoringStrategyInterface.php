<?php

declare(strict_types=1);

namespace App\TrackMatcher\Calculator\Strategy;

interface AlbumScoringStrategyInterface
{
    public static function getPriority(): int;

    public function calculateScore(string $albumTitle, string $unmatchedAlbum, array $pathInfo = []): float;

    public function getScoreReason(string $albumTitle, string $unmatchedAlbum, array $pathInfo = []): ?string;
}
