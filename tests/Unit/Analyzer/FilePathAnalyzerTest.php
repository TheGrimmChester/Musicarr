<?php

declare(strict_types=1);

namespace App\Tests\Unit\Analyzer;

use App\Analyzer\FilePathAnalyzer;
use PHPUnit\Framework\TestCase;

class FilePathAnalyzerTest extends TestCase
{
    private FilePathAnalyzer $filePathAnalyzer;

    protected function setUp(): void
    {
        $this->filePathAnalyzer = new FilePathAnalyzer();
    }

    public function testExtractPathInformationWithStandardPath(): void
    {
        $filePath = '/home/user/Music/Artist Name/Album Name/01 Track Title.mp3';
        $result = $this->filePathAnalyzer->extractPathInformation($filePath);

        $this->assertEquals('Artist Name', $result['artist']);
        $this->assertEquals('Album Name', $result['album']);
        $this->assertNull($result['year']);
        $this->assertEquals('01', $result['track_number']);
        $this->assertCount(5, $result['directory_structure']); // home, user, Music, Artist Name, Album Name
    }

    public function testExtractPathInformationWithYearAtStart(): void
    {
        $filePath = '/home/user/Music/Artist Name/1999 Album Name/01 Track Title.mp3';
        $result = $this->filePathAnalyzer->extractPathInformation($filePath);

        $this->assertEquals('Artist Name', $result['artist']);
        $this->assertEquals('Album Name', $result['album']);
        $this->assertEquals(1999, $result['year']);
        $this->assertEquals('01', $result['track_number']);
    }

    public function testExtractPathInformationWithYearAtEnd(): void
    {
        $filePath = '/home/user/Music/Artist Name/Album Name 2000/01 Track Title.mp3';
        $result = $this->filePathAnalyzer->extractPathInformation($filePath);

        $this->assertEquals('Artist Name', $result['artist']);
        $this->assertEquals('Album Name', $result['album']);
        $this->assertEquals(2000, $result['year']);
        $this->assertEquals('01', $result['track_number']);
    }

    public function testExtractPathInformationWithMusicDirectory(): void
    {
        $filePath = '/home/user/Music/Artist Name/Album Name/01 Track Title.mp3';
        $result = $this->filePathAnalyzer->extractPathInformation($filePath);

        $this->assertEquals('Artist Name', $result['artist']);
        $this->assertEquals('Album Name', $result['album']);
        $this->assertContains('Music', $result['directory_structure']);
    }

    public function testExtractPathInformationWithMusicDirectoryCaseInsensitive(): void
    {
        $filePath = '/home/user/music/Artist Name/Album Name/01 Track Title.mp3';
        $result = $this->filePathAnalyzer->extractPathInformation($filePath);

        $this->assertEquals('Artist Name', $result['artist']);
        $this->assertEquals('Album Name', $result['album']);
        $this->assertContains('music', $result['directory_structure']);
    }

    public function testExtractPathInformationWithoutMusicDirectory(): void
    {
        $filePath = '/home/user/Artist Name/Album Name/01 Track Title.mp3';
        $result = $this->filePathAnalyzer->extractPathInformation($filePath);

        $this->assertEquals('home', $result['artist']); // First part becomes artist
        $this->assertEquals('user', $result['album']); // Second part becomes album
        $this->assertNotContains('Music', $result['directory_structure']);
    }

    public function testExtractPathInformationWithComplexPath(): void
    {
        $filePath = '/home/user/Music/Genre/Artist Name/1999 Album Name (Deluxe Edition)/01 Track Title.mp3';
        $result = $this->filePathAnalyzer->extractPathInformation($filePath);

        $this->assertEquals('Genre', $result['artist']); // First part after Music
        $this->assertEquals('Album Name (Deluxe Edition)', $result['album']); // Second part after Music
        $this->assertEquals(1999, $result['year']);
        $this->assertEquals('01', $result['track_number']);
    }

    public function testExtractPathInformationWithVinylTrackNumber(): void
    {
        $filePath = '/home/user/Music/Artist Name/Album Name/A1 Track Title.mp3';
        $result = $this->filePathAnalyzer->extractPathInformation($filePath);

        $this->assertEquals('Artist Name', $result['artist']);
        $this->assertEquals('Album Name', $result['album']);
        $this->assertEquals('A1', $result['track_number']);
    }

