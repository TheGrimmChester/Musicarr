<?php

declare(strict_types=1);

namespace App\Tests\Unit\TrackMatcher\Calculator\Strategy;

use App\Entity\Configuration;
use App\Repository\ConfigurationRepository;
use App\TrackMatcher\Calculator\Strategy\ExactDurationScoringStrategy;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ExactDurationScoringStrategyTest extends TestCase
{
    private ExactDurationScoringStrategy $strategy;
    private EntityManagerInterface $entityManager;
    private ConfigurationRepository $configRepository;

    protected function setUp(): void
    {
        $this->configRepository = $this->createMock(ConfigurationRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->entityManager->method('getRepository')
            ->with(Configuration::class)
            ->willReturn($this->configRepository);

        $this->strategy = new ExactDurationScoringStrategy($this->entityManager);
    }

    public function testCalculateScoreWithExactMatch(): void
    {
        $score = $this->strategy->calculateScore(180, 180);

        $this->assertEquals(100.0, $score);
    }

    public function testCalculateScoreWithNoMatchAndExactRequired(): void
    {
        $config = $this->createMock(Configuration::class);
        $config->method('getParsedValue')->willReturn(true);

        $this->configRepository->method('findByKey')
            ->with('association.exact_duration_match')
            ->willReturn($config);

        $score = $this->strategy->calculateScore(180, 185);

        $this->assertEquals(-1.0, $score);
    }

    public function testCalculateScoreWithNoMatchAndExactNotRequired(): void
    {
        $this->configRepository->method('findByKey')
            ->with('association.exact_duration_match')
            ->willReturn(null);

        $score = $this->strategy->calculateScore(180, 185);

        $this->assertEquals(0.0, $score);
    }

    public function testGetScoreReasonWithExactMatch(): void
    {
        $reason = $this->strategy->getScoreReason(180, 180);

        $this->assertEquals('Exact duration match', $reason);
    }

    public function testGetScoreReasonWithNoMatchAndExactRequired(): void
    {
        $config = $this->createMock(Configuration::class);
        $config->method('getParsedValue')->willReturn(true);

        $this->configRepository->method('findByKey')
            ->with('association.exact_duration_match')
            ->willReturn($config);

        $reason = $this->strategy->getScoreReason(180, 185);

        $this->assertEquals('Duration mismatch (exact match required)', $reason);
    }

    public function testGetScoreReasonWithNoMatchAndExactNotRequired(): void
    {
        $this->configRepository->method('findByKey')
            ->with('association.exact_duration_match')
            ->willReturn(null);

        $reason = $this->strategy->getScoreReason(180, 185);

        $this->assertNull($reason);
    }
}
