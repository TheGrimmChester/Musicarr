<?php

declare(strict_types=1);

namespace App\Tests\Integration\TrackMatcher;

use App\Analyzer\FilePathAnalyzer;
use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Track;
use App\Entity\UnmatchedTrack;
use App\StringSimilarity;
use App\TrackMatcher\Calculator\ScoreCalculatorChain;
use App\TrackMatcher\TrackMatcher;
use PHPUnit\Framework\TestCase;

class TrackMatcherTest extends TestCase
{
    private TrackMatcher $trackMatcher;
    private StringSimilarity $stringSimilarityService;
    private FilePathAnalyzer $filePathAnalyzerService;
    private ScoreCalculatorChain $scoreCalculatorChain;

    protected function setUp(): void
    {
        $this->stringSimilarityService = $this->createMock(StringSimilarity::class);
        $this->filePathAnalyzerService = $this->createMock(FilePathAnalyzer::class);
        $this->scoreCalculatorChain = $this->createMock(ScoreCalculatorChain::class);

        $this->trackMatcher = new TrackMatcher(
            $this->stringSimilarityService,
            $this->filePathAnalyzerService,
            $this->scoreCalculatorChain
        );
    }

    public function testCalculateMatchScore(): void
    {
        $track = $this->createTrack();
        $unmatchedTrack = $this->createUnmatchedTrack();
        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $this->scoreCalculatorChain->method('executeChain')
            ->with($track, $unmatchedTrack, $pathInfo)
            ->willReturn(['score' => 85.5, 'reasons' => ['Title match', 'Artist match']]);

        $score = $this->trackMatcher->calculateMatchScore($track, $unmatchedTrack, $pathInfo);
        $this->assertEquals(85.5, $score);
    }

    public function testCalculateMatchScoreWithNegativeScore(): void
    {
        $track = $this->createTrack();
        $unmatchedTrack = $this->createUnmatchedTrack();
        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $this->scoreCalculatorChain->method('executeChain')
            ->with($track, $unmatchedTrack, $pathInfo)
            ->willReturn(['score' => -10.0, 'reasons' => ['Title mismatch penalty']]);

        $score = $this->trackMatcher->calculateMatchScore($track, $unmatchedTrack, $pathInfo);
        $this->assertEquals(0.0, $score);
    }

    public function testCalculateMatchScoreWithScoreAbove100(): void
    {
        $track = $this->createTrack();
        $unmatchedTrack = $this->createUnmatchedTrack();
        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $this->scoreCalculatorChain->method('executeChain')
            ->with($track, $unmatchedTrack, $pathInfo)
            ->willReturn(['score' => 150.0, 'reasons' => ['Perfect match']]);

        $score = $this->trackMatcher->calculateMatchScore($track, $unmatchedTrack, $pathInfo);
        $this->assertEquals(100.0, $score);
    }

    public function testGetMatchReason(): void
    {
        $track = $this->createTrack();
        $unmatchedTrack = $this->createUnmatchedTrack();
        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $this->scoreCalculatorChain->method('executeChain')
            ->with($track, $unmatchedTrack, $pathInfo)
            ->willReturn(['score' => 85.5, 'reasons' => ['Title match', 'Artist match']]);

        $reason = $this->trackMatcher->getMatchReason($track, $unmatchedTrack, $pathInfo);
        $this->assertEquals('Title match, Artist match', $reason);
    }

    public function testGetMatchReasonWithNoReasons(): void
    {
        $track = $this->createTrack();
        $unmatchedTrack = $this->createUnmatchedTrack();
        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $this->scoreCalculatorChain->method('executeChain')
            ->with($track, $unmatchedTrack, $pathInfo)
            ->willReturn(['score' => 0.0, 'reasons' => []]);

        $reason = $this->trackMatcher->getMatchReason($track, $unmatchedTrack, $pathInfo);
        $this->assertEquals('No significant matches found', $reason);
    }

    public function testFindBestMatches(): void
    {
        $unmatchedTrack = $this->createUnmatchedTrack();
        $tracks = [$this->createTrack(), $this->createTrack()];
        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $this->filePathAnalyzerService->method('extractPathInformation')
            ->with('/test/path.mp3')
            ->willReturn($pathInfo);

        $this->scoreCalculatorChain->method('executeChain')
            ->willReturn(['score' => 85.5, 'reasons' => ['Title match']]);

        $matches = $this->trackMatcher->findBestMatches($unmatchedTrack, $tracks, 5);

        $this->assertCount(2, $matches);
        $this->assertEquals(85.5, $matches[0]['score']);
        $this->assertEquals('Title match', $matches[0]['reason']);
    }

