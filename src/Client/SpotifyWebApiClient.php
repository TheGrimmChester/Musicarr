<?php

declare(strict_types=1);

namespace App\Client;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Minimal Spotify Web API client using Client Credentials flow
 * Docs: https://developer.spotify.com/documentation/web-api/reference/search.
 */
class SpotifyWebApiClient
{
    private const TOKEN_URL = 'https://accounts.spotify.com/api/token';
    private const API_BASE = 'https://api.spotify.com/v1';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private CacheInterface $cache,
        #[Autowire('%env(SPOTIFY_CLIENT_ID)%')]
        private ?string $clientId = null,
        #[Autowire('%env(SPOTIFY_CLIENT_SECRET)%')]
        private ?string $clientSecret = null,
        #[Autowire('%env(SPOTIFY_ACCESS_TOKEN)%')]
        private ?string $accessTokenOverride = null,
    ) {
    }

    private function getAccessToken(): ?string
    {
        // If a bearer token is explicitly provided (e.g. temporary token), use it directly
        if (!empty($this->accessTokenOverride)) {
            return $this->accessTokenOverride;
        }

        if (empty($this->clientId) || empty($this->clientSecret)) {
            $this->logger->warning('Spotify client credentials are not configured');

            return null;
        }

        return $this->cache->get('spotify_cc_token', function (ItemInterface $item) {
            $item->expiresAfter(3000); // ~50min to be safe (token lasts 3600s)

            $response = $this->httpClient->request('POST', self::TOKEN_URL, [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
                'timeout' => 15,
            ]);

            $status = $response->getStatusCode();
            if (200 !== $status) {
                $this->logger->error('Spotify token request failed: ' . $status);

                return null;
            }

            $data = $response->toArray(false);

            return $data['access_token'] ?? null;
        });
    }

    /**
     * Search for the best matching artist by name and return Spotify ID and image URL if available.
     */
    public function searchArtist(string $name): ?array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return null;
        }

        try {
            $data = $this->cache->get('spotify_artist_search_' . md5($name), function (ItemInterface $item) use ($name, $token) {
                $item->expiresAfter(31557600);

                $response = $this->httpClient->request('GET', self::API_BASE . '/search', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                    ],
                    'query' => [
                        'q' => \sprintf('"%s"', $name),
                        'type' => 'artist',
                        'limit' => 1,
                    ],
                    'timeout' => 15,
                ]);

                if (200 !== $response->getStatusCode()) {
                    throw new Exception($response->getContent(false), $response->getStatusCode());
                }

                return $response->toArray(false);
            });

            $items = $data['artists']['items'] ?? [];
            if (empty($items)) {
                return null;
            }

            $artist = $items[0];
            $imageUrl = null;
            if (!empty($artist['images'])) {
                // Pick the first (largest) image
                $imageUrl = $artist['images'][0]['url'] ?? null;
            }

            return [
                'id' => $artist['id'] ?? null,
                'name' => $artist['name'] ?? $name,
                'image_url' => $imageUrl,
                'popularity' => $artist['popularity'] ?? null,
                'genres' => $artist['genres'] ?? [],
                'followers' => $artist['followers']['total'] ?? null,
            ];
        } catch (Throwable $e) {
            $this->logger->error('Spotify artist search error: ' . $e->getMessage());

            return null;
        }
    }
}