    public function testExtractPathInformationWithTrackNumberSeparator(): void
    {
        $filePath = '/home/user/Music/Artist Name/Album Name/01. Track Title.mp3';
        $result = $this->filePathAnalyzer->extractPathInformation($filePath);

        $this->assertEquals('01', $result['track_number']);
    }

    public function testExtractPathInformationWithTrackNumberDash(): void
    {
        $filePath = '/home/user/Music/Artist Name/Album Name/01- Track Title.mp3';
        $result = $this->filePathAnalyzer->extractPathInformation($filePath);

        $this->assertEquals('01', $result['track_number']);
    }

    public function testExtractPathInformationWithTrackNumberSpace(): void
    {
        $filePath = '/home/user/Music/Artist Name/Album Name/01 Track Title.mp3';
        $result = $this->filePathAnalyzer->extractPathInformation($filePath);

        $this->assertEquals('01', $result['track_number']);
    }

    public function testExtractPathInformationWithNoTrackNumber(): void
    {
        $filePath = '/home/user/Music/Artist Name/Album Name/Track Title.mp3';
        $result = $this->filePathAnalyzer->extractPathInformation($filePath);

        $this->assertNull($result['track_number']);
    }

    public function testExtractPathInformationWithRootPath(): void
    {
        $filePath = '/track.mp3';
        $result = $this->filePathAnalyzer->extractPathInformation($filePath);

        $this->assertEquals('', $result['artist']); // Empty string, not null
        $this->assertNull($result['album']);
        $this->assertNull($result['year']);
        $this->assertNull($result['track_number']);
        $this->assertCount(1, $result['directory_structure']); // Contains one empty string
        $this->assertEquals('', $result['directory_structure'][0]); // First element is empty string
    }

    public function testExtractPathInformationWithSingleDirectory(): void
    {
        $filePath = '/home/Artist Name/track.mp3';
        $result = $this->filePathAnalyzer->extractPathInformation($filePath);

        $this->assertEquals('home', $result['artist']); // First part becomes artist
        $this->assertEquals('Artist Name', $result['album']); // Second part becomes album
        $this->assertNull($result['year']);
        $this->assertNull($result['track_number']);
    }

    public function testExtractPathInformationWithYearOnly(): void
    {
        $filePath = '/home/user/Music/1999/01 Track Title.mp3';
        $result = $this->filePathAnalyzer->extractPathInformation($filePath);

        $this->assertEquals('1999', $result['artist']); // Year becomes artist when no other parts
        $this->assertNull($result['album']); // No album part
        $this->assertNull($result['year']); // Year is not extracted as year in this case
        $this->assertEquals('01', $result['track_number']);
    }

    public function testExtractPathInformationWithYearAndAlbum(): void
    {
        $filePath = '/home/user/Music/1999 Album Name/01 Track Title.mp3';
        $result = $this->filePathAnalyzer->extractPathInformation($filePath);

        $this->assertNull($result['artist']); // Year is processed first, so no artist
        $this->assertEquals('Album Name', $result['album']);
        $this->assertEquals(1999, $result['year']);
        $this->assertEquals('01', $result['track_number']);
    }

    public function testExtractArtistFromPath(): void
    {
        $filePath = '/home/user/Music/Artist Name/Album Name/01 Track Title.mp3';
        $artist = $this->filePathAnalyzer->extractArtistFromPath($filePath);

        $this->assertEquals('Artist Name', $artist);
    }

    public function testExtractAlbumFromPath(): void
    {
        $filePath = '/home/user/Music/Artist Name/Album Name/01 Track Title.mp3';
        $album = $this->filePathAnalyzer->extractAlbumFromPath($filePath);

        $this->assertEquals('Album Name', $album);
    }

    public function testExtractYearFromPath(): void
    {
        $filePath = '/home/user/Music/Artist Name/1999 Album Name/01 Track Title.mp3';
        $year = $this->filePathAnalyzer->extractYearFromPath($filePath);

        $this->assertEquals(1999, $year);
    }

    public function testExtractTrackNumberFromPath(): void
    {
        $filePath = '/home/user/Music/Artist Name/Album Name/01 Track Title.mp3';
        $trackNumber = $this->filePathAnalyzer->extractTrackNumberFromPath($filePath);

        $this->assertEquals('01', $trackNumber);
    }

    public function testHasYearInPathWithYear(): void
    {
        $filePath = '/home/user/Music/Artist Name/1999 Album Name/01 Track Title.mp3';
        $hasYear = $this->filePathAnalyzer->hasYearInPath($filePath);

        $this->assertTrue($hasYear);
    }

