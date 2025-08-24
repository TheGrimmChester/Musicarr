<?php

declare(strict_types=1);

namespace App\Tests\Unit\TrackMatcher\Calculator\Strategy;

use App\TrackMatcher\Calculator\Strategy\VinylTrackNumberScoringStrategy;
use PHPUnit\Framework\TestCase;

class VinylTrackNumberScoringStrategyTest extends TestCase
{
    private VinylTrackNumberScoringStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new VinylTrackNumberScoringStrategy();
    }

    public function testCalculateScoreWithExactMatch(): void
    {
        $score = $this->strategy->calculateScore('A1', 'A1');

        $this->assertEquals(15.0, $score);
    }

    public function testCalculateScoreWithCloseMatch(): void
    {
        $score = $this->strategy->calculateScore('A1', 'A2');

        $this->assertEquals(10.0, $score);
    }

    public function testCalculateScoreWithReasonableMatch(): void
    {
        $score = $this->strategy->calculateScore('A1', 'A4');

        $this->assertEquals(5.0, $score);
    }

    public function testCalculateScoreWithFarMatch(): void
    {
        $score = $this->strategy->calculateScore('A1', 'A7');

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithDifferentSides(): void
    {
        $score = $this->strategy->calculateScore('A1', 'B1');

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithNonVinylFormat(): void
    {
        $score = $this->strategy->calculateScore('1', '2');

        $this->assertEquals(0.0, $score);
    }

    public function testGetScoreReasonWithExactMatch(): void
    {
        $reason = $this->strategy->getScoreReason('A1', 'A1');

        $this->assertEquals('Track number match on same side', $reason);
    }

    public function testGetScoreReasonWithCloseMatch(): void
    {
        $reason = $this->strategy->getScoreReason('A1', 'A2');

        $this->assertEquals('Close track number match on same side (difference: 1)', $reason);
    }

    public function testGetScoreReasonWithReasonableMatch(): void
    {
        $reason = $this->strategy->getScoreReason('A1', 'A4');

        $this->assertEquals('Reasonable track number match on same side (difference: 3)', $reason);
    }

    public function testGetScoreReasonWithFarMatch(): void
    {
        $reason = $this->strategy->getScoreReason('A1', 'A7');

        $this->assertNull($reason);
    }

    public function testGetScoreReasonWithDifferentSides(): void
    {
        $reason = $this->strategy->getScoreReason('A1', 'B1');

        $this->assertNull($reason);
    }

    public function testGetScoreReasonWithNonVinylFormat(): void
    {
        $reason = $this->strategy->getScoreReason('1', '2');

        $this->assertNull($reason);
    }
}
