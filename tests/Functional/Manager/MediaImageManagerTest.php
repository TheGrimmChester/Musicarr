<?php

declare(strict_types=1);

namespace App\Tests\Functional\Manager;

use App\Client\SpotifyScrapingClient;
use App\Client\SpotifyWebApiClient;
use App\Configuration\ConfigurationService;
use App\Entity\Artist;
use App\Entity\Library;
use App\Manager\MediaImageManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class MediaImageManagerTest extends KernelTestCase
{
    private ContainerInterface $container;
    private MediaImageManager $mediaImageManager;
    private EntityManagerInterface $entityManager;
    private ConfigurationService $configurationService;
    private string $testDir;

    protected function setUp(): void
    {
        // Set required environment variable for tests
        putenv('MUSICBRAINZ_USER_AGENT=Test-Application/1.0.0 (test@example.com)');

        self::bootKernel();
        $this->container = $this->getContainer();

        $this->mediaImageManager = $this->container->get(MediaImageManager::class);
        $this->entityManager = $this->container->get(EntityManagerInterface::class);
        $this->configurationService = $this->container->get(ConfigurationService::class);

        $this->testDir = sys_get_temp_dir() . '/media_image_manager_func_test_' . uniqid();
        mkdir($this->testDir, 0777, true);

        // Create test metadata directory
        $metadataDir = $this->testDir . '/metadata/artists';
        mkdir($metadataDir, 0777, true);

        // Clean up any existing test data
        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $this->cleanupTestData();

        if (is_dir($this->testDir)) {
            $this->removeDirectory($this->testDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private function cleanupTestData(): void
    {
        try {
            // Clean up any test artists and libraries
            $artistRepo = $this->entityManager->getRepository(Artist::class);
            $libraryRepo = $this->entityManager->getRepository(Library::class);

            $testArtists = $artistRepo->createQueryBuilder('a')
                ->where('a.mbid LIKE :mbid')
                ->setParameter('mbid', '8e68819d-71be-4e7d-b41d-f1df81b01d3f-%')
                ->getQuery()
                ->getResult();

            foreach ($testArtists as $artist) {
                $this->entityManager->remove($artist);
            }

            $testLibraries = $libraryRepo->createQueryBuilder('l')
                ->where('l.name = :name')
                ->setParameter('name', 'Test Library')
                ->getQuery()
                ->getResult();

            foreach ($testLibraries as $library) {
                $this->entityManager->remove($library);
            }

            $this->entityManager->flush();
        } catch (Exception $e) {
            // Ignore cleanup errors
        }
    }

    public function testDownloadAndStoreArtistImageWithRealDependencies()
    {
        $artistName = 'Test Artist Functional';
        $mbid = '8e68819d-71be-4e7d-b41d-f1df81b01d3f-1';

        // Create test library
        $library = new Library();
        $library->setName('Test Library');
        $library->setPath($this->testDir . '/library');
        $this->entityManager->persist($library);

        // Create test artist
        $artist = new Artist();
        $artist->setName($artistName);
        $artist->setMbid($mbid);
        $artist->setArtistFolderPath($this->testDir . '/library/' . $artistName);
        $this->entityManager->persist($artist);

        $this->entityManager->flush();

        // Mock the configuration to disable save_in_library for this test
        $this->configurationService->set('metadata.save_in_library', false);
        $this->configurationService->set('metadata.base_dir', $this->testDir . '/metadata');

        // Mock HTTP client to return a fake image
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn('fake image data');
        $response->method('getHeaders')->willReturn(['content-type' => ['image/jpeg']]);
        $httpClient->method('request')->willReturn($response);

        // Create a test instance with mocked HTTP client
        $testMediaImageManager = new MediaImageManager(
            $this->container->get('logger'),
            $this->testDir,
            $this->container->get('translator'),
            $httpClient,
            $this->container->get('App\Client\SpotifyScrapingClient'),
            $this->container->get('App\Client\SpotifyWebApiClient'),
            $this->configurationService,
            $this->container->get('App\File\FileSanitizer')
        );

        // Mock Spotify clients to return image URL
        $spotifyWebApiClient = $this->createMock(SpotifyWebApiClient::class);
        $spotifyWebApiClient->method('searchArtist')->willReturn(null);

        $spotifyScrapingClient = $this->createMock(SpotifyScrapingClient::class);
        $spotifyScrapingClient->method('getArtistImageUrl')->willReturn('https://example.com/test-artist.jpg');

        // Use reflection to set mocked clients
        $reflection = new ReflectionClass($testMediaImageManager);
        $spotifyWebApiClientProperty = $reflection->getProperty('spotifyWebApiClient');
        $spotifyWebApiClientProperty->setAccessible(true);
        $spotifyWebApiClientProperty->setValue($testMediaImageManager, $spotifyWebApiClient);

        $spotifyScrapingClientProperty = $reflection->getProperty('spotifyScrapingClient');
        $spotifyScrapingClientProperty->setAccessible(true);
        $spotifyScrapingClientProperty->setValue($testMediaImageManager, $spotifyScrapingClient);

        // Test the method
        $result = $testMediaImageManager->downloadAndStoreArtistImage(
            $artistName,
            $mbid,
            $mbid
        );

        // Assert that the method returned a path
        $this->assertNotNull($result);
        $this->assertStringContainsString('/metadata/artists/', $result);

        // Check that the file was actually created
        $expectedFile = $this->testDir . '/metadata/artists/' . $mbid . '.jpg';
        $this->assertFileExists($expectedFile);

        // Check file size (should be greater than 0)
        $this->assertGreaterThan(0, filesize($expectedFile));

        // Clean up
        $this->entityManager->remove($artist);
        $this->entityManager->remove($library);
        $this->entityManager->flush();
    }

    public function testDownloadAndStoreArtistImageWithLibrarySaveEnabled()
    {
        $artistName = 'Test Artist Library';
        $mbid = '8e68819d-71be-4e7d-b41d-f1df81b01d3f-2';
        $libraryPath = $this->testDir . '/library';

        // Create test library
        $library = new Library();
        $library->setName('Test Library');
        $library->setPath($libraryPath);
        $this->entityManager->persist($library);

        // Create test artist
        $artist = new Artist();
        $artist->setName($artistName);
        $artist->setMbid($mbid);
        $artist->setArtistFolderPath($libraryPath . '/' . $artistName);
        $this->entityManager->persist($artist);

        $this->entityManager->flush();

        // Enable save_in_library
        $this->configurationService->set('metadata.save_in_library', true);

        // Mock HTTP client to return a fake image
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn('fake image data');
        $response->method('getHeaders')->willReturn(['content-type' => ['image/jpeg']]);
        $httpClient->method('request')->willReturn($response);

        // Create a test instance with mocked HTTP client
        $testMediaImageManager = new MediaImageManager(
            $this->container->get('logger'),
            $this->testDir,
            $this->container->get('translator'),
            $httpClient,
            $this->container->get('App\Client\SpotifyScrapingClient'),
            $this->container->get('App\Client\SpotifyWebApiClient'),
            $this->configurationService,
            $this->container->get('App\File\FileSanitizer')
        );

        // Mock Spotify clients to return image URL
        $spotifyWebApiClient = $this->createMock(SpotifyWebApiClient::class);
        $spotifyWebApiClient->method('searchArtist')->willReturn(null);

        $spotifyScrapingClient = $this->createMock(SpotifyScrapingClient::class);
        $spotifyScrapingClient->method('getArtistImageUrl')->willReturn('https://example.com/test-artist.jpg');

        // Use reflection to set mocked clients
        $reflection = new ReflectionClass($testMediaImageManager);
        $spotifyWebApiClientProperty = $reflection->getProperty('spotifyWebApiClient');
        $spotifyWebApiClientProperty->setAccessible(true);
        $spotifyWebApiClientProperty->setValue($testMediaImageManager, $spotifyWebApiClient);

        $spotifyScrapingClientProperty = $reflection->getProperty('spotifyScrapingClient');
        $spotifyScrapingClientProperty->setAccessible(true);
        $spotifyScrapingClientProperty->setValue($testMediaImageManager, $spotifyScrapingClient);

        // Test the method with library save enabled
        $result = $testMediaImageManager->downloadAndStoreArtistImage(
            $artistName,
            $mbid,
            $mbid,
            false,
            null,
            $libraryPath,
            $artist->getId()
        );

        // Assert that the method returned a path
        $this->assertNotNull($result);
        $this->assertStringContainsString('/media/artist/', $result);

        // Check that the file was actually created in the library
        $expectedFile = $libraryPath . '/' . $mbid . '.jpg';
        $this->assertFileExists($expectedFile);

        // Check file size (should be greater than 0)
        $this->assertGreaterThan(0, filesize($expectedFile));

        // Clean up
        $this->entityManager->remove($artist);
        $this->entityManager->remove($library);
        $this->entityManager->flush();
    }

    public function testDownloadAndStoreArtistImageWithExistingImage()
    {
        $artistName = 'Test Artist Existing';
        $mbid = '8e68819d-71be-4e7d-b41d-f1df81b01d3f-3';

        // Create test library
        $library = new Library();
        $library->setName('Test Library');
        $library->setPath($this->testDir . '/library');
        $this->entityManager->persist($library);

        // Create test artist
        $artist = new Artist();
        $artist->setName($artistName);
        $artist->setMbid($mbid);
        $artist->setArtistFolderPath($this->testDir . '/library/' . $artistName);
        $this->entityManager->persist($artist);

        $this->entityManager->flush();

        // Disable save_in_library for this test
        $this->configurationService->set('metadata.save_in_library', false);
        $this->configurationService->set('metadata.base_dir', $this->testDir . '/metadata');

        // Create an existing image file
        $existingImagePath = $this->testDir . '/metadata/artists/' . $mbid . '.jpg';
        $dir = \dirname($existingImagePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($existingImagePath, 'fake image content');

        // Mock HTTP client (should not be called since image exists)
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('request');

        // Create a test instance with mocked HTTP client
        $testMediaImageManager = new MediaImageManager(
            $this->container->get('logger'),
            $this->testDir,
            $this->container->get('translator'),
            $httpClient,
            $this->container->get('App\Client\SpotifyScrapingClient'),
            $this->container->get('App\Client\SpotifyWebApiClient'),
            $this->configurationService,
            $this->container->get('App\File\FileSanitizer')
        );

        // Mock Spotify clients to return no image URL (so it falls back to existing image)
        $spotifyWebApiClient = $this->createMock(SpotifyWebApiClient::class);
        $spotifyWebApiClient->method('searchArtist')->willReturn(null);

        $spotifyScrapingClient = $this->createMock(SpotifyScrapingClient::class);
        $spotifyScrapingClient->method('getArtistImageUrl')->willReturn(null);

        // Use reflection to set mocked clients
        $reflection = new ReflectionClass($testMediaImageManager);
        $spotifyWebApiClientProperty = $reflection->getProperty('spotifyWebApiClient');
        $spotifyWebApiClientProperty->setAccessible(true);
        $spotifyWebApiClientProperty->setValue($testMediaImageManager, $spotifyWebApiClient);

        $spotifyScrapingClientProperty = $reflection->getProperty('spotifyScrapingClient');
        $spotifyScrapingClientProperty->setAccessible(true);
        $spotifyScrapingClientProperty->setValue($testMediaImageManager, $spotifyScrapingClient);

        // Test the method - since the fake image won't pass validation, it should return null
        // This tests the fallback behavior when no valid image is found
        $result = $testMediaImageManager->downloadAndStoreArtistImage(
            $artistName,
            $mbid,
            $mbid
        );

        // Since the fake image doesn't pass validation, the method should return null
        $this->assertNull($result);

        // Clean up
        $this->entityManager->remove($artist);
        $this->entityManager->remove($library);
        $this->entityManager->flush();
    }

    public function testDownloadAndStoreArtistImageWithForceRedownload()
    {
        $artistName = 'Test Artist Force';
        $mbid = '8e68819d-71be-4e7d-b41d-f1df81b01d3f-4';

        // Create test library
        $library = new Library();
        $library->setName('Test Library');
        $library->setPath($this->testDir . '/library');
        $this->entityManager->persist($library);

        // Create test artist
        $artist = new Artist();
        $artist->setName($artistName);
        $artist->setMbid($mbid);
        $artist->setArtistFolderPath($this->testDir . '/library/' . $artistName);
        $this->entityManager->persist($artist);

        $this->entityManager->flush();

        // Disable save_in_library for this test
        $this->configurationService->set('metadata.save_in_library', false);
        $this->configurationService->set('metadata.base_dir', $this->testDir . '/metadata');

        // Create an existing image file
        $existingImagePath = $this->testDir . '/metadata/artists/' . $mbid . '.jpg';
        $dir = \dirname($existingImagePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($existingImagePath, 'old image data');

        // Mock HTTP client to return a new fake image
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn('fake image data');
        $response->method('getHeaders')->willReturn(['content-type' => ['image/jpeg']]);
        $httpClient->method('request')->willReturn($response);

        // Create a test instance with mocked HTTP client
        $testMediaImageManager = new MediaImageManager(
            $this->container->get('logger'),
            $this->testDir,
            $this->container->get('translator'),
            $httpClient,
            $this->container->get('App\Client\SpotifyScrapingClient'),
            $this->container->get('App\Client\SpotifyWebApiClient'),
            $this->configurationService,
            $this->container->get('App\File\FileSanitizer')
        );

        // Mock Spotify clients to return image URL
        $spotifyWebApiClient = $this->createMock(SpotifyWebApiClient::class);
        $spotifyWebApiClient->method('searchArtist')->willReturn(null);

        $spotifyScrapingClient = $this->createMock(SpotifyScrapingClient::class);
        $spotifyScrapingClient->method('getArtistImageUrl')->willReturn('https://example.com/test-artist.jpg');

        // Use reflection to set mocked clients
        $reflection = new ReflectionClass($testMediaImageManager);
        $spotifyWebApiClientProperty = $reflection->getProperty('spotifyWebApiClient');
        $spotifyWebApiClientProperty->setAccessible(true);
        $spotifyWebApiClientProperty->setValue($testMediaImageManager, $spotifyWebApiClient);

        $spotifyScrapingClientProperty = $reflection->getProperty('spotifyScrapingClient');
        $spotifyScrapingClientProperty->setAccessible(true);
        $spotifyScrapingClientProperty->setValue($testMediaImageManager, $spotifyScrapingClient);

        // Test the method with force redownload
        $result = $testMediaImageManager->downloadAndStoreArtistImage(
            $artistName,
            $mbid,
            $mbid,
            true // forceRedownload = true
        );

        // Assert that the method returned a path
        $this->assertNotNull($result);
        $this->assertStringContainsString('/metadata/artists/', $result);

        // Check that the file was updated (should contain new data)
        $this->assertFileExists($existingImagePath);
        $fileContent = file_get_contents($existingImagePath);
        $this->assertNotEquals('old image data', $fileContent);

        // Clean up
        $this->entityManager->remove($artist);
        $this->entityManager->remove($library);
        $this->entityManager->flush();
    }

    private function createFakeImageData(): string
    {
        // Create a simple fake image data that will pass basic validation
        return 'fake image data';
    }
}
