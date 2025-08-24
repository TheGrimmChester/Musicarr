<?php

declare(strict_types=1);

namespace App\Tests\Functional\TrackMatcher\Calculator;

use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Track;
use App\Entity\UnmatchedTrack;
use App\Repository\ConfigurationRepository;
use App\Tests\Functional\TestConfigurationService;
use App\TrackMatcher\Calculator\YearMatchCalculator;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class YearMatchCalculatorFunctionalTest extends KernelTestCase
{
    private YearMatchCalculator $yearMatchCalculator;
    private TestConfigurationService $testConfigService;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->testConfigService = new TestConfigurationService(
            self::getContainer()->get(EntityManagerInterface::class),
            self::getContainer()->get(ConfigurationRepository::class)
        );

        $this->yearMatchCalculator = new YearMatchCalculator(
            self::getContainer()->get(EntityManagerInterface::class)
        );
    }

    public function testCalculateScoreWithExactMatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 2020);

        // Set default year tolerance
        $this->testConfigService->setConfiguration('association.year_tolerance', 1);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->yearMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(100.0, $score, 'Exact year match should give expected score');
    }

    public function testCalculateScoreWithSmallDifference(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 2021);

        // Set year tolerance to 1
        $this->testConfigService->setConfiguration('association.year_tolerance', 1);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->yearMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(80.0, $score, 'Small year difference should give good score');
    }

    public function testCalculateScoreWithMediumDifference(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 2023);

        // Set year tolerance to 1
        $this->testConfigService->setConfiguration('association.year_tolerance', 1);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->yearMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(40.0, $score, 'Medium year difference should give medium score');
    }

    public function testCalculateScoreWithLargeDifference(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 2030);

        // Set year tolerance to 1
        $this->testConfigService->setConfiguration('association.year_tolerance', 1);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->yearMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(20.0, $score, 'Large year difference should give low score');
    }

    public function testCalculateScoreWithCustomTolerance(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 2023);

        // Set year tolerance to 5
        $this->testConfigService->setConfiguration('association.year_tolerance', 5);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->yearMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(40.0, $score, 'Year within custom tolerance should give medium score');
    }

    public function testCalculateScoreWithZeroTolerance(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 2021);

        // Set year tolerance to 0
        $this->testConfigService->setConfiguration('association.year_tolerance', 0);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->yearMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(80.0, $score, 'Year with zero tolerance should still give score for small difference');
    }

    public function testCalculateScoreWithMissingYear(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', null);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 2020);

        // Set year tolerance to 1
        $this->testConfigService->setConfiguration('association.year_tolerance', 1);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->yearMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Track without year should give zero score');
    }

    public function testCalculateScoreWithMissingUnmatchedYear(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', null);

        // Set year tolerance to 1
        $this->testConfigService->setConfiguration('association.year_tolerance', 1);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->yearMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Unmatched track without year should give zero score');
    }

    public function testCalculateScoreWithMissingAlbum(): void
    {
        $track = $this->createTrackWithoutAlbum('Test Song', 'Test Artist', 2020);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 2020);

        // Set year tolerance to 1
        $this->testConfigService->setConfiguration('association.year_tolerance', 1);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->yearMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Track without album should give zero score');
    }

    public function testCalculateScoreWithMissingArtist(): void
    {
        $track = $this->createTrackWithoutArtist('Test Song', 'Test Album', 2020);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 2020);

        // Set year tolerance to 1
        $this->testConfigService->setConfiguration('association.year_tolerance', 1);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->yearMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Track without artist should give zero score');
    }

    public function testGetScoreReasonWithExactMatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 2020);

        // Set year tolerance to 1
        $this->testConfigService->setConfiguration('association.year_tolerance', 1);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $reason = $this->yearMatchCalculator->getScoreReason($track, $unmatchedTrack, $pathInfo);

        $this->assertStringContainsString('Exact year match', $reason);
    }

    public function testGetScoreReasonWithDifference(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 2021);

        // Set year tolerance to 1
        $this->testConfigService->setConfiguration('association.year_tolerance', 1);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $reason = $this->yearMatchCalculator->getScoreReason($track, $unmatchedTrack, $pathInfo);

        $this->assertStringContainsString('Close year match', $reason);
    }

    public function testGetScoreReasonWithNoMatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 2030);

        // Set year tolerance to 1
        $this->testConfigService->setConfiguration('association.year_tolerance', 1);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $reason = $this->yearMatchCalculator->getScoreReason($track, $unmatchedTrack, $pathInfo);

        $this->assertStringContainsString('Year match', $reason);
    }

    public function testGetPriority(): void
    {
        $priority = YearMatchCalculator::getPriority();

        $this->assertEquals(40, $priority, 'Year calculator should have correct priority');
    }

    public function testGetType(): void
    {
        $type = $this->yearMatchCalculator->getType();

        $this->assertEquals('year', $type, 'Year calculator should have correct type');
    }

    public function testCalculateScoreWithVeryOldYear(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 1960);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 1961);

        // Set year tolerance to 1
        $this->testConfigService->setConfiguration('association.year_tolerance', 1);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->yearMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(80.0, $score, 'Old year with small difference should give good score');
    }

    public function testCalculateScoreWithVeryRecentYear(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2023);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 2024);

        // Set year tolerance to 1
        $this->testConfigService->setConfiguration('association.year_tolerance', 1);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->yearMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(80.0, $score, 'Recent year with small difference should give good score');
    }

    public function testCalculateScoreWithEdgeCaseTolerance(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 2022);

        // Set year tolerance to 2 (exactly at the edge)
        $this->testConfigService->setConfiguration('association.year_tolerance', 2);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->yearMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(60.0, $score, 'Year at tolerance edge should give medium score');
    }

    public function testCalculateScoreWithVeryHighTolerance(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 2050);

        // Set year tolerance to 50
        $this->testConfigService->setConfiguration('association.year_tolerance', 50);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->yearMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(20.0, $score, 'Year within very high tolerance should give low score');
    }

    public function testCalculateScoreWithPathInfoMismatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 2020);

        // Set year tolerance to 1
        $this->testConfigService->setConfiguration('association.year_tolerance', 1);

        // Path info doesn't match the track's actual artist/album
        $pathInfo = ['artist' => 'Different Artist', 'album' => 'Different Album'];

        $score = $this->yearMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(100.0, $score, 'Path info mismatch should not affect year matching');
    }

    public function testCalculateScoreWithEmptyPathInfo(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 2020);

        // Set year tolerance to 1
        $this->testConfigService->setConfiguration('association.year_tolerance', 1);

        $pathInfo = [];

        $score = $this->yearMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(100.0, $score, 'Empty path info should not affect year matching');
    }

    public function testCalculateScoreWithPartialPathInfo(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 2020);

        // Set year tolerance to 1
        $this->testConfigService->setConfiguration('association.year_tolerance', 1);

        // Only artist info, missing album
        $pathInfo = ['artist' => 'Test Artist'];

        $score = $this->yearMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(100.0, $score, 'Partial path info should not affect year matching');
    }

    public function testCalculateScoreWithZeroYear(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 0);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 1);

        // Set year tolerance to 1
        $this->testConfigService->setConfiguration('association.year_tolerance', 1);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->yearMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(80.0, $score, 'Zero year with small difference should give good score');
    }

    private function createTrack(?string $title, ?string $artistName, ?string $albumTitle, ?int $year): Track
    {
        $track = new Track();
        $track->setTitle($title);

        if ($artistName && $albumTitle) {
            $artist = new Artist();
            $artist->setName($artistName);

            $album = new Album();
            $album->setTitle($albumTitle);
            $album->setArtist($artist);
            if (null !== $year) {
                // Handle year 0 specially
                if (0 === $year) {
                    $album->setReleaseDate(new DateTime('0000-01-01'));
                } else {
                    $album->setReleaseDate(new DateTime("$year-01-01"));
                }
            }

            $track->setAlbum($album);
        }

        return $track;
    }

    private function createTrackWithoutAlbum(?string $title, ?string $artistName, ?int $year): Track
    {
        $track = new Track();
        $track->setTitle($title);

        if ($artistName) {
            $track->setArtistName($artistName);
        }

        return $track;
    }

    private function createTrackWithoutArtist(?string $title, ?string $albumTitle, ?int $year): Track
    {
        $track = new Track();
        $track->setTitle($title);

        if ($albumTitle) {
            $album = new Album();
            $album->setTitle($albumTitle);
            if (null !== $year) {
                // Handle year 0 specially
                if (0 === $year) {
                    $album->setReleaseDate(new DateTime('0000-01-01'));
                } else {
                    $album->setReleaseDate(new DateTime("$year-01-01"));
                }
            }
            $track->setAlbum($album);
        }

        return $track;
    }

    private function createUnmatchedTrack(?string $title, ?string $artistName, ?string $albumTitle, ?int $year): UnmatchedTrack
    {
        $unmatchedTrack = new UnmatchedTrack();
        $unmatchedTrack->setTitle($title);
        $unmatchedTrack->setArtist($artistName);
        $unmatchedTrack->setAlbum($albumTitle);
        $unmatchedTrack->setYear($year);

        return $unmatchedTrack;
    }
}
