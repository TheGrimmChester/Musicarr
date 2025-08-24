<?php

declare(strict_types=1);

namespace App\Tests\Unit\TrackMatcher\Calculator\Strategy;

use App\StringSimilarity;
use App\TrackMatcher\Calculator\Strategy\ArtistSimilarityScoringStrategy;
use PHPUnit\Framework\TestCase;

class ArtistSimilarityScoringStrategyTest extends TestCase
{
    private ArtistSimilarityScoringStrategy $strategy;
    private StringSimilarity $stringSimilarity;

    protected function setUp(): void
    {
        $this->stringSimilarity = $this->createMock(StringSimilarity::class);
        $this->strategy = new ArtistSimilarityScoringStrategy($this->stringSimilarity);
    }

    public function testCalculateScoreWithHighSimilarity(): void
    {
        $this->stringSimilarity->method('calculateSimilarity')
            ->with('Test Artist', 'Test Artist')
            ->willReturn(0.9);

        $score = $this->strategy->calculateScore('Test Artist', 'Test Artist');

        $this->assertEquals(4.5, $score); // 0.9 * 5.0
    }

    public function testCalculateScoreWithMediumSimilarity(): void
    {
        $this->stringSimilarity->method('calculateSimilarity')
            ->with('Test Artist', 'Test Artist')
            ->willReturn(0.6);

        $score = $this->strategy->calculateScore('Test Artist', 'Test Artist');

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithLowSimilarity(): void
    {
        $this->stringSimilarity->method('calculateSimilarity')
            ->with('Test Artist', 'Different Artist')
            ->willReturn(0.2);

        $score = $this->strategy->calculateScore('Test Artist', 'Different Artist');

        $this->assertEquals(-15.0, $score);
    }

    public function testCalculateScoreWithExactSimilarity(): void
    {
        $this->stringSimilarity->method('calculateSimilarity')
            ->with('Test Artist', 'Test Artist')
            ->willReturn(1.0);

        $score = $this->strategy->calculateScore('Test Artist', 'Test Artist');

        $this->assertEquals(5.0, $score); // 1.0 * 5.0
    }

    public function testCalculateScoreWithThresholdSimilarity(): void
    {
        $this->stringSimilarity->method('calculateSimilarity')
            ->with('Test Artist', 'Test Artist')
            ->willReturn(0.8);

        $score = $this->strategy->calculateScore('Test Artist', 'Test Artist');

        $this->assertEquals(0.0, $score); // 0.8 is not > 0.8, so returns 0.0
    }

    public function testGetScoreReasonWithHighSimilarity(): void
    {
        $this->stringSimilarity->method('calculateSimilarity')
            ->with('Test Artist', 'Test Artist')
            ->willReturn(0.9);

        $reason = $this->strategy->getScoreReason('Test Artist', 'Test Artist');

        $this->assertEquals('Artist similarity (0.9)', $reason);
    }

    public function testGetScoreReasonWithMediumSimilarity(): void
    {
        $this->stringSimilarity->method('calculateSimilarity')
            ->with('Test Artist', 'Test Artist')
            ->willReturn(0.6);

        $reason = $this->strategy->getScoreReason('Test Artist', 'Test Artist');

        $this->assertNull($reason);
    }

    public function testGetScoreReasonWithLowSimilarity(): void
    {
        $this->stringSimilarity->method('calculateSimilarity')
            ->with('Test Artist', 'Different Artist')
            ->willReturn(0.2);

        $reason = $this->strategy->getScoreReason('Test Artist', 'Different Artist');

        $this->assertEquals('Artist mismatch penalty (0.2)', $reason);
    }

    public function testGetScoreReasonWithThresholdSimilarity(): void
    {
        $this->stringSimilarity->method('calculateSimilarity')
            ->with('Test Artist', 'Test Artist')
            ->willReturn(0.8);

        $reason = $this->strategy->getScoreReason('Test Artist', 'Test Artist');

        $this->assertNull($reason); // 0.8 is not > 0.8, so returns null
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(60, ArtistSimilarityScoringStrategy::getPriority());
    }
}
