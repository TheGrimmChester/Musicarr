<?php

declare(strict_types=1);

namespace App\Tests\Unit\TrackMatcher\Calculator\Strategy;

use App\Configuration\Config\ConfigurationFactory;
use App\TrackMatcher\Calculator\Strategy\ExactArtistMatchScoringStrategy;
use PHPUnit\Framework\TestCase;

class ExactArtistMatchScoringStrategyTest extends TestCase
{
    private ExactArtistMatchScoringStrategy $strategy;
    private ConfigurationFactory $configurationFactory;

    protected function setUp(): void
    {
        $this->configurationFactory = $this->createMock(ConfigurationFactory::class);
        $this->strategy = new ExactArtistMatchScoringStrategy($this->configurationFactory);
    }

    public function testCalculateScoreWithExactMatch(): void
    {
        $score = $this->strategy->calculateScore('Test Artist', 'Test Artist');

        $this->assertEquals(30.0, $score);
    }

    public function testCalculateScoreWithExactMatchCaseInsensitive(): void
    {
        $score = $this->strategy->calculateScore('Test Artist', 'test artist');

        $this->assertEquals(30.0, $score);
    }

    public function testCalculateScoreWithNoMatchAndExactRequired(): void
    {
        $this->configurationFactory->method('getDefaultConfiguration')
            ->with('association.')
            ->willReturn(['exact_artist_match' => true]);

        $score = $this->strategy->calculateScore('Test Artist', 'Different Artist');

        $this->assertEquals(-50.0, $score);
    }

    public function testCalculateScoreWithNoMatchAndExactNotRequired(): void
    {
        $this->configurationFactory->method('getDefaultConfiguration')
            ->with('association.')
            ->willReturn(['exact_artist_match' => false]);

        $score = $this->strategy->calculateScore('Test Artist', 'Different Artist');

        $this->assertEquals(0.0, $score);
    }

    public function testGetScoreReasonWithExactMatch(): void
    {
        $reason = $this->strategy->getScoreReason('Test Artist', 'Test Artist');

        $this->assertEquals('Artist match', $reason);
    }

    public function testGetScoreReasonWithExactMatchCaseInsensitive(): void
    {
        $reason = $this->strategy->getScoreReason('Test Artist', 'test artist');

        $this->assertEquals('Artist match', $reason);
    }

    public function testGetScoreReasonWithNoMatchAndExactRequired(): void
    {
        $this->configurationFactory->method('getDefaultConfiguration')
            ->with('association.')
            ->willReturn(['exact_artist_match' => true]);

        $reason = $this->strategy->getScoreReason('Test Artist', 'Different Artist');

        $this->assertEquals('Artist mismatch (exact match required)', $reason);
    }

    public function testGetScoreReasonWithNoMatchAndExactNotRequired(): void
    {
        $this->configurationFactory->method('getDefaultConfiguration')
            ->with('association.')
            ->willReturn(['exact_artist_match' => false]);

        $reason = $this->strategy->getScoreReason('Test Artist', 'Different Artist');

        $this->assertNull($reason);
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(100, ExactArtistMatchScoringStrategy::getPriority());
    }
}
