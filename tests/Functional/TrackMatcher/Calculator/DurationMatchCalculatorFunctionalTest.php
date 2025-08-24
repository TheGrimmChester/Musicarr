<?php

declare(strict_types=1);

namespace App\Tests\Functional\TrackMatcher\Calculator;

use App\Configuration\Config\ConfigurationFactory;
use App\Configuration\ConfigurationService;
use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Track;
use App\Entity\UnmatchedTrack;
use App\Repository\ConfigurationRepository;
use App\Tests\Functional\TestConfigurationService;
use App\TrackMatcher\Calculator\DurationMatchCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DurationMatchCalculatorFunctionalTest extends KernelTestCase
{
    private DurationMatchCalculator $durationMatchCalculator;
    private ConfigurationService $configurationService;
    private ConfigurationFactory $configurationFactory;
    private TestConfigurationService $testConfigService;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->configurationService = self::getContainer()->get(ConfigurationService::class);
        $this->configurationFactory = self::getContainer()->get(ConfigurationFactory::class);
        $this->testConfigService = new TestConfigurationService(
            self::getContainer()->get(EntityManagerInterface::class),
            self::getContainer()->get(ConfigurationRepository::class)
        );

        $this->durationMatchCalculator = new DurationMatchCalculator(
            self::getContainer()->get(EntityManagerInterface::class)
        );
    }

    public function testCalculateScoreWithExactMatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 180);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 180);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->durationMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(100.0, $score, 'Exact duration match should give perfect score');
    }

    public function testCalculateScoreWithSmallDifference(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 180);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 182);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->durationMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(80.0, $score, '2 second difference should give 80 points');
    }

    public function testCalculateScoreWithMediumDifference(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 180);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 188);

        // Ensure exact duration match is not required
        $this->testConfigService->setConfiguration('association.exact_duration_match', false);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->durationMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(50.0, $score, 'Medium duration difference should give expected score');
    }

    public function testCalculateScoreWithLargeDifference(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 180);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 200);

        // Ensure exact duration match is not required
        $this->testConfigService->setConfiguration('association.exact_duration_match', false);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->durationMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(30.0, $score, 'Large duration difference should give expected score');
    }

    public function testCalculateScoreWithCustomTolerance(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 180);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 190);

        // Ensure exact duration match is not required
        $this->testConfigService->setConfiguration('association.exact_duration_match', false);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->durationMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(50.0, $score, 'Duration within custom tolerance should give expected score');
    }

    public function testCalculateScoreWithZeroTolerance(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 180);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 181);

        // Set exact duration match required
        $this->testConfigService->setConfiguration('association.exact_duration_match', true);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->durationMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Duration with exact match required should give zero score for any difference');
    }

    public function testCalculateScoreWithMissingDuration(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', null);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 180);

        // Ensure exact duration match is not required
        $this->testConfigService->setConfiguration('association.exact_duration_match', false);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->durationMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Track without duration should give zero score');
    }

    public function testCalculateScoreWithMissingUnmatchedDuration(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 180);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', null);

        // Ensure exact duration match is not required
        $this->testConfigService->setConfiguration('association.exact_duration_match', false);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->durationMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Unmatched track without duration should give zero score');
    }

    public function testGetScoreReasonWithExactMatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 180);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 180);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $reason = $this->durationMatchCalculator->getScoreReason($track, $unmatchedTrack, $pathInfo);

        $this->assertStringContainsString('Exact duration match', $reason);
    }

    public function testGetScoreReasonWithDifference(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 180);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 185);

        // Ensure exact duration match is not required
        $this->testConfigService->setConfiguration('association.exact_duration_match', false);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $reason = $this->durationMatchCalculator->getScoreReason($track, $unmatchedTrack, $pathInfo);

        $this->assertStringContainsString('Duration match', $reason);
    }

    public function testGetScoreReasonWithNoMatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 180);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 200);

        // Ensure exact duration match is not required
        $this->testConfigService->setConfiguration('association.exact_duration_match', false);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $reason = $this->durationMatchCalculator->getScoreReason($track, $unmatchedTrack, $pathInfo);

        $this->assertStringContainsString('Duration match', $reason);
    }

    public function testGetPriority(): void
    {
        $priority = DurationMatchCalculator::getPriority();

        $this->assertEquals(50, $priority, 'Duration calculator should have correct priority');
    }

    public function testGetType(): void
    {
        $type = $this->durationMatchCalculator->getType();

        $this->assertEquals('duration', $type, 'Duration calculator should have correct type');
    }

    public function testCalculateScoreWithVeryShortDuration(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 30);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 35);

        // Ensure exact duration match is not required
        $this->testConfigService->setConfiguration('association.exact_duration_match', false);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->durationMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(70.0, $score, 'Very short duration with small difference should give expected score');
    }

    public function testCalculateScoreWithVeryLongDuration(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 600);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 610);

        // Ensure exact duration match is not required
        $this->testConfigService->setConfiguration('association.exact_duration_match', false);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->durationMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(50.0, $score, 'Very long duration with small difference should give expected score');
    }

    private function createTrack(?string $title, ?string $artistName, ?string $albumTitle, ?int $duration): Track
    {
        $track = new Track();
        $track->setTitle($title);
        $track->setDuration($duration);

        if ($artistName && $albumTitle) {
            $artist = new Artist();
            $artist->setName($artistName);

            $album = new Album();
            $album->setTitle($albumTitle);
            $album->setArtist($artist);

            $track->setAlbum($album);
        }

        return $track;
    }

    private function createUnmatchedTrack(?string $title, ?string $artistName, ?string $albumTitle, ?int $duration): UnmatchedTrack
    {
        $unmatchedTrack = new UnmatchedTrack();
        $unmatchedTrack->setTitle($title);
        $unmatchedTrack->setArtist($artistName);
        $unmatchedTrack->setAlbum($albumTitle);
        $unmatchedTrack->setDuration($duration);

        return $unmatchedTrack;
    }
}
