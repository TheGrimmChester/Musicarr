<?php

declare(strict_types=1);

namespace App\Tests\Unit\TrackMatcher\Calculator\Strategy;

use App\TrackMatcher\Calculator\Strategy\PathMatchScoringStrategy;
use PHPUnit\Framework\TestCase;

class PathMatchScoringStrategyTest extends TestCase
{
    private PathMatchScoringStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new PathMatchScoringStrategy();
    }

    public function testCalculateScoreWithPathMatch(): void
    {
        $pathInfo = ['album' => 'Test Album'];

        $score = $this->strategy->calculateScore('Test Album', 'Different Album', $pathInfo);

        $this->assertEquals(15.0, $score);
    }

    public function testCalculateScoreWithPathMatchCaseInsensitive(): void
    {
        $pathInfo = ['album' => 'test album'];

        $score = $this->strategy->calculateScore('Test Album', 'Different Album', $pathInfo);

        $this->assertEquals(15.0, $score);
    }

    public function testCalculateScoreWithNoPathMatch(): void
    {
        $pathInfo = ['album' => 'Different Album'];

        $score = $this->strategy->calculateScore('Test Album', 'Different Album', $pathInfo);

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithNoPathInfo(): void
    {
        $score = $this->strategy->calculateScore('Test Album', 'Different Album', []);

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithNoAlbumInPathInfo(): void
    {
        $pathInfo = ['artist' => 'Test Artist'];

        $score = $this->strategy->calculateScore('Test Album', 'Different Album', $pathInfo);

        $this->assertEquals(0.0, $score);
    }

    public function testGetScoreReasonWithPathMatch(): void
    {
        $pathInfo = ['album' => 'Test Album'];

        $reason = $this->strategy->getScoreReason('Test Album', 'Different Album', $pathInfo);

        $this->assertEquals('Directory album match', $reason);
    }

    public function testGetScoreReasonWithPathMatchCaseInsensitive(): void
    {
        $pathInfo = ['album' => 'test album'];

        $reason = $this->strategy->getScoreReason('Test Album', 'Different Album', $pathInfo);

        $this->assertEquals('Directory album match', $reason);
    }

    public function testGetScoreReasonWithNoPathMatch(): void
    {
        $pathInfo = ['album' => 'Different Album'];

        $reason = $this->strategy->getScoreReason('Test Album', 'Different Album', $pathInfo);

        $this->assertNull($reason);
    }

    public function testGetScoreReasonWithNoPathInfo(): void
    {
        $reason = $this->strategy->getScoreReason('Test Album', 'Different Album', []);

        $this->assertNull($reason);
    }
}
