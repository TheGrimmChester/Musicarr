<?php

declare(strict_types=1);

namespace App\TrackMatcher\Calculator;

use App\Entity\Track;
use App\Entity\UnmatchedTrack;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class ScoreCalculatorChain
{
    /**
     * @param ScoreCalculatorInterface[] $calculators
     */
    public function __construct(
        #[TaggedIterator('app.score_calculator', defaultPriorityMethod: 'getPriority')]
        private iterable $calculators
    ) {
    }

    /**
     * Execute the complete chain of calculators.
     */
    public function executeChain(Track $track, UnmatchedTrack $unmatchedTrack, array $pathInfo): array
    {
        $totalScore = 0.0;
        $reasons = [];

        foreach ($this->calculators as $calculator) {
            $score = $calculator->calculateScore($track, $unmatchedTrack, $pathInfo);
            $reason = $calculator->getScoreReason($track, $unmatchedTrack, $pathInfo);

            $totalScore += $score;
            if ($reason) {
                $reasons[] = $reason;
            }
        }

        return [
            'score' => $totalScore,
            'reasons' => $reasons,
        ];
    }

    /**
     * Execute chain with specific calculator types.
     */
    public function executeChainWithTypes(Track $track, UnmatchedTrack $unmatchedTrack, array $pathInfo, array $types): array
    {
        $totalScore = 0.0;
        $reasons = [];

        foreach ($this->calculators as $calculator) {
            if (!$this->isCalculatorTypeAllowed($calculator, $types)) {
                continue;
            }

            $score = $calculator->calculateScore($track, $unmatchedTrack, $pathInfo);
            $reason = $calculator->getScoreReason($track, $unmatchedTrack, $pathInfo);

            $totalScore += $score;
            if ($reason) {
                $reasons[] = $reason;
            }
        }

        return [
            'score' => $totalScore,
            'reasons' => $reasons,
        ];
    }

    /**
     * Check if calculator type is allowed.
     */
    private function isCalculatorTypeAllowed(ScoreCalculatorInterface $calculator, array $types): bool
    {
        return \in_array($calculator->getType(), $types, true);
    }

    /**
     * Get available calculator types.
     */
    public function getAvailableTypes(): array
    {
        $types = [];
        foreach ($this->calculators as $calculator) {
            $types[] = $calculator->getType();
        }

        return array_unique($types);
    }

    /**
     * Get calculator by type.
     */
    public function getCalculatorByType(string $type): ?ScoreCalculatorInterface
    {
        foreach ($this->calculators as $calculator) {
            if ($calculator->getType() === $type) {
                return $calculator;
            }
        }

        return null;
    }
}
