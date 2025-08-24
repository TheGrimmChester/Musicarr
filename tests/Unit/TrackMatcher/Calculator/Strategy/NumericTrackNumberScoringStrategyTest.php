<?php

declare(strict_types=1);

namespace App\Tests\Unit\TrackMatcher\Calculator\Strategy;

use App\TrackMatcher\Calculator\Strategy\NumericTrackNumberScoringStrategy;
use PHPUnit\Framework\TestCase;

class NumericTrackNumberScoringStrategyTest extends TestCase
{
    private NumericTrackNumberScoringStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new NumericTrackNumberScoringStrategy();
    }

    public function testCalculateScoreWithSmallDifference(): void
    {
        $score = $this->strategy->calculateScore('1', '2');

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithLargeDifference(): void
    {
        $score = $this->strategy->calculateScore('1', '10');

        $this->assertEquals(-5.0, $score);
    }

    public function testCalculateScoreWithExactMatch(): void
    {
        $score = $this->strategy->calculateScore('5', '5');

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithZeroTrackNumber(): void
    {
        $score = $this->strategy->calculateScore('0', '5');

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithNegativeTrackNumber(): void
    {
        $score = $this->strategy->calculateScore('-1', '5');

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithNonNumericStrings(): void
    {
        $score = $this->strategy->calculateScore('A1', 'B2');

        $this->assertEquals(0.0, $score);
    }

    public function testGetScoreReasonWithSmallDifference(): void
    {
        $reason = $this->strategy->getScoreReason('1', '2');

        $this->assertNull($reason);
    }

    public function testGetScoreReasonWithLargeDifference(): void
    {
        $reason = $this->strategy->getScoreReason('1', '10');

        $this->assertEquals('Track number mismatch (difference: 9)', $reason);
    }

    public function testGetScoreReasonWithExactMatch(): void
    {
        $reason = $this->strategy->getScoreReason('5', '5');

        $this->assertNull($reason);
    }

    public function testGetScoreReasonWithZeroTrackNumber(): void
    {
        $reason = $this->strategy->getScoreReason('0', '5');

        $this->assertNull($reason);
    }

    public function testGetScoreReasonWithNegativeTrackNumber(): void
    {
        $reason = $this->strategy->getScoreReason('-1', '5');

        $this->assertNull($reason);
    }

    public function testGetScoreReasonWithNonNumericStrings(): void
    {
        $reason = $this->strategy->getScoreReason('A1', 'B2');

        $this->assertNull($reason);
    }
}
