<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Repository\LibraryRepository;
use App\Statistic\StatisticsService;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HomeControllerTest extends WebTestCase
{
    private StatisticsService|MockObject $statisticsService;
    private LibraryRepository|MockObject $libraryRepository;
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Mock the StatisticsService
        $this->statisticsService = $this->createMock(StatisticsService::class);

        // Mock the LibraryRepository
        $this->libraryRepository = $this->createMock(LibraryRepository::class);

        // Replace the services in the container
        self::getContainer()->set(StatisticsService::class, $this->statisticsService);
        self::getContainer()->set(LibraryRepository::class, $this->libraryRepository);
    }

    public function testIndexActionRendersCorrectly(): void
    {
        // Mock the statistics data
        $mockStats = [
            'artists' => 150,
            'albums' => 500,
            'singles' => 50,
            'tracks' => 5000,
            'libraries' => 3,
            'downloaded_albums' => 450,
            'downloaded_singles' => 45,
            'downloaded_tracks' => 4500,
            'album_completion_rate' => 90.0,
            'single_completion_rate' => 90.0,
            'track_completion_rate' => 90.0,
        ];

        $this->statisticsService
            ->expects($this->once())
            ->method('getStatisticsSummary')
            ->willReturn($mockStats);

        // Mock enabled libraries count
        $this->libraryRepository
            ->expects($this->once())
            ->method('count')
            ->with(['enabled' => true])
            ->willReturn(3);

        // Make a request to the home page
        $crawler = $this->client->request('GET', '/');

        // Assert the response is successful
        $this->assertResponseIsSuccessful();

        // Assert the page title contains expected content
        $this->assertSelectorTextContains('h1', 'Dashboard');

        // Assert that statistics are displayed
        $this->assertSelectorTextContains('body', '150'); // Total artists
        $this->assertSelectorTextContains('body', '500'); // Total albums
        $this->assertSelectorTextContains('body', '5000'); // Total tracks
        $this->assertSelectorTextContains('body', '90'); // Completion rate
    }

    public function testIndexActionWithEmptyStatistics(): void
    {
        // Mock empty statistics
        $mockStats = [
            'artists' => 0,
            'albums' => 0,
            'singles' => 0,
            'tracks' => 0,
            'libraries' => 0,
            'downloaded_albums' => 0,
            'downloaded_singles' => 0,
            'downloaded_tracks' => 0,
            'album_completion_rate' => 0.0,
            'single_completion_rate' => 0.0,
            'track_completion_rate' => 0.0,
        ];

        $this->statisticsService
            ->expects($this->once())
            ->method('getStatisticsSummary')
            ->willReturn($mockStats);

        // Mock enabled libraries count
        $this->libraryRepository
            ->expects($this->once())
            ->method('count')
            ->with(['enabled' => true])
            ->willReturn(0);

        // Make a request to the home page
        $crawler = $this->client->request('GET', '/');

        // Assert the response is successful
        $this->assertResponseIsSuccessful();

        // Assert that zero values are displayed
        $this->assertSelectorTextContains('body', '0');
    }

    public function testIndexActionTemplateRendering(): void
    {
        // Mock basic statistics
        $mockStats = [
            'artists' => 10,
            'albums' => 20,
            'singles' => 5,
            'tracks' => 200,
            'libraries' => 1,
            'downloaded_albums' => 15,
            'downloaded_singles' => 3,
            'downloaded_tracks' => 150,
            'album_completion_rate' => 75.0,
            'single_completion_rate' => 60.0,
            'track_completion_rate' => 75.0,
        ];

        $this->statisticsService
            ->expects($this->once())
            ->method('getStatisticsSummary')
            ->willReturn($mockStats);

        // Mock enabled libraries count
        $this->libraryRepository
            ->expects($this->once())
            ->method('count')
            ->with(['enabled' => true])
            ->willReturn(1);

        // Make a request to the home page
        $crawler = $this->client->request('GET', '/');

        // Assert the response is successful
        $this->assertResponseIsSuccessful();

        // Assert that the correct template is used
        $this->assertSelectorExists('body');

        // Assert that the page contains expected content structure
        $this->assertSelectorExists('.stats-card');

        // Assert that statistics sections exist
        $this->assertSelectorExists('.card');
    }

    public function testIndexActionResponseHeaders(): void
    {
        // Mock basic statistics
        $mockStats = [
            'artists' => 5,
            'albums' => 10,
            'singles' => 2,
            'tracks' => 100,
            'libraries' => 1,
            'downloaded_albums' => 8,
            'downloaded_singles' => 1,
            'downloaded_tracks' => 80,
            'album_completion_rate' => 80.0,
            'single_completion_rate' => 50.0,
            'track_completion_rate' => 80.0,
        ];

        $this->statisticsService
            ->expects($this->once())
            ->method('getStatisticsSummary')
            ->willReturn($mockStats);

        // Mock enabled libraries count
        $this->libraryRepository
            ->expects($this->once())
            ->method('count')
            ->with(['enabled' => true])
            ->willReturn(1);

        // Make a request to the home page
        $this->client->request('GET', '/');

        // Assert response headers
        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    public function testIndexActionWithLargeStatistics(): void
    {
        // Mock large statistics
        $mockStats = [
            'artists' => 10000,
            'albums' => 50000,
            'singles' => 5000,
            'tracks' => 500000,
            'libraries' => 10,
            'downloaded_albums' => 45000,
            'downloaded_singles' => 4500,
            'downloaded_tracks' => 450000,
            'album_completion_rate' => 90.0,
            'single_completion_rate' => 90.0,
            'track_completion_rate' => 90.0,
        ];

        $this->statisticsService
            ->expects($this->once())
            ->method('getStatisticsSummary')
            ->willReturn($mockStats);

        // Mock enabled libraries count
        $this->libraryRepository
            ->expects($this->once())
            ->method('count')
            ->with(['enabled' => true])
            ->willReturn(10);

        // Make a request to the home page
        $crawler = $this->client->request('GET', '/');

        // Assert the response is successful
        $this->assertResponseIsSuccessful();

        // Assert that large numbers are displayed
        $this->assertSelectorTextContains('body', '10000'); // Large artist count
        $this->assertSelectorTextContains('body', '50000'); // Large album count
        $this->assertSelectorTextContains('body', '500000'); // Large track count
    }
}
