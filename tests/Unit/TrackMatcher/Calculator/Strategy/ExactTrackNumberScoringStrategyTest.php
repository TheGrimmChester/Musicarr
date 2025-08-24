<?php

declare(strict_types=1);

namespace App\Tests\Unit\TrackMatcher\Calculator\Strategy;

use App\TrackMatcher\Calculator\Strategy\ExactTrackNumberScoringStrategy;
use PHPUnit\Framework\TestCase;

class ExactTrackNumberScoringStrategyTest extends TestCase
{
    private ExactTrackNumberScoringStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new ExactTrackNumberScoringStrategy();
    }

    public function testCalculateScoreWithExactMatch(): void
    {
        $score = $this->strategy->calculateScore('1', '1');

        $this->assertEquals(15.0, $score);
    }

    public function testCalculateScoreWithExactMatchString(): void
    {
        $score = $this->strategy->calculateScore('01', '01');

        $this->assertEquals(15.0, $score);
    }

    public function testCalculateScoreWithNoMatch(): void
    {
        $score = $this->strategy->calculateScore('1', '2');

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithDifferentFormats(): void
    {
        $score = $this->strategy->calculateScore('1', '01');

        $this->assertEquals(0.0, $score);
    }

    public function testGetScoreReasonWithExactMatch(): void
    {
        $reason = $this->strategy->getScoreReason('1', '1');

        $this->assertEquals('Track number match', $reason);
    }

    public function testGetScoreReasonWithExactMatchString(): void
    {
        $reason = $this->strategy->getScoreReason('01', '01');

        $this->assertEquals('Track number match', $reason);
    }

    public function testGetScoreReasonWithNoMatch(): void
    {
        $reason = $this->strategy->getScoreReason('1', '2');

        $this->assertNull($reason);
    }

    public function testGetScoreReasonWithDifferentFormats(): void
    {
        $reason = $this->strategy->getScoreReason('1', '01');

        $this->assertNull($reason);
    }
}