    public function testFindBestMatchesWithLimit(): void
    {
        $unmatchedTrack = $this->createUnmatchedTrack();
        $tracks = [$this->createTrack(), $this->createTrack(), $this->createTrack()];
        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $this->filePathAnalyzerService->method('extractPathInformation')
            ->with('/test/path.mp3')
            ->willReturn($pathInfo);

        $this->scoreCalculatorChain->method('executeChain')
            ->willReturn(['score' => 85.5, 'reasons' => ['Title match']]);

        $matches = $this->trackMatcher->findBestMatches($unmatchedTrack, $tracks, 2);

        $this->assertCount(2, $matches);
    }

    public function testFindBestMatchesWithLowScores(): void
    {
        $unmatchedTrack = $this->createUnmatchedTrack();
        $tracks = [$this->createTrack()];
        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $this->filePathAnalyzerService->method('extractPathInformation')
            ->with('/test/path.mp3')
            ->willReturn($pathInfo);

        $this->scoreCalculatorChain->method('executeChain')
            ->willReturn(['score' => 0.05, 'reasons' => ['Very weak match']]);

        $matches = $this->trackMatcher->findBestMatches($unmatchedTrack, $tracks, 5);

        $this->assertCount(0, $matches);
    }

    public function testAreTracksSimilarWithValidTracks(): void
    {
        $track1 = $this->createTrack();
        $track2 = $this->createTrack();

        $this->stringSimilarityService->method('calculateSimilarity')
            ->willReturnMap([
                ['Test Artist', 'Test Artist', 1.0],
                ['Test Album', 'Test Album', 1.0],
                ['Test Track', 'Test Track', 1.0],
            ]);

        $result = $this->trackMatcher->areTracksSimilar($track1, $track2, 0.8);
        $this->assertTrue($result);
    }

    public function testAreTracksSimilarWithLowSimilarity(): void
    {
        $track1 = $this->createTrack();
        $track2 = $this->createTrack();

        $this->stringSimilarityService->method('calculateSimilarity')
            ->willReturnMap([
                ['Test Artist', 'Test Artist', 0.5],
                ['Test Album', 'Test Album', 0.5],
                ['Test Track', 'Test Track', 0.5],
            ]);

        $result = $this->trackMatcher->areTracksSimilar($track1, $track2, 0.8);
        $this->assertFalse($result);
    }

    public function testAreTracksSimilarWithInvalidTracks(): void
    {
        $track1 = $this->createTrack();
        $track2 = new Track(); // No album or artist

        $result = $this->trackMatcher->areTracksSimilar($track1, $track2, 0.8);
        $this->assertFalse($result);
    }

    public function testCalculateMatchScoreWithCustomChain(): void
    {
        $track = $this->createTrack();
        $unmatchedTrack = $this->createUnmatchedTrack();
        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];
        $calculatorTypes = ['title', 'artist'];

        $this->scoreCalculatorChain->method('executeChainWithTypes')
            ->with($track, $unmatchedTrack, $pathInfo, $calculatorTypes)
            ->willReturn(['score' => 75.0, 'reasons' => ['Title match', 'Artist match']]);

