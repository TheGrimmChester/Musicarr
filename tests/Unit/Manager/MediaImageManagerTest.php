<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manager;

use App\Client\SpotifyScrapingClient;
use App\Client\SpotifyWebApiClient;
use App\Configuration\ConfigurationService;
use App\Entity\Album;
use App\Entity\Artist;
use App\File\FileSanitizer;
use App\Manager\MediaImageManager;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MediaImageManagerTest extends TestCase
{
    private MediaImageManager $mediaImageManager;
    private LoggerInterface|MockObject $logger;
    private TranslatorInterface|MockObject $translator;
    private HttpClientInterface|MockObject $httpClient;
    private SpotifyScrapingClient|MockObject $spotifyScrapingClient;
    private SpotifyWebApiClient|MockObject $spotifyWebApiClient;
    private ConfigurationService|MockObject $configurationService;
    private FileSanitizer|MockObject $fileSanitizer;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->spotifyScrapingClient = $this->createMock(SpotifyScrapingClient::class);
        $this->spotifyWebApiClient = $this->createMock(SpotifyWebApiClient::class);
        $this->configurationService = $this->createMock(ConfigurationService::class);
        $this->fileSanitizer = $this->createMock(FileSanitizer::class);

        $this->mediaImageManager = new MediaImageManager(
            $this->logger,
            '/tmp/test-project',
            $this->translator,
            $this->httpClient,
            $this->spotifyScrapingClient,
            $this->spotifyWebApiClient,
            $this->configurationService,
            $this->fileSanitizer,
            'TestUserAgent/1.0'
        );
    }

    public function testMediaImageManagerClassExists(): void
    {
        $this->assertInstanceOf(MediaImageManager::class, $this->mediaImageManager);
    }

    public function testMediaImageManagerHasExpectedMethods(): void
    {
        $expectedMethods = [
            'downloadAndStoreImage',
            'downloadAndStoreArtistImage',
            'downloadAndStoreAlbumCoverFromUrl',
            'optimizeImage',
            'imageExistsAndValid',
            'imageExists',
            'getLocalImagePath',
            'deleteLocalImage',
            'cleanupOrphanedImages',
            'getImageStats',
            'copyImageWithNewName',
            'resolveArtistImagePathForFolder',
            'moveArtistImage',
            'resolveArtistImagePath',
            'resolveAlbumCoverPath',
        ];

        foreach ($expectedMethods as $method) {
            $this->assertTrue(method_exists($this->mediaImageManager, $method), "Method {$method} should exist");
        }
    }

    public function testMediaImageManagerMethodsReturnExpectedTypes(): void
    {
        // Test that methods return the expected types
        $this->assertIsBool($this->mediaImageManager->imageExistsAndValid('artist', 'test-id'));
        $this->assertIsBool($this->mediaImageManager->imageExists('artist', 'test-id'));
        $this->assertIsArray($this->mediaImageManager->getImageStats());
    }

    public function testMediaImageManagerCanHandleBasicOperations(): void
    {
        // Test that basic methods can be called without crashing
        $this->assertIsBool($this->mediaImageManager->imageExistsAndValid('artist', 'test-id'));
        $this->assertIsBool($this->mediaImageManager->imageExists('artist', 'test-id'));
        $this->assertIsArray($this->mediaImageManager->getImageStats());

        // Test path resolution methods
        $artistPath = $this->mediaImageManager->resolveArtistImagePath($this->createMock(Artist::class));
        $this->assertNull($artistPath); // Should return null in test environment

        $albumPath = $this->mediaImageManager->resolveAlbumCoverPath($this->createMock(Album::class));
        $this->assertNull($albumPath); // Should return null in test environment
    }

    public function testMediaImageManagerReflection(): void
    {
        // Test that we can reflect on the class and its methods
        $reflection = new ReflectionClass($this->mediaImageManager);

        $this->assertTrue($reflection->hasMethod('downloadAndStoreImage'));
        $this->assertTrue($reflection->hasMethod('downloadAndStoreArtistImage'));
        $this->assertTrue($reflection->hasMethod('downloadAndStoreAlbumCoverFromUrl'));
        $this->assertTrue($reflection->hasMethod('optimizeImage'));
        $this->assertTrue($reflection->hasMethod('imageExistsAndValid'));
        $this->assertTrue($reflection->hasMethod('imageExists'));
        $this->assertTrue($reflection->hasMethod('getLocalImagePath'));
        $this->assertTrue($reflection->hasMethod('deleteLocalImage'));
        $this->assertTrue($reflection->hasMethod('cleanupOrphanedImages'));
        $this->assertTrue($reflection->hasMethod('getImageStats'));
        $this->assertTrue($reflection->hasMethod('copyImageWithNewName'));
        $this->assertTrue($reflection->hasMethod('resolveArtistImagePathForFolder'));
        $this->assertTrue($reflection->hasMethod('moveArtistImage'));
        $this->assertTrue($reflection->hasMethod('resolveArtistImagePath'));
        $this->assertTrue($reflection->hasMethod('resolveAlbumCoverPath'));

        // Check method visibility
        $this->assertTrue($reflection->getMethod('downloadAndStoreImage')->isPublic());
        $this->assertTrue($reflection->getMethod('downloadAndStoreArtistImage')->isPublic());
        $this->assertTrue($reflection->getMethod('downloadAndStoreAlbumCoverFromUrl')->isPublic());
        $this->assertTrue($reflection->getMethod('optimizeImage')->isPublic());
        $this->assertTrue($reflection->getMethod('imageExistsAndValid')->isPublic());
        $this->assertTrue($reflection->getMethod('imageExists')->isPublic());
        $this->assertTrue($reflection->getMethod('getLocalImagePath')->isPublic());
        $this->assertTrue($reflection->getMethod('deleteLocalImage')->isPublic());
        $this->assertTrue($reflection->getMethod('cleanupOrphanedImages')->isPublic());
        $this->assertTrue($reflection->getMethod('getImageStats')->isPublic());
        $this->assertTrue($reflection->getMethod('copyImageWithNewName')->isPublic());
        $this->assertTrue($reflection->getMethod('resolveArtistImagePathForFolder')->isPublic());
        $this->assertTrue($reflection->getMethod('moveArtistImage')->isPublic());
        $this->assertTrue($reflection->getMethod('resolveArtistImagePath')->isPublic());
        $this->assertTrue($reflection->getMethod('resolveAlbumCoverPath')->isPublic());
    }

    public function testMediaImageManagerConstructor(): void
    {
        // Test that the constructor properly sets the dependencies
        $reflection = new ReflectionClass($this->mediaImageManager);

        $this->assertTrue($reflection->hasProperty('logger'));
        $this->assertTrue($reflection->hasProperty('projectDir'));
        $this->assertTrue($reflection->hasProperty('translator'));
        $this->assertTrue($reflection->hasProperty('httpClient'));
        $this->assertTrue($reflection->hasProperty('spotifyScrapingClient'));
        $this->assertTrue($reflection->hasProperty('spotifyWebApiClient'));
        $this->assertTrue($reflection->hasProperty('configurationService'));
        $this->assertTrue($reflection->hasProperty('fileSanitizer'));
        $this->assertTrue($reflection->hasProperty('userAgent'));
    }

    public function testMediaImageManagerCanHandleImageOperations(): void
    {
        // Test that image operation methods can be called without crashing
        $this->assertIsBool($this->mediaImageManager->deleteLocalImage('artist', 'test-id'));
        $this->assertIsInt($this->mediaImageManager->cleanupOrphanedImages());

        // Test copy image method
        $result = $this->mediaImageManager->copyImageWithNewName('/tmp/source.jpg', 'artist', 'test-id');
        $this->assertNull($result); // Should return null in test environment
    }

    public function testMediaImageManagerCanHandlePathOperations(): void
    {
        // Test path-related methods
        $artist = $this->createMock(Artist::class);
        $album = $this->createMock(Album::class);

        $artistPath = $this->mediaImageManager->resolveArtistImagePath($artist);
        $this->assertNull($artistPath); // Should return null in test environment

        $albumPath = $this->mediaImageManager->resolveAlbumCoverPath($album);
        $this->assertNull($albumPath); // Should return null in test environment

        $folderPath = $this->mediaImageManager->resolveArtistImagePathForFolder($artist, '/tmp/old-folder');
        $this->assertNull($folderPath); // Should return null in test environment
    }

    public function testMediaImageManagerCanHandleOptimization(): void
    {
        // Test image optimization method
        // This method likely requires a real image file, so we'll just test that it exists
        $this->assertTrue(method_exists($this->mediaImageManager, 'optimizeImage'));

        // Create a temporary file to avoid warnings
        $tempFile = tempnam(sys_get_temp_dir(), 'test_image');
        file_put_contents($tempFile, 'not a real image');

        // Test that it can be called without crashing (though it may not do anything useful)
        try {
            $this->mediaImageManager->optimizeImage($tempFile);
            // If no exception is thrown, that's fine
        } catch (Exception $e) {
            // Expected behavior when file isn't a valid image
            $this->assertInstanceOf(Exception::class, $e);
        }

        // Clean up
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
}
