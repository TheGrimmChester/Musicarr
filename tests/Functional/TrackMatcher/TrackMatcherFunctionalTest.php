<?php

declare(strict_types=1);

namespace App\Tests\Functional\TrackMatcher;

use App\Analyzer\FilePathAnalyzer;
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
use App\TrackMatcher\TrackMatcher;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TrackMatcherFunctionalTest extends KernelTestCase
{
    private TrackMatcher $trackMatcher;
    private ScoreCalculatorChain $scoreCalculatorChain;
    private StringSimilarity $stringSimilarity;
    private FilePathAnalyzer $filePathAnalyzer;
    private TestConfigurationService $testConfigService;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->stringSimilarity = self::getContainer()->get(StringSimilarity::class);
        $this->filePathAnalyzer = self::getContainer()->get(FilePathAnalyzer::class);
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

        // Create a real ScoreCalculatorChain with real dependencies
        $this->scoreCalculatorChain = new ScoreCalculatorChain([
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
        ]);

        $this->trackMatcher = new TrackMatcher(
            $this->stringSimilarity,
            $this->filePathAnalyzer,
            $this->scoreCalculatorChain
        );
    }

    public function testCalculateMatchScoreWithPerfectMatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020, 180);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 2020, 180);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->trackMatcher->calculateMatchScore($track, $unmatchedTrack, $pathInfo);

        $this->assertGreaterThan(80.0, $score, 'Perfect match should have high score');
        $this->assertLessThanOrEqual(100.0, $score, 'Score should not exceed 100');
    }

    public function testCalculateMatchScoreWithPartialMatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020, 180);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Different Album', 2020, 180);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Different Album'];

        $score = $this->trackMatcher->calculateMatchScore($track, $unmatchedTrack, $pathInfo);

        $this->assertGreaterThan(30.0, $score, 'Partial match should have moderate score');
        $this->assertLessThan(400.0, $score, 'Partial match should not have extremely high score');
    }

    public function testCalculateMatchScoreWithPoorMatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020, 180);
        $unmatchedTrack = $this->createUnmatchedTrack('Different Song', 'Different Artist', 'Different Album', 2019, 200);

        $pathInfo = ['artist' => 'Different Artist', 'album' => 'Different Album'];

        $score = $this->trackMatcher->calculateMatchScore($track, $unmatchedTrack, $pathInfo);

        $this->assertLessThan(200.0, $score, 'Poor match should have low score');
        $this->assertGreaterThanOrEqual(0.0, $score, 'Score should not be negative');
    }

    public function testFindBestMatches(): void
    {
        $tracks = [
            $this->createTrack('Perfect Match', 'Test Artist', 'Test Album', 2020, 180),
            $this->createTrack('Good Match', 'Test Artist', 'Test Album', 2020, 175),
            $this->createTrack('Poor Match', 'Different Artist', 'Different Album', 2019, 200),
        ];

        $unmatchedTrack = $this->createUnmatchedTrack('Perfect Match', 'Test Artist', 'Test Album', 2020, 180);

        $matches = $this->trackMatcher->findBestMatches($unmatchedTrack, $tracks, 2);

        $this->assertCount(2, $matches, 'Should return requested number of matches');
        $this->assertGreaterThanOrEqual($matches[1]['score'], $matches[0]['score'], 'Matches should be sorted by score (descending)');
    }

    public function testAreTracksSimilar(): void
    {
        $track1 = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020, 180);
        $track2 = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020, 180);
        $track3 = $this->createTrack('Different Song', 'Different Artist', 'Different Album', 2019, 200);

        $this->assertTrue($this->trackMatcher->areTracksSimilar($track1, $track2), 'Identical tracks should be similar');
        $this->assertFalse($this->trackMatcher->areTracksSimilar($track1, $track3), 'Very different tracks should not be similar');
    }

    public function testAreTracksSimilarWithDifferentTitles(): void
    {
        $track1 = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020, 180);
        $track2 = $this->createTrack('Similar Song', 'Test Artist', 'Test Album', 2020, 180);

        $this->assertTrue($this->trackMatcher->areTracksSimilar($track1, $track2), 'Tracks with similar metadata should be similar');
    }

    public function testGetMatchReason(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020, 180);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 2020, 180);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $reason = $this->trackMatcher->getMatchReason($track, $unmatchedTrack, $pathInfo);

        $this->assertIsString($reason, 'Should return a string reason');
        $this->assertNotEmpty($reason, 'Reason should not be empty');
    }

    public function testCalculateMatchScoreWithExactTitleRequirement(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020, 180);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 2020, 180);

        // Set exact title match requirement
        $this->testConfigService->setConfiguration('association.require_exact_title_match', true);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->trackMatcher->calculateMatchScore($track, $unmatchedTrack, $pathInfo);

        $this->assertGreaterThan(80.0, $score, 'Exact title requirement should still give high score for exact match');
    }

    public function testCalculateMatchScoreWithExactTitleRequirementButNoMatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 2020, 180);
        $unmatchedTrack = $this->createUnmatchedTrack('Similar Song', 'Test Artist', 'Test Album', 2020, 180);

        // Set exact title match requirement
        $this->testConfigService->setConfiguration('association.require_exact_title_match', true);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->trackMatcher->calculateMatchScore($track, $unmatchedTrack, $pathInfo);

        $this->assertLessThan(300.0, $score, 'Exact title requirement should give lower score for non-exact match');
    }

    private function createTrack(string $title, string $artistName, string $albumTitle, int $year, int $duration): Track
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

        return $track;
    }

    private function createUnmatchedTrack(string $title, string $artistName, string $albumTitle, int $year, int $duration): UnmatchedTrack
    {
        $unmatchedTrack = new UnmatchedTrack();
        $unmatchedTrack->setTitle($title);
        $unmatchedTrack->setArtist($artistName);
        $unmatchedTrack->setAlbum($albumTitle);
        $unmatchedTrack->setYear($year);
        $unmatchedTrack->setDuration($duration);
        $unmatchedTrack->setFilePath("/music/{$artistName}/{$albumTitle}/{$title}.mp3");

        return $unmatchedTrack;
    }
}