    public function testHasYearInPathWithoutYear(): void
    {
        $filePath = '/home/user/Music/Artist Name/Album Name/01 Track Title.mp3';
        $hasYear = $this->filePathAnalyzer->hasYearInPath($filePath);

        $this->assertFalse($hasYear);
    }

    public function testGetDirectoryStructure(): void
    {
        $filePath = '/home/user/Music/Artist Name/Album Name/01 Track Title.mp3';
        $structure = $this->filePathAnalyzer->getDirectoryStructure($filePath);

        $expected = ['home', 'user', 'Music', 'Artist Name', 'Album Name'];
        $this->assertEquals($expected, $structure);
    }

    public function testExtractPathInformationWithSpecialCharacters(): void
    {
        $filePath = '/home/user/Music/Artist & Name/Album (Special Edition)/01 Track Title.mp3';
        $result = $this->filePathAnalyzer->extractPathInformation($filePath);

        $this->assertEquals('Artist & Name', $result['artist']);
        $this->assertEquals('Album (Special Edition)', $result['album']);
        $this->assertEquals('01', $result['track_number']);
    }

    public function testExtractPathInformationWithAccentedCharacters(): void
    {
        $filePath = '/home/user/Music/Artiste Français/Album Français/01 Titre.mp3';
        $result = $this->filePathAnalyzer->extractPathInformation($filePath);

        $this->assertEquals('Artiste Français', $result['artist']);
        $this->assertEquals('Album Français', $result['album']);
        $this->assertEquals('01', $result['track_number']);
    }

    public function testExtractPathInformationWithJapaneseCharacters(): void
    {
        $filePath = '/home/user/Music/アーティスト名/アルバム名/01 曲名.mp3';
        $result = $this->filePathAnalyzer->extractPathInformation($filePath);

        $this->assertEquals('アーティスト名', $result['artist']);
        $this->assertEquals('アルバム名', $result['album']);
        $this->assertEquals('01', $result['track_number']);
    }

    public function testExtractPathInformationWithMultipleYears(): void
    {
        $filePath = '/home/user/Music/Artist Name/1999-2000 Album Name/01 Track Title.mp3';
        $result = $this->filePathAnalyzer->extractPathInformation($filePath);

        // Should extract the first year found (1999)
        $this->assertEquals('Artist Name', $result['artist']);
        $this->assertEquals('-2000 Album Name', $result['album']); // Remaining part after year extraction
        $this->assertEquals(1999, $result['year']);
        $this->assertEquals('01', $result['track_number']);
    }

    public function testExtractPathInformationWithYearInMiddle(): void
    {
        $filePath = '/home/user/Music/Artist Name/Album Name 1999 Edition/01 Track Title.mp3';
        $result = $this->filePathAnalyzer->extractPathInformation($filePath);

        $this->assertEquals('Artist Name', $result['artist']);
        $this->assertEquals('Album Name 1999 Edition', $result['album']); // Year is not extracted as year in this case
        $this->assertNull($result['year']);
        $this->assertEquals('01', $result['track_number']);
    }

    public function testExtractPathInformationWithEmptyPath(): void
    {
        $filePath = '';
        $result = $this->filePathAnalyzer->extractPathInformation($filePath);

        $this->assertEquals('', $result['artist']); // Empty string, not null
        $this->assertNull($result['album']);
        $this->assertNull($result['year']);
        $this->assertNull($result['track_number']);
        $this->assertCount(1, $result['directory_structure']); // Contains one empty string
        $this->assertEquals('', $result['directory_structure'][0]); // First element is empty string
    }

    public function testExtractPathInformationWithWindowsPath(): void
    {
        $filePath = 'C:\Users\user\Music\Artist Name\Album Name\01 Track Title.mp3';
        $result = $this->filePathAnalyzer->extractPathInformation($filePath);

        $this->assertEquals('.', $result['artist']); // First part after splitting
        $this->assertNull($result['album']); // No second part
        $this->assertNull($result['year']);
        $this->assertEquals('01', $result['track_number']);
    }

    public function testExtractPathInformationWithNetworkPath(): void
    {
        $filePath = '//server/share/Music/Artist Name/Album Name/01 Track Title.mp3';
        $result = $this->filePathAnalyzer->extractPathInformation($filePath);

        $this->assertEquals('Artist Name', $result['artist']); // First part after Music
        $this->assertEquals('Album Name', $result['album']); // Second part after Music
        $this->assertNull($result['year']);
        $this->assertEquals('01', $result['track_number']);
    }
}
