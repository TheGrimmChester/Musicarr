<?php

declare(strict_types=1);

namespace App\Tests\Unit\TrackMatcher\Calculator\Strategy;

use App\TrackMatcher\Calculator\Strategy\ArtistPathMatchScoringStrategy;
use PHPUnit\Framework\TestCase;

class ArtistPathMatchScoringStrategyTest extends TestCase
{
    private ArtistPathMatchScoringStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new ArtistPathMatchScoringStrategy();
    }

    public function testCalculateScoreWithPathMatch(): void
    {
        $pathInfo = ['artist' => 'Test Artist'];

        $score = $this->strategy->calculateScore('Test Artist', 'Unmatched Artist', $pathInfo);

        $this->assertEquals(20.0, $score);
    }

    public function testCalculateScoreWithPathMatchCaseInsensitive(): void
    {
        $pathInfo = ['artist' => 'test artist'];

        $score = $this->strategy->calculateScore('Test Artist', 'Unmatched Artist', $pathInfo);

        $this->assertEquals(20.0, $score);
    }

    public function testCalculateScoreWithNoPathMatch(): void
    {
        $pathInfo = ['artist' => 'Different Artist'];

        $score = $this->strategy->calculateScore('Test Artist', 'Unmatched Artist', $pathInfo);

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithNoArtistInPathInfo(): void
    {
        $pathInfo = ['album' => 'Test Album'];

        $score = $this->strategy->calculateScore('Test Artist', 'Unmatched Artist', $pathInfo);

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithEmptyPathInfo(): void
    {
        $pathInfo = [];

        $score = $this->strategy->calculateScore('Test Artist', 'Unmatched Artist', $pathInfo);

        $this->assertEquals(0.0, $score);
    }

    public function testGetScoreReasonWithPathMatch(): void
    {
        $pathInfo = ['artist' => 'Test Artist'];

        $reason = $this->strategy->getScoreReason('Test Artist', 'Unmatched Artist', $pathInfo);

        $this->assertEquals('Directory artist match', $reason);
    }

    public function testGetScoreReasonWithPathMatchCaseInsensitive(): void
    {
        $pathInfo = ['artist' => 'test artist'];

        $reason = $this->strategy->getScoreReason('Test Artist', 'Unmatched Artist', $pathInfo);

        $this->assertEquals('Directory artist match', $reason);
    }

    public function testGetScoreReasonWithNoPathMatch(): void
    {
        $pathInfo = ['artist' => 'Different Artist'];

        $reason = $this->strategy->getScoreReason('Test Artist', 'Unmatched Artist', $pathInfo);

        $this->assertNull($reason);
    }

    public function testGetScoreReasonWithNoArtistInPathInfo(): void
    {
        $pathInfo = ['album' => 'Test Album'];

        $reason = $this->strategy->getScoreReason('Test Artist', 'Unmatched Artist', $pathInfo);

        $this->assertNull($reason);
    }

    public function testGetScoreReasonWithEmptyPathInfo(): void
    {
        $pathInfo = [];

        $reason = $this->strategy->getScoreReason('Test Artist', 'Unmatched Artist', $pathInfo);

        $this->assertNull($reason);
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(80, ArtistPathMatchScoringStrategy::getPriority());
    }
}
