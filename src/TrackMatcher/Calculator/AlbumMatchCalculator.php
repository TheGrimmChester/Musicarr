<?php

declare(strict_types=1);

namespace App\TrackMatcher\Calculator;

use App\Entity\Track;
use App\Entity\UnmatchedTrack;
use App\TrackMatcher\Calculator\Strategy\AlbumScoringStrategyInterface;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class AlbumMatchCalculator extends AbstractScoreCalculator
{
    /**
     * @param AlbumScoringStrategyInterface[] $strategies
     */
    public function __construct(
        #[TaggedIterator('app.album_scoring_strategy', defaultPriorityMethod: 'getPriority')]
        private iterable $strategies
    ) {
    }

    public static function getPriority(): int
    {
        return 60; // Medium priority
    }

    public function getType(): string
    {
        return 'album';
    }

    public function calculateScore(Track $track, UnmatchedTrack $unmatchedTrack, array $pathInfo): float
    {
        if (!$this->validateEntities($track, $unmatchedTrack)) {
            return 0.0;
        }

        $album = $track->getAlbum();
        $unmatchedAlbum = $unmatchedTrack->getAlbum();

        if (!$album?->getTitle() || !$unmatchedAlbum) {
            return 0.0;
        }

        $cleanedTitle = $this->clean($album->getTitle());
        $cleanedUnmatched = $this->clean($unmatchedAlbum);

        $result = $this->executeStrategyChain($cleanedTitle, $cleanedUnmatched, $pathInfo, 'calculateScore');

        return is_numeric($result) ? (float) $result : 0.0;
    }

    public function getScoreReason(Track $track, UnmatchedTrack $unmatchedTrack, array $pathInfo): ?string
    {
        if (!$this->validateEntities($track, $unmatchedTrack)) {
            return null;
        }

        $album = $track->getAlbum();
        $unmatchedAlbum = $unmatchedTrack->getAlbum();

        if (!$album?->getTitle() || !$unmatchedAlbum) {
            return null;
        }

        $cleanedTitle = $this->clean($album->getTitle());
        $cleanedUnmatched = $this->clean($unmatchedAlbum);

        $result = $this->executeStrategyChain($cleanedTitle, $cleanedUnmatched, $pathInfo, 'getScoreReason');

        return \is_string($result) ? $result : null;
    }

    /**
     * Execute the strategy chain and return the first non-zero result.
     */
    private function executeStrategyChain(string $albumTitle, string $unmatchedAlbum, array $pathInfo, string $method): mixed
    {
        foreach ($this->strategies as $strategy) {
            $result = $this->callStrategyMethod($strategy, $method, $albumTitle, $unmatchedAlbum, $pathInfo);

            if ($this->isValidResult($result, $method)) {
                return $result;
            }
        }

        return $this->getDefaultResult($method);
    }

    /**
     * Call the appropriate method on the strategy.
     */
    private function callStrategyMethod(AlbumScoringStrategyInterface $strategy, string $method, string $albumTitle, string $unmatchedAlbum, array $pathInfo): mixed
    {
        return match ($method) {
            'calculateScore' => $strategy->calculateScore($albumTitle, $unmatchedAlbum, $pathInfo),
            'getScoreReason' => $strategy->getScoreReason($albumTitle, $unmatchedAlbum, $pathInfo),
            default => throw new InvalidArgumentException("Unknown method: {$method}")
        };
    }

    /**
     * Check if the result is valid (non-zero for score, non-null for reason).
     */
    private function isValidResult(mixed $result, string $method): bool
    {
        return match ($method) {
            'calculateScore' => 0.0 !== $result,
            'getScoreReason' => null !== $result,
            default => false
        };
    }

    /**
     * Get default result based on method type.
     */
    private function getDefaultResult(string $method): mixed
    {
        return match ($method) {
            'calculateScore' => 0.0,
            'getScoreReason' => null,
            default => throw new InvalidArgumentException("Unknown method: {$method}")
        };
    }

    /**
     * Clean title by removing track numbers and features in parentheses.
     */
    private function clean(string $title): string
    {
        $cleaned = preg_replace('/:/', '', $title);
        $cleaned = preg_replace('/-/', '', $cleaned);
        $cleaned = preg_replace('/  /', ' ', $cleaned);

        return mb_trim($cleaned);
    }
}
