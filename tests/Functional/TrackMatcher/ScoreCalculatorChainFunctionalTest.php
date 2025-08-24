<?php

declare(strict_types=1);

namespace App\Tests\Functional\TrackMatcher;

use App\Configuration\Config\ConfigurationFactory;
use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Track;
use App\Entity\UnmatchedTrack;
use App\Repository\ConfigurationRepository;
use App\StringSimilarity;
use App\Tests\Functional\TestConfigurationService;
use App\TrackMatcher\Calculator\AlbumMatchCalculator;
use App\TrackMatcher\Calculator\ArtistMatchCalculator;
use App\TrackMatcher\Calculator\DurationMatchCalculator;
use App\TrackMatcher\Calculator\ScoreCalculatorChain;
use App\TrackMatcher\Calculator\Strategy\ArtistPathMatchScoringStrategy;
use App\TrackMatcher\Calculator\Strategy\ArtistSimilarityScoringStrategy;
use App\TrackMatcher\Calculator\Strategy\ExactAlbumMatchScoringStrategy;
use App\TrackMatcher\Calculator\Strategy\ExactArtistMatchScoringStrategy;
use App\TrackMatcher\Calculator\Strategy\PathMatchScoringStrategy;
use App\TrackMatcher\Calculator\Strategy\SimilarityScoringStrategy;
use App\TrackMatcher\Calculator\TitleMatchCalculator;
use App\TrackMatcher\Calculator\TrackNumberMatchCalculator;
use App\TrackMatcher\Calculator\YearMatchCalculator;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ScoreCalculatorChainFunctionalTest extends KernelTestCase
{
    private ScoreCalculatorChain $scoreCalculatorChain;
    private TestConfigurationService $testConfigService;
    private StringSimilarity $stringSimilarity;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->stringSimilarity = self::getContainer()->get(StringSimilarity::class);
        $this->testConfigService = new TestConfigurationService(
            self::getContainer()->get(EntityManagerInterface::class),
            self::getContainer()->get(ConfigurationRepository::class)
        );

        // Set default configuration values for testing
        $this->testConfigService->setConfiguration('association.require_exact_title_match', false);
        $this->testConfigService->setConfiguration('association.require_exact_artist_match', false);
        $this->testConfigService->setConfiguration('association.require_exact_album_match', false);
        $this->testConfigService->setConfiguration('association.require_exact_year_match', false);
        $this->testConfigService->setConfiguration('association.require_exact_duration_match', false);

        // Create real calculators with real dependencies
        $calculators = [
            new TitleMatchCalculator($this->stringSimilarity, self::getContainer()->get(EntityManagerInterface::class)),
            new ArtistMatchCalculator([
                new ExactArtistMatchScoringStrategy(
                    self::getContainer()->get(ConfigurationFactory::class)
                ),
                new ArtistPathMatchScoringStrategy(),
                new ArtistSimilarityScoringStrategy($this->stringSimilarity),
            ]),
            new AlbumMatchCalculator([
                new ExactAlbumMatchScoringStrategy(),
                new PathMatchScoringStrategy(),
                new SimilarityScoringStrategy($this->stringSimilarity),
            ]),
            new TrackNumberMatchCalculator(),
            new DurationMatchCalculator(self::getContainer()->get(EntityManagerInterface::class)),
            new YearMatchCalculator(self::getContainer()->get(EntityManagerInterface::class)),
        ];

        $this->scoreCalculatorChain = new ScoreCalculatorChain($calculators);
    }

    public function testExecuteChainWithPerfectMatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020, 180, '01');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 2020, 180, '01');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $result = $this->scoreCalculatorChain->executeChain($track, $unmatchedTrack, $pathInfo);

        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('reasons', $result);
        $this->assertGreaterThan(80.0, $result['score'], 'Perfect match should have high score');
        $this->assertNotEmpty($result['reasons'], 'Should provide match reasons');
    }

    public function testExecuteChainWithPartialMatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020, 180, '01');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Different Album', 2020, 175, '01');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Different Album'];

        $result = $this->scoreCalculatorChain->executeChain($track, $unmatchedTrack, $pathInfo);

        $this->assertGreaterThan(30.0, $result['score'], 'Partial match should have moderate score');
        $this->assertLessThan(400.0, $result['score'], 'Partial match should not have extremely high score');
    }

    public function testExecuteChainWithPoorMatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020, 180, '01');
        $unmatchedTrack = $this->createUnmatchedTrack('Different Song', 'Different Artist', 'Different Album', 2019, 200, '01');

        $pathInfo = ['artist' => 'Different Artist', 'album' => 'Different Album'];

        $result = $this->scoreCalculatorChain->executeChain($track, $unmatchedTrack, $pathInfo);

        $this->assertLessThan(200.0, $result['score'], 'Poor match should have low score');
        $this->assertGreaterThanOrEqual(0.0, $result['score'], 'Score should not be negative');
    }

    public function testExecuteChainWithSpecificTypes(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020, 180, '01');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 2020, 180, '01');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $result = $this->scoreCalculatorChain->executeChainWithTypes($track, $unmatchedTrack, $pathInfo, ['title', 'artist']);

        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('reasons', $result);
        $this->assertGreaterThan(50.0, $result['score'], 'Specific types should give good score');
    }

    public function testGetAvailableTypes(): void
    {
        $types = $this->scoreCalculatorChain->getAvailableTypes();

        $this->assertContains('title', $types);
        $this->assertContains('artist', $types);
        $this->assertContains('album', $types);
        $this->assertContains('trackNumber', $types);
        $this->assertContains('duration', $types);
        $this->assertContains('year', $types);
    }

    public function testGetCalculatorByType(): void
    {
        $titleCalculator = $this->scoreCalculatorChain->getCalculatorByType('title');
        $artistCalculator = $this->scoreCalculatorChain->getCalculatorByType('artist');

        $this->assertInstanceOf(TitleMatchCalculator::class, $titleCalculator);
        $this->assertInstanceOf(ArtistMatchCalculator::class, $artistCalculator);
    }

    public function testExecuteChainWithExactTitleRequirement(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020, 180, '01');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 2020, 180, '01');

        // Set exact title match requirement
        $this->testConfigService->setConfiguration('association.require_exact_title_match', true);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $result = $this->scoreCalculatorChain->executeChain($track, $unmatchedTrack, $pathInfo);

        $this->assertGreaterThan(80.0, $result['score'], 'Exact title requirement should still give high score for exact match');
    }

    public function testExecuteChainWithExactTitleRequirementButNoMatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020, 180, '01');
        $unmatchedTrack = $this->createUnmatchedTrack('Similar Song', 'Test Artist', 'Test Album', 2020, 180, '01');

        // Set exact title match requirement
        $this->testConfigService->setConfiguration('association.require_exact_title_match', true);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $result = $this->scoreCalculatorChain->executeChain($track, $unmatchedTrack, $pathInfo);

        $this->assertLessThan(300.0, $result['score'], 'Exact title requirement should give lower score for non-exact match');
    }

    public function testExecuteChainWithDurationTolerance(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020, 180, '01');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 2020, 185, '01');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $result = $this->scoreCalculatorChain->executeChain($track, $unmatchedTrack, $pathInfo);

        $this->assertGreaterThan(50.0, $result['score'], 'Duration within tolerance should give good score');
    }

    public function testExecuteChainWithYearTolerance(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020, 180, '01');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 2021, 180, '01');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $result = $this->scoreCalculatorChain->executeChain($track, $unmatchedTrack, $pathInfo);

        $this->assertGreaterThan(50.0, $result['score'], 'Year within tolerance should give good score');
    }

    private function createTrack(string $title, string $artistName, string $albumTitle, int $year, int $duration, string $trackNumber): Track
    {
        $artist = new Artist();
        $artist->setName($artistName);

        $album = new Album();
        $album->setTitle($albumTitle);
        $album->setArtist($artist);
        $album->setReleaseDate(new DateTime("$year-01-01"));

        $track = new Track();
        $track->setTitle($title);
        $track->setAlbum($album);
        $track->setDuration($duration);
        $track->setTrackNumber($trackNumber);

        return $track;
    }

    private function createUnmatchedTrack(string $title, string $artistName, string $albumTitle, int $year, int $duration, string $trackNumber): UnmatchedTrack
    {
        $unmatchedTrack = new UnmatchedTrack();
        $unmatchedTrack->setTitle($title);
        $unmatchedTrack->setArtist($artistName);
        $unmatchedTrack->setAlbum($albumTitle);
        $unmatchedTrack->setYear($year);
        $unmatchedTrack->setDuration($duration);
        $unmatchedTrack->setTrackNumber($trackNumber);
        $unmatchedTrack->setFilePath("/music/{$artistName}/{$albumTitle}/{$title}.mp3");

        return $unmatchedTrack;
    }
}
