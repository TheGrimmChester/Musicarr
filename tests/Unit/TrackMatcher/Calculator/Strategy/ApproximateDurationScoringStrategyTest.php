<?php

declare(strict_types=1);

namespace App\Tests\Unit\TrackMatcher\Calculator\Strategy;

use App\TrackMatcher\Calculator\Strategy\ApproximateDurationScoringStrategy;
use PHPUnit\Framework\TestCase;

class ApproximateDurationScoringStrategyTest extends TestCase
{
    private ApproximateDurationScoringStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new ApproximateDurationScoringStrategy();
    }

    public function testCalculateScoreWith1SecondDifference(): void
    {
        $score = $this->strategy->calculateScore(180, 181);

        $this->assertEquals(90.0, $score);
    }

    public function testCalculateScoreWith2SecondDifference(): void
    {
        $score = $this->strategy->calculateScore(180, 182);

        $this->assertEquals(80.0, $score);
    }

    public function testCalculateScoreWith3SecondDifference(): void
    {
        $score = $this->strategy->calculateScore(180, 183);

        $this->assertEquals(80.0, $score);
    }

    public function testCalculateScoreWith4SecondDifference(): void
    {
        $score = $this->strategy->calculateScore(180, 184);

        $this->assertEquals(70.0, $score);
    }

    public function testCalculateScoreWith5SecondDifference(): void
    {
        $score = $this->strategy->calculateScore(180, 185);

        $this->assertEquals(70.0, $score);
    }

    public function testCalculateScoreWith6SecondDifference(): void
    {
        $score = $this->strategy->calculateScore(180, 186);

        $this->assertEquals(50.0, $score);
    }

    public function testCalculateScoreWith10SecondDifference(): void
    {
        $score = $this->strategy->calculateScore(180, 190);

        $this->assertEquals(50.0, $score);
    }

    public function testCalculateScoreWith11SecondDifference(): void
    {
        $score = $this->strategy->calculateScore(180, 191);

        $this->assertEquals(30.0, $score);
    }

    public function testCalculateScoreWith30SecondDifference(): void
    {
        $score = $this->strategy->calculateScore(180, 210);

        $this->assertEquals(30.0, $score);
    }

    public function testCalculateScoreWith31SecondDifference(): void
    {
        $score = $this->strategy->calculateScore(180, 211);

        $this->assertEquals(10.0, $score);
    }

    public function testGetScoreReasonWith1SecondDifference(): void
    {
        $reason = $this->strategy->getScoreReason(180, 181);

        $this->assertEquals('Close duration match: 181s vs 180s (1s difference)', $reason);
    }

    public function testGetScoreReasonWith2SecondDifference(): void
    {
        $reason = $this->strategy->getScoreReason(180, 182);

        $this->assertEquals('Duration match: 182s vs 180s (2s difference)', $reason);
    }

    public function testGetScoreReasonWith5SecondDifference(): void
    {
        $reason = $this->strategy->getScoreReason(180, 185);

        $this->assertEquals('Duration match: 185s vs 180s (5s difference)', $reason);
    }

    public function testGetScoreReasonWith10SecondDifference(): void
    {
        $reason = $this->strategy->getScoreReason(180, 190);

        $this->assertEquals('Duration match: 190s vs 180s (10s difference)', $reason);
    }

    public function testGetScoreReasonWith30SecondDifference(): void
    {
        $reason = $this->strategy->getScoreReason(180, 210);

        $this->assertEquals('Duration match: 210s vs 180s (30s difference)', $reason);
    }

    public function testGetScoreReasonWith31SecondDifference(): void
    {
        $reason = $this->strategy->getScoreReason(180, 211);

        $this->assertEquals('Duration match: 211s vs 180s (31s difference)', $reason);
    }
}
