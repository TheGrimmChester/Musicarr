<?php

declare(strict_types=1);

namespace App\TrackMatcher\Calculator;

use App\Entity\Track;
use App\Entity\UnmatchedTrack;
use App\TrackMatcher\Calculator\Strategy\ArtistScoringStrategyInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class ArtistMatchCalculator extends AbstractScoreCalculator
{
    /**
     * @param ArtistScoringStrategyInterface[] $strategies
     */
    public function __construct(
        #[TaggedIterator('app.artist_scoring_strategy', defaultPriorityMethod: 'getPriority')]
        private iterable $strategies
    ) {
    }

    public static function getPriority(): int
    {
        return 80; // High priority
    }

    public function getType(): string
    {
        return 'artist';
    }

    public function calculateScore(Track $track, UnmatchedTrack $unmatchedTrack, array $pathInfo): float
    {
        if (!$this->validateEntities($track, $unmatchedTrack)) {
            return 0.0;
        }

        $album = $track->getAlbum();
        if (!$album) {
            return 0.0;
        }

        $artist = $album->getArtist();
        if (!$artist) {
            return 0.0;
        }

        $artistName = $artist->getName();
        $unmatchedTrackArtist = $unmatchedTrack->getArtist();

        if (!$artistName || !$unmatchedTrackArtist) {
            return 0.0;
        }

        $result = $this->executeStrategyChain($artistName, $unmatchedTrackArtist, $pathInfo, 'calculateScore');

        return is_numeric($result) ? (float) $result : 0.0;
    }

    public function getScoreReason(Track $track, UnmatchedTrack $unmatchedTrack, array $pathInfo): ?string
    {
        if (!$this->validateEntities($track, $unmatchedTrack)) {
            return null;
        }

        $album = $track->getAlbum();
        if (!$album) {
            return null;
        }

        $artist = $album->getArtist();
        if (!$artist) {
            return null;
        }

        $artistName = $artist->getName();
        $unmatchedTrackArtist = $unmatchedTrack->getArtist();

        if (!$artistName || !$unmatchedTrackArtist) {
            return null;
        }

        $result = $this->executeStrategyChain($artistName, $unmatchedTrackArtist, $pathInfo, 'getScoreReason');

        return \is_string($result) ? $result : null;
    }

    /**
     * Execute the strategy chain and return the first non-zero result.
     */
    private function executeStrategyChain(string $artistName, string $unmatchedArtist, array $pathInfo, string $method): mixed
    {
        foreach ($this->strategies as $strategy) {
            $result = 'calculateScore' === $method
                ? $strategy->calculateScore($artistName, $unmatchedArtist, $pathInfo)
                : $strategy->getScoreReason($artistName, $unmatchedArtist, $pathInfo);

            if ($this->isValidResult($result, $method)) {
                return $result;
            }
        }

        return 'calculateScore' === $method ? 0.0 : null;
    }

    /**
     * Check if the result is valid (non-zero for score, non-null for reason).
     */
    private function isValidResult(mixed $result, string $method): bool
    {
        return 'calculateScore' === $method ? 0.0 !== $result : null !== $result;
    }
}
