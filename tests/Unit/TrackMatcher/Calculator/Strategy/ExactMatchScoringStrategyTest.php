<?php

declare(strict_types=1);

namespace App\Tests\Unit\TrackMatcher\Calculator\Strategy;

use App\Configuration\Config\ConfigurationFactory;
use App\TrackMatcher\Calculator\Strategy\ExactMatchScoringStrategy;
use PHPUnit\Framework\TestCase;

class ExactMatchScoringStrategyTest extends TestCase
{
    private ExactMatchScoringStrategy $strategy;
    private ConfigurationFactory $configurationFactory;

    protected function setUp(): void
    {
        $this->configurationFactory = $this->createMock(ConfigurationFactory::class);
        $this->strategy = new ExactMatchScoringStrategy($this->configurationFactory);
    }

    public function testCalculateScoreWithExactMatch(): void
    {
        $score = $this->strategy->calculateScore('Test Album', 'Test Album');

        $this->assertEquals(25.0, $score);
    }

    public function testCalculateScoreWithExactMatchCaseInsensitive(): void
    {
        $score = $this->strategy->calculateScore('Test Album', 'test album');

        $this->assertEquals(25.0, $score);
    }

    public function testCalculateScoreWithNoMatchAndExactRequired(): void
    {
        $this->configurationFactory->method('getDefaultConfiguration')
            ->with('association.')
            ->willReturn(['exact_artist_match' => true]);

        $score = $this->strategy->calculateScore('Test Album', 'Different Album');

        $this->assertEquals(-50.0, $score);
    }

    public function testCalculateScoreWithNoMatchAndExactNotRequired(): void
    {
        $this->configurationFactory->method('getDefaultConfiguration')
            ->with('association.')
            ->willReturn(['exact_artist_match' => false]);

        $score = $this->strategy->calculateScore('Test Album', 'Different Album');

        $this->assertEquals(0.0, $score);
    }

    public function testGetScoreReasonWithExactMatch(): void
    {
        $reason = $this->strategy->getScoreReason('Test Album', 'Test Album');

        $this->assertEquals('Exact artist match', $reason);
    }

    public function testGetScoreReasonWithExactMatchCaseInsensitive(): void
    {
        $reason = $this->strategy->getScoreReason('Test Album', 'test album');

        $this->assertEquals('Exact artist match', $reason);
    }

    public function testGetScoreReasonWithNoMatchAndExactRequired(): void
    {
        $this->configurationFactory->method('getDefaultConfiguration')
            ->with('association.')
            ->willReturn(['exact_artist_match' => true]);

        $reason = $this->strategy->getScoreReason('Test Album', 'Different Album');

        $this->assertEquals('Artist mismatch (exact match required)', $reason);
    }

    public function testGetScoreReasonWithNoMatchAndExactNotRequired(): void
    {
        $this->configurationFactory->method('getDefaultConfiguration')
            ->with('association.')
            ->willReturn(['exact_artist_match' => false]);

        $reason = $this->strategy->getScoreReason('Test Album', 'Different Album');

        $this->assertNull($reason);
    }
}