        $score = $this->trackMatcher->calculateMatchScoreWithCustomChain($track, $unmatchedTrack, $pathInfo, $calculatorTypes);
        $this->assertEquals(75.0, $score);
    }

    public function testCalculateMatchScoreWithCustomChainAbove100(): void
    {
        $track = $this->createTrack();
        $unmatchedTrack = $this->createUnmatchedTrack();
        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];
        $calculatorTypes = ['title', 'artist'];

        $this->scoreCalculatorChain->method('executeChainWithTypes')
            ->with($track, $unmatchedTrack, $pathInfo, $calculatorTypes)
            ->willReturn(['score' => 150.0, 'reasons' => ['Perfect match']]);

        $score = $this->trackMatcher->calculateMatchScoreWithCustomChain($track, $unmatchedTrack, $pathInfo, $calculatorTypes);
        $this->assertEquals(100.0, $score);
    }

    public function testGetDetailedMatchAnalysis(): void
    {
        $track = $this->createTrack();
        $unmatchedTrack = $this->createUnmatchedTrack();
        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $this->scoreCalculatorChain->method('executeChain')
            ->with($track, $unmatchedTrack, $pathInfo)
            ->willReturn(['score' => 85.5, 'reasons' => ['Title match', 'Artist match']]);

        $this->scoreCalculatorChain->method('getAvailableTypes')
            ->willReturn(['title', 'artist', 'album', 'duration']);

        $analysis = $this->trackMatcher->getDetailedMatchAnalysis($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(85.5, $analysis['totalScore']);
        $this->assertEquals(['Title match', 'Artist match'], $analysis['reasons']);
        $this->assertEquals($pathInfo, $analysis['pathInfo']);
        $this->assertEquals(['title', 'artist', 'album', 'duration'], $analysis['availableTypes']);
        $this->assertEquals('Test Track', $analysis['trackInfo']['title']);
        $this->assertEquals('Test Album', $analysis['trackInfo']['album']);
        $this->assertEquals('Test Artist', $analysis['trackInfo']['artist']);
        $this->assertEquals('Test Track', $analysis['unmatchedTrackInfo']['title']);
    }

    // Real-life test scenarios with various track names and formats

    public function testAreTracksSimilarWithRealMusicTitles(): void
    {
        $track1 = $this->createTrackWithRealData('Bohemian Rhapsody', 'Queen', 'A Night at the Opera');
        $track2 = $this->createTrackWithRealData('Bohemian Rhapsody', 'Queen', 'A Night at the Opera');

        $this->stringSimilarityService->method('calculateSimilarity')
            ->willReturnMap([
                ['Queen', 'Queen', 1.0],
                ['A Night at the Opera', 'A Night at the Opera', 1.0],
                ['Bohemian Rhapsody', 'Bohemian Rhapsody', 1.0],
            ]);

        $result = $this->trackMatcher->areTracksSimilar($track1, $track2, 0.8);
        $this->assertTrue($result);
    }

    public function testAreTracksSimilarWithTrackNumberVariations(): void
    {
        $track1 = $this->createTrackWithRealData('01. Stairway to Heaven', 'Led Zeppelin', 'Led Zeppelin IV');
        $track2 = $this->createTrackWithRealData('Stairway to Heaven', 'Led Zeppelin', 'Led Zeppelin IV');

        $this->stringSimilarityService->method('calculateSimilarity')
            ->willReturnMap([
                ['Led Zeppelin', 'Led Zeppelin', 1.0],
                ['Led Zeppelin IV', 'Led Zeppelin IV', 1.0],
                ['01. Stairway to Heaven', 'Stairway to Heaven', 0.85], // Track number difference
            ]);

        $result = $this->trackMatcher->areTracksSimilar($track1, $track2, 0.8);
        $this->assertTrue($result); // Should still be similar enough
    }

    public function testAreTracksSimilarWithRemixVersions(): void
    {
        $track1 = $this->createTrackWithRealData('Billie Jean', 'Michael Jackson', 'Thriller');
        $track2 = $this->createTrackWithRealData('Billie Jean (Extended Remix)', 'Michael Jackson', 'Thriller');

        $this->stringSimilarityService->method('calculateSimilarity')
            ->willReturnMap([
                ['Michael Jackson', 'Michael Jackson', 1.0],
                ['Thriller', 'Thriller', 1.0],
                ['Billie Jean', 'Billie Jean (Extended Remix)', 0.4], // Remix version - much lower similarity
            ]);

        $result = $this->trackMatcher->areTracksSimilar($track1, $track2, 0.8);
        $this->assertFalse($result); // Below threshold due to title difference
    }

    public function testAreTracksSimilarWithLiveVersions(): void
    {
        $track1 = $this->createTrackWithRealData('Hotel California', 'Eagles', 'Hotel California');
        $track2 = $this->createTrackWithRealData('Hotel California (Live)', 'Eagles', 'Hotel California');

        $this->stringSimilarityService->method('calculateSimilarity')
            ->willReturnMap([
                ['Eagles', 'Eagles', 1.0],
                ['Hotel California', 'Hotel California', 1.0],
                ['Hotel California', 'Hotel California (Live)', 0.8], // Live version
            ]);

        $result = $this->trackMatcher->areTracksSimilar($track1, $track2, 0.8);
        $this->assertTrue($result); // Exactly at threshold
    }

    public function testAreTracksSimilarWithFeaturingArtists(): void
    {
        $track1 = $this->createTrackWithRealData('Uptown Funk', 'Mark Ronson ft. Bruno Mars', 'Uptown Special');
        $track2 = $this->createTrackWithRealData('Uptown Funk', 'Mark Ronson featuring Bruno Mars', 'Uptown Special');

        $this->stringSimilarityService->method('calculateSimilarity')
            ->willReturnMap([
                ['Mark Ronson ft. Bruno Mars', 'Mark Ronson featuring Bruno Mars', 0.9], // Different featuring format
                ['Uptown Special', 'Uptown Special', 1.0],
                ['Uptown Funk', 'Uptown Funk', 1.0],
            ]);

        $result = $this->trackMatcher->areTracksSimilar($track1, $track2, 0.8);
        $this->assertTrue($result); // High overall similarity
    }

    public function testAreTracksSimilarWithSpecialCharacters(): void
    {
        $track1 = $this->createTrackWithRealData('Café del Mar', 'Energy 52', 'Café del Mar');
        $track2 = $this->createTrackWithRealData('Cafe del Mar', 'Energy 52', 'Cafe del Mar');

        $this->stringSimilarityService->method('calculateSimilarity')
            ->willReturnMap([
                ['Energy 52', 'Energy 52', 1.0],
                ['Café del Mar', 'Cafe del Mar', 0.95], // Accent difference
                ['Café del Mar', 'Cafe del Mar', 0.95],
            ]);

        $result = $this->trackMatcher->areTracksSimilar($track1, $track2, 0.8);
        $this->assertTrue($result); // High similarity despite accent
    }

    public function testAreTracksSimilarWithDeluxeEdition(): void
    {
        $track1 = $this->createTrackWithRealData('Wonderwall', 'Oasis', 'What\'s the Story Morning Glory');
        $track2 = $this->createTrackWithRealData('Wonderwall', 'Oasis', 'What\'s the Story Morning Glory (Deluxe Edition)');

        $this->stringSimilarityService->method('calculateSimilarity')
            ->willReturnMap([
                ['Oasis', 'Oasis', 1.0],
                ['What\'s the Story Morning Glory', 'What\'s the Story Morning Glory (Deluxe Edition)', 0.85], // Deluxe edition
                ['Wonderwall', 'Wonderwall', 1.0],
            ]);

        $result = $this->trackMatcher->areTracksSimilar($track1, $track2, 0.8);
        $this->assertTrue($result); // High similarity
    }

    public function testAreTracksSimilarWithJapaneseTitles(): void
    {
        $track1 = $this->createTrackWithRealData('残酷な天使のテーゼ', '高橋洋子', '新世紀エヴァンゲリオン');
        $track2 = $this->createTrackWithRealData('残酷な天使のテーゼ', '高橋洋子', '新世紀エヴァンゲリオン');

        $this->stringSimilarityService->method('calculateSimilarity')
            ->willReturnMap([
                ['高橋洋子', '高橋洋子', 1.0],
                ['新世紀エヴァンゲリオン', '新世紀エヴァンゲリオン', 1.0],
                ['残酷な天使のテーゼ', '残酷な天使のテーゼ', 1.0],
            ]);

        $result = $this->trackMatcher->areTracksSimilar($track1, $track2, 0.8);
        $this->assertTrue($result); // Perfect match with Japanese characters
    }

    public function testAreTracksSimilarWithCompilationAlbums(): void
    {
        $track1 = $this->createTrackWithRealData('Sweet Child O\' Mine', 'Guns N\' Roses', 'Appetite for Destruction');
        $track2 = $this->createTrackWithRealData('Sweet Child O\' Mine', 'Guns N\' Roses', 'Greatest Hits');

        $this->stringSimilarityService->method('calculateSimilarity')
            ->willReturnMap([
                ['Guns N\' Roses', 'Guns N\' Roses', 1.0],
                ['Appetite for Destruction', 'Greatest Hits', 0.3], // Different albums
                ['Sweet Child O\' Mine', 'Sweet Child O\' Mine', 1.0],
            ]);

        $result = $this->trackMatcher->areTracksSimilar($track1, $track2, 0.8);
        $this->assertFalse($result); // Low album similarity
    }

    public function testAreTracksSimilarWithAcousticVersions(): void
    {
        $track1 = $this->createTrackWithRealData('Nothing Else Matters', 'Metallica', 'Metallica (The Black Album)');
        $track2 = $this->createTrackWithRealData('Nothing Else Matters (Acoustic)', 'Metallica', 'Metallica (The Black Album)');

        $this->stringSimilarityService->method('calculateSimilarity')
            ->willReturnMap([
                ['Metallica', 'Metallica', 1.0],
                ['Metallica (The Black Album)', 'Metallica (The Black Album)', 1.0],
                ['Nothing Else Matters', 'Nothing Else Matters (Acoustic)', 0.8], // Acoustic version
            ]);

        $result = $this->trackMatcher->areTracksSimilar($track1, $track2, 0.8);
        $this->assertTrue($result); // Exactly at threshold
    }

    public function testAreTracksSimilarWithExplicitVersions(): void
    {
        $track1 = $this->createTrackWithRealData('Lose Yourself', 'Eminem', '8 Mile');
        $track2 = $this->createTrackWithRealData('Lose Yourself (Explicit)', 'Eminem', '8 Mile');

        $this->stringSimilarityService->method('calculateSimilarity')
            ->willReturnMap([
                ['Eminem', 'Eminem', 1.0],
                ['8 Mile', '8 Mile', 1.0],
                ['Lose Yourself', 'Lose Yourself (Explicit)', 0.85], // Explicit version
            ]);

        $result = $this->trackMatcher->areTracksSimilar($track1, $track2, 0.8);
        $this->assertTrue($result); // High similarity
    }

    public function testAreTracksSimilarWithInstrumentalVersions(): void
    {
        $track1 = $this->createTrackWithRealData('Clocks', 'Coldplay', 'A Rush of Blood to the Head');
        $track2 = $this->createTrackWithRealData('Clocks (Instrumental)', 'Coldplay', 'A Rush of Blood to the Head');

        $this->stringSimilarityService->method('calculateSimilarity')
            ->willReturnMap([
                ['Coldplay', 'Coldplay', 1.0],
                ['A Rush of Blood to the Head', 'A Rush of Blood to the Head', 1.0],
                ['Clocks', 'Clocks (Instrumental)', 0.8], // Instrumental version
            ]);

        $result = $this->trackMatcher->areTracksSimilar($track1, $track2, 0.8);
        $this->assertTrue($result); // Exactly at threshold
    }

    public function testAreTracksSimilarWithBonusTracks(): void
    {
        $track1 = $this->createTrackWithRealData('Fix You', 'Coldplay', 'X&Y');
        $track2 = $this->createTrackWithRealData('Fix You (Bonus Track)', 'Coldplay', 'X&Y');

        $this->stringSimilarityService->method('calculateSimilarity')
            ->willReturnMap([
                ['Coldplay', 'Coldplay', 1.0],
                ['X&Y', 'X&Y', 1.0],
                ['Fix You', 'Fix You (Bonus Track)', 0.85], // Bonus track
            ]);

        $result = $this->trackMatcher->areTracksSimilar($track1, $track2, 0.8);
        $this->assertTrue($result); // High similarity
    }

    public function testAreTracksSimilarWithDifferentArtistsSameSong(): void
    {
        $track1 = $this->createTrackWithRealData('Hallelujah', 'Jeff Buckley', 'Grace');
        $track2 = $this->createTrackWithRealData('Hallelujah', 'Leonard Cohen', 'Various Positions');

        $this->stringSimilarityService->method('calculateSimilarity')
            ->willReturnMap([
                ['Jeff Buckley', 'Leonard Cohen', 0.2], // Different artists
                ['Grace', 'Various Positions', 0.1], // Different albums
                ['Hallelujah', 'Hallelujah', 1.0], // Same song title
            ]);

        $result = $this->trackMatcher->areTracksSimilar($track1, $track2, 0.8);
        $this->assertFalse($result); // Low overall similarity due to different artists/albums
    }

    public function testAreTracksSimilarWithCoverVersions(): void
    {
        $track1 = $this->createTrackWithRealData('Hurt', 'Nine Inch Nails', 'The Downward Spiral');
        $track2 = $this->createTrackWithRealData('Hurt', 'Johnny Cash', 'American IV: The Man Comes Around');

        $this->stringSimilarityService->method('calculateSimilarity')
            ->willReturnMap([
                ['Nine Inch Nails', 'Johnny Cash', 0.1], // Completely different artists
                ['The Downward Spiral', 'American IV: The Man Comes Around', 0.1], // Different albums
                ['Hurt', 'Hurt', 1.0], // Same song title
            ]);

        $result = $this->trackMatcher->areTracksSimilar($track1, $track2, 0.8);
        $this->assertFalse($result); // Very low similarity despite same title
    }

    public function testAreTracksSimilarWithRemasteredVersions(): void
    {
        $track1 = $this->createTrackWithRealData('Smells Like Teen Spirit', 'Nirvana', 'Nevermind');
        $track2 = $this->createTrackWithRealData('Smells Like Teen Spirit', 'Nirvana', 'Nevermind (Remastered)');

        $this->stringSimilarityService->method('calculateSimilarity')
            ->willReturnMap([
                ['Nirvana', 'Nirvana', 1.0],
                ['Nevermind', 'Nevermind (Remastered)', 0.9], // Remastered version
                ['Smells Like Teen Spirit', 'Smells Like Teen Spirit', 1.0],
            ]);

        $result = $this->trackMatcher->areTracksSimilar($track1, $track2, 0.8);
        $this->assertTrue($result); // High similarity
    }

    public function testAreTracksSimilarWithAnniversaryEditions(): void
    {
        $track1 = $this->createTrackWithRealData('Imagine', 'John Lennon', 'Imagine');
        $track2 = $this->createTrackWithRealData('Imagine', 'John Lennon', 'Imagine (50th Anniversary Edition)');

        $this->stringSimilarityService->method('calculateSimilarity')
            ->willReturnMap([
                ['John Lennon', 'John Lennon', 1.0],
                ['Imagine', 'Imagine (50th Anniversary Edition)', 0.8], // Anniversary edition
                ['Imagine', 'Imagine', 1.0],
            ]);

        $result = $this->trackMatcher->areTracksSimilar($track1, $track2, 0.8);
        $this->assertTrue($result); // Exactly at threshold
    }

    public function testAreTracksSimilarWithDifferentApostropheCharacters(): void
    {
        $track1 = $this->createTrackWithRealData('Soulja\'s Story', 'Kendrick Lamar', 'Section.80');
        $track2 = $this->createTrackWithRealData('Soulja’s Story', 'Kendrick Lamar', 'Section.80');

        $this->stringSimilarityService->method('calculateSimilarity')
            ->willReturnMap([
                ['Kendrick Lamar', 'Kendrick Lamar', 1.0],
                ['Section.80', 'Section.80', 1.0],
                ['Soulja\'s Story', 'Soulja’s Story', 0.95], // Different apostrophe characters
            ]);

        $result = $this->trackMatcher->areTracksSimilar($track1, $track2, 0.8);
        $this->assertTrue($result); // High similarity despite apostrophe difference
    }

    public function testAreTracksSimilarWithSouljaStoryApostropheVariations(): void
    {
        // Test the specific case mentioned: "Soulja's Story" vs "Soulja's Story"
        // One with straight apostrophe, one with curly apostrophe
        $track1 = $this->createTrackWithRealData('Soulja\'s Story', 'Kendrick Lamar', 'Section.80');
        $track2 = $this->createTrackWithRealData('Soulja’s Story', 'Kendrick Lamar', 'Section.80');

        $this->stringSimilarityService->method('calculateSimilarity')
            ->willReturnMap([
                ['Kendrick Lamar', 'Kendrick Lamar', 1.0],
                ['Section.80', 'Section.80', 1.0],
                ['Soulja\'s Story', 'Soulja’s Story', 0.98], // Very high similarity for apostrophe variations
            ]);

        $result = $this->trackMatcher->areTracksSimilar($track1, $track2, 0.8);
        $this->assertTrue($result); // Should be well above threshold
    }

    private function createTrack(): Track
    {
        $artist = new Artist();
        $artist->setName('Test Artist');

        $album = new Album();
        $album->setTitle('Test Album');
        $album->setArtist($artist);

        $track = new Track();
        $track->setTitle('Test Track');
        $track->setAlbum($album);
        $track->setTrackNumber('1');

        return $track;
    }

    private function createUnmatchedTrack(): UnmatchedTrack
    {
        $unmatchedTrack = new UnmatchedTrack();
        $unmatchedTrack->setFilePath('/test/path.mp3');
        $unmatchedTrack->setTitle('Test Track');
        $unmatchedTrack->setArtist('Test Artist');
        $unmatchedTrack->setAlbum('Test Album');

        return $unmatchedTrack;
    }

    private function createTrackWithRealData(string $title, string $artistName, string $albumTitle): Track
    {
        $artist = new Artist();
        $artist->setName($artistName);

        $album = new Album();
        $album->setTitle($albumTitle);
        $album->setArtist($artist);

        $track = new Track();
        $track->setTitle($title);
        $track->setAlbum($album);
        $track->setTrackNumber('1');

        return $track;
    }
}
