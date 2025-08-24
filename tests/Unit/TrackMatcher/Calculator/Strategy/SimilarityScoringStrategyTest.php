<?php

declare(strict_types=1);

namespace App\Tests\Unit\TrackMatcher\Calculator\Strategy;

use App\StringSimilarity;
use App\TrackMatcher\Calculator\Strategy\SimilarityScoringStrategy;
use PHPUnit\Framework\TestCase;

class SimilarityScoringStrategyTest extends TestCase
{
    private SimilarityScoringStrategy $strategy;
    private StringSimilarity $stringSimilarity;

    protected function setUp(): void
    {
        $this->stringSimilarity = $this->createMock(StringSimilarity::class);
        $this->strategy = new SimilarityScoringStrategy($this->stringSimilarity);
    }

    public function testCalculateScoreWithHighSimilarity(): void
    {
        $this->stringSimilarity->method('calculateSimilarity')
            ->with('Test Album', 'Test Album')
            ->willReturn(0.9);

        $score = $this->strategy->calculateScore('Test Album', 'Test Album');

        $this->assertEquals(4.5, $score); // 0.9 * 5.0
    }

    public function testCalculateScoreWithMediumSimilarity(): void
    {
        $this->stringSimilarity->method('calculateSimilarity')
            ->with('Test Album', 'Test Album')
            ->willReturn(0.6);

        $score = $this->strategy->calculateScore('Test Album', 'Test Album');

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithLowSimilarity(): void
    {
        $this->stringSimilarity->method('calculateSimilarity')
            ->with('Test Album', 'Different Album')
            ->willReturn(0.2);

        $score = $this->strategy->calculateScore('Test Album', 'Different Album');

        $this->assertEquals(-10.0, $score);
    }

    public function testCalculateScoreWithExactSimilarity(): void
    {
        $this->stringSimilarity->method('calculateSimilarity')
            ->with('Test Album', 'Test Album')
            ->willReturn(1.0);

        $score = $this->strategy->calculateScore('Test Album', 'Test Album');

        $this->assertEquals(5.0, $score); // 1.0 * 5.0
    }

    public function testCalculateScoreWithThresholdSimilarity(): void
    {
        $this->stringSimilarity->method('calculateSimilarity')
            ->with('Test Album', 'Test Album')
            ->willReturn(0.8);

        $score = $this->strategy->calculateScore('Test Album', 'Test Album');

        $this->assertEquals(4.0, $score); // 0.8 * 5.0
    }

    public function testGetScoreReasonWithHighSimilarity(): void
    {
        $this->stringSimilarity->method('calculateSimilarity')
            ->with('Test Album', 'Test Album')
            ->willReturn(0.9);

        $reason = $this->strategy->getScoreReason('Test Album', 'Test Album');

        $this->assertEquals('Album similarity (0.9)', $reason);
    }

    public function testGetScoreReasonWithMediumSimilarity(): void
    {
        $this->stringSimilarity->method('calculateSimilarity')
            ->with('Test Album', 'Test Album')
            ->willReturn(0.6);

        $reason = $this->strategy->getScoreReason('Test Album', 'Test Album');

        $this->assertNull($reason);
    }

    public function testGetScoreReasonWithLowSimilarity(): void
    {
        $this->stringSimilarity->method('calculateSimilarity')
            ->with('Test Album', 'Different Album')
            ->willReturn(0.2);

        $reason = $this->strategy->getScoreReason('Test Album', 'Different Album');

        $this->assertEquals('Album mismatch penalty (0.2)', $reason);
    }

    public function testGetScoreReasonWithThresholdSimilarity(): void
    {
        $this->stringSimilarity->method('calculateSimilarity')
            ->with('Test Album', 'Test Album')
            ->willReturn(0.8);

        $reason = $this->strategy->getScoreReason('Test Album', 'Test Album');

        $this->assertEquals('Album similarity (0.8)', $reason);
    }
}
