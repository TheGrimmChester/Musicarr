<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api/cache')]
class CacheApiController extends AbstractController
{
    public function __construct(
        private HttpClientInterface $httpClient
    ) {
    }

    #[Route('/demo', name: 'api_cache_demo', methods: ['GET'])]
    public function demo(): JsonResponse
    {
        $results = [];

        // Test different cache pools with real requests
        $testUrls = [
            'artist' => [
                'url' => 'https://musicbrainz.org/ws/2/artist?query=Beatles&fmt=json&limit=1',
                'description' => 'MusicBrainz Artist Search',
            ],
            'album' => [
                'url' => 'https://musicbrainz.org/ws/2/release?query=Abbey%20Road&fmt=json&limit=1',
                'description' => 'MusicBrainz Album Search',
            ],
            'general' => [
                'url' => 'https://httpbin.org/json',
                'description' => 'General HTTP Request',
            ],
        ];

        foreach ($testUrls as $type => $config) {
            // First request (should hit the API)
            $start1 = microtime(true);

            try {
                $response1 = $this->httpClient->request('GET', $config['url']);
                $content1 = $response1->getContent();
                $time1 = microtime(true) - $start1;
                $success1 = true;
            } catch (Exception $e) {
                $time1 = microtime(true) - $start1;
                $content1 = null;
                $success1 = false;
                $error1 = $e->getMessage();
            }

            // Second request (should be cached)
            $start2 = microtime(true);

            try {
                $response2 = $this->httpClient->request('GET', $config['url']);
                $content2 = $response2->getContent();
                $time2 = microtime(true) - $start2;
                $success2 = true;
            } catch (Exception $e) {
                $time2 = microtime(true) - $start2;
                $content2 = null;
                $success2 = false;
                $error2 = $e->getMessage();
            }

            $results[$type] = [
                'description' => $config['description'],
                'url' => $config['url'],
                'first_request' => [
                    'success' => $success1,
                    'time' => round($time1, 3),
                    'error' => $error1 ?? null,
                ],
                'second_request' => [
                    'success' => $success2,
                    'time' => round($time2, 3),
                    'error' => $error2 ?? null,
                ],
                'cache_hit' => $success1 && $success2 && $content1 === $content2,
                'performance_improvement' => $success1 && $success2 ? round(($time1 - $time2) / $time1 * 100, 1) : null,
            ];
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Cache demonstration completed',
            'results' => $results,
            'summary' => [
                'total_tests' => \count($results),
                'cache_hits' => \count(array_filter($results, fn ($r) => $r['cache_hit'])),
                'average_improvement' => round(
                    array_sum(array_filter(array_column($results, 'performance_improvement'))) /
                    \count(array_filter(array_column($results, 'performance_improvement'))),
                    1
                ),
            ],
        ]);
    }

    #[Route('/pools', name: 'api_cache_pools', methods: ['GET'])]
    public function pools(): JsonResponse
    {
        $pools = [
            'artist' => [
                'name' => 'Artist Cache',
                'duration' => '30 days',
                'description' => 'Artist info, search results from MusicBrainz/Last.fm',
                'patterns' => ['/artist/', 'artist.getinfo', 'spotify.com/artist'],
            ],
            'album' => [
                'name' => 'Album Cache',
                'duration' => '30 days',
                'description' => 'Album/release info from MusicBrainz',
                'patterns' => ['/release/', '/release-group/'],
            ],
            'track' => [
                'name' => 'Track Cache',
                'duration' => '30 days',
                'description' => 'Track/recording data from MusicBrainz',
                'patterns' => ['/recording/'],
            ],
            'cover' => [
                'name' => 'Cover Cache',
                'duration' => '90 days',
                'description' => 'Cover art from Cover Art Archive',
                'patterns' => ['coverartarchive.org', '/front-500'],
            ],
            'image' => [
                'name' => 'Image Cache',
                'duration' => '90 days',
                'description' => 'Artist images from Last.fm, Spotify',
                'patterns' => ['lastfm-img', 'spotify.*image', '.jpg', '.png', '.jpeg', '.gif'],
            ],
            'http' => [
                'name' => 'HTTP Cache',
                'duration' => '30 days',
                'description' => 'General HTTP requests (fallback)',
                'patterns' => ['All other GET requests'],
            ],
        ];

        return new JsonResponse([
            'success' => true,
            'pools' => $pools,
        ]);
    }
}
