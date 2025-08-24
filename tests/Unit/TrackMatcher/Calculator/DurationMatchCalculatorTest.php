<?php

declare(strict_types=1);

namespace App\Tests\Unit\TrackMatcher\Calculator;

use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Configuration;
use App\Entity\Track;
use App\Entity\UnmatchedTrack;
use App\Repository\ConfigurationRepository;
use App\TrackMatcher\Calculator\DurationMatchCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class DurationMatchCalculatorTest extends TestCase
{
    private DurationMatchCalculator $calculator;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->calculator = new DurationMatchCalculator($this->entityManager);
    }

    private function setupRepositoryMocks(bool $exactDurationRequired = false): void
    {
        $configRepo = $this->createMock(ConfigurationRepository::class);

        if ($exactDurationRequired) {
            $config = $this->createMock(Configuration::class);
            $config->method('getParsedValue')->willReturn(true);
            $configRepo->method('findByKey')
                ->with('association.exact_duration_match')
                ->willReturn($config);
        } else {
            $configRepo->method('findByKey')
                ->with('association.exact_duration_match')
                ->willReturn(null);
        }

        $this->entityManager->method('getRepository')
            ->willReturnMap([
                [Configuration::class, $configRepo],
                [Track::class, $this->createMock(EntityRepository::class)],
                [UnmatchedTrack::class, $this->createMock(EntityRepository::class)],
            ]);
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(50, DurationMatchCalculator::getPriority());
    }

    public function testGetType(): void
    {
        $this->assertEquals('duration', $this->calculator->getType());
    }

    public function testCalculateScoreWithExactMatch(): void
    {
        $this->setupRepositoryMocks();

        $track = $this->createTrack(180);
        $unmatchedTrack = $this->createUnmatchedTrack(180);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);
        $this->assertEquals(100.0, $score);
    }

    public function testCalculateScoreWithExactDurationRequired(): void
    {
        $this->setupRepositoryMocks(true); // Exact duration required

        $track = $this->createTrack(180);
        $unmatchedTrack = $this->createUnmatchedTrack(181);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);
        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithOneSecondDifference(): void
    {
        $this->setupRepositoryMocks();

        $track = $this->createTrack(180);
        $unmatchedTrack = $this->createUnmatchedTrack(181);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);
        $this->assertEquals(90.0, $score);
    }

    public function testCalculateScoreWithNoDurationInfo(): void
    {
        $this->setupRepositoryMocks();

        $track = $this->createTrack(null);
        $unmatchedTrack = $this->createUnmatchedTrack(180);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);
        $this->assertEquals(0.0, $score);
    }

    public function testGetScoreReasonWithExactMatch(): void
    {
        $this->setupRepositoryMocks();

        $track = $this->createTrack(180);
        $unmatchedTrack = $this->createUnmatchedTrack(180);

        $reason = $this->calculator->getScoreReason($track, $unmatchedTrack, []);
        $this->assertEquals('Exact duration match', $reason);
    }

    public function testGetScoreReasonWithExactDurationRequired(): void
    {
        $this->setupRepositoryMocks(true); // Exact duration required

        $track = $this->createTrack(180);
        $unmatchedTrack = $this->createUnmatchedTrack(181);

        $reason = $this->calculator->getScoreReason($track, $unmatchedTrack, []);
        $this->assertEquals('Duration mismatch (exact match required)', $reason);
    }

    public function testGetScoreReasonWithApproximateMatch(): void
    {
        $this->setupRepositoryMocks(false); // Exact duration not required

        $track = $this->createTrack(180);
        $unmatchedTrack = $this->createUnmatchedTrack(181);

        $reason = $this->calculator->getScoreReason($track, $unmatchedTrack, []);
        $this->assertEquals('Close duration match: 181s vs 180s (1s difference)', $reason);
    }

    private function createTrack(?int $duration): Track
    {
        $artist = new Artist();
        $artist->setName('Test Artist');

        $album = new Album();
        $album->setTitle('Test Album');
        $album->setArtist($artist);

        $track = new Track();
        $track->setTitle('Test Track');
        $track->setAlbum($album);
        $track->setDuration($duration);

        return $track;
    }

    private function createUnmatchedTrack(?int $duration): UnmatchedTrack
    {
        $unmatchedTrack = new UnmatchedTrack();
        $unmatchedTrack->setFilePath('/test/path.mp3');
        $unmatchedTrack->setTitle('Test Track');
        $unmatchedTrack->setDuration($duration);

        return $unmatchedTrack;
    }
}
