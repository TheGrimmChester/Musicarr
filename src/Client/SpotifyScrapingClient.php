<?php

declare(strict_types=1);

namespace App\Client;

use DOMDocument;
use DOMXPath;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SpotifyScrapingClient
{
    private const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36';
    private const GOOGLE_SEARCH_URL = 'https://www.google.com/search';
    private const DUCKDUCKGO_SEARCH_URL = 'https://duckduckgo.com/html';
    private const SPOTIFY_ARTIST_URL_PATTERN = '#https://open\.spotify\.com/artist/([a-zA-Z0-9]+)#';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire('%env(MUSICBRAINZ_USER_AGENT)%')]
        private ?string $userAgent = null
    ) {
    }

    /**
     * Get the user agent for HTTP requests.
     */
    private function getUserAgent(): string
    {
        return $this->userAgent ?? self::DEFAULT_USER_AGENT;
    }

    /**
     * Get Spotify artist ID by searching for the artist.
     */
    public function getSpotifyArtistId(string $artistName): ?string
    {
        // Try direct Spotify search first
        $encodedArtistName = urlencode($artistName);
        $spotifyUrl = "https://open.spotify.com/search/{$encodedArtistName}";

        try {
            $html = $this->httpClient->request('GET', $spotifyUrl)->getContent();

            // Look for artist ID in the HTML
            if (preg_match('/spotify:artist:([a-zA-Z0-9]{22})/', $html, $matches)) {
                return $matches[1];
            }
        } catch (Exception $e) {
            $this->logger->warning('Failed to search Spotify directly: ' . $e->getMessage());
        }

        // If direct search fails, try Google search
        $googleQuery = urlencode("site:open.spotify.com artist {$artistName}");
        $googleUrl = "https://www.google.com/search?q={$googleQuery}";

        try {
            $html = $this->httpClient->request('GET', $googleUrl)->getContent();

            // Look for Spotify artist URLs in Google results
            if (preg_match('/https:\/\/open\.spotify\.com\/artist\/([a-zA-Z0-9]{22})/', $html, $matches)) {
                return $matches[1];
            }
        } catch (Exception $e) {
            $this->logger->warning('Failed to search Google: ' . $e->getMessage());
        }

        // If Google fails, try DuckDuckGo
        $ddgQuery = urlencode("site:open.spotify.com artist {$artistName}");
        $ddgUrl = "https://duckduckgo.com/html/?q={$ddgQuery}";

        try {
            $html = $this->httpClient->request('GET', $ddgUrl)->getContent();

            // Look for Spotify artist URLs in DuckDuckGo results
            if (preg_match('/https:\/\/open\.spotify\.com\/artist\/([a-zA-Z0-9]{22})/', $html, $matches)) {
                return $matches[1];
            }
        } catch (Exception $e) {
            $this->logger->warning('Failed to search DuckDuckGo: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Get known artist IDs for testing purposes.
     */
    private function getKnownArtistId(string $artistName): ?string
    {
        $knownArtists = [
            'Adele' => '4dpARuHxo51G3z768sgnrY',
            'Ed Sheeran' => '6eUKZXaKkcviH0Ku9w2n3V',
            'Taylor Swift' => '06HL4z0CvFAxyc27GXpf02',
            'The Beatles' => '3WrFJ7ztbogyGnTHbHJFl2',
            'Queen' => '1dfeR4HaWDbWqFHLkxsg1d',
            'Coldplay' => '4gzpq5DPGxSnKTe4SA8HAU',
            'Radiohead' => '4Z8W4fKeB5YxbusRsdQVPb',
            'Pink Floyd' => '0k17h0D3J5VfsdmQ1iZtE9',
            'Led Zeppelin' => '36QJpDe2go2KgaRleHCDTp',
            'AC/DC' => '711MCceyCBcFnzjGY4Q7Un',
        ];

        $normalizedName = mb_strtolower(mb_trim($artistName));
        foreach ($knownArtists as $knownName => $spotifyId) {
            if (mb_strtolower($knownName) === $normalizedName) {
                $this->logger->info("Found known Spotify artist ID for '{$artistName}': {$spotifyId}");

                return $spotifyId;
            }
        }

        return null;
    }

    /**
     * Search Spotify directly for artist ID.
     */
    private function searchSpotifyDirectly(string $artistName): ?string
    {
        try {
            // Use Spotify's search endpoint (public, no auth needed for basic search)
            $query = urlencode($artistName);
            $spotifySearchUrl = "https://open.spotify.com/search/{$query}";

            $response = $this->httpClient->request('GET', $spotifySearchUrl, [
                'headers' => [
                    'User-Agent' => $this->getUserAgent(),
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Connection' => 'keep-alive',
                ],
                'timeout' => 30,
            ]);

            $html = $response->getContent();

            // Look for artist links in the search results
            $patterns = [
                '#/artist/([a-zA-Z0-9]{22})#', // Spotify artist IDs are 22 characters
                '#open\.spotify\.com/artist/([a-zA-Z0-9]{22})#',
                '#spotify\.com/artist/([a-zA-Z0-9]{22})#',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $html, $matches)) {
                    // Get the first match (most relevant)
                    if (!empty($matches[1])) {
                        $artistId = $matches[1][0];
                        $this->logger->info("Found Spotify artist ID directly for '{$artistName}': {$artistId}");

                        return $artistId;
                    }
                }
            }

            $this->logger->info("No Spotify artist ID found directly for: {$artistName}");

            return null;
        } catch (Exception $e) {
            $this->logger->error("Error searching Spotify directly for artist ID for '{$artistName}': " . $e->getMessage());

            return null;
        }
    }

    /**
     * Search Google for Spotify artist ID.
     */
    private function searchGoogleForSpotifyId(string $artistName): ?string
    {
        try {
            $query = urlencode("site:open.spotify.com artist \"$artistName\"");
            $googleUrl = self::GOOGLE_SEARCH_URL . "?q={$query}";

            $response = $this->httpClient->request('GET', $googleUrl, [
                'headers' => [
                    'User-Agent' => $this->getUserAgent(),
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Connection' => 'keep-alive',
                    'Cache-Control' => 'no-cache',
                    'Pragma' => 'no-cache',
                ],
                'timeout' => 30,
                'max_redirects' => 0, // Don't follow redirects to avoid consent pages
            ]);

            $html = $response->getContent();

            // Use regex to find spotify artist url in the search results
            if (preg_match(self::SPOTIFY_ARTIST_URL_PATTERN, $html, $matches)) {
                $artistId = $matches[1];
                $this->logger->info("Found Spotify artist ID for '{$artistName}': {$artistId}");

                return $artistId;
            }

            // Try alternative patterns
            $alternativePatterns = [
                '#open\.spotify\.com/artist/([a-zA-Z0-9]+)#',
                '#spotify\.com/artist/([a-zA-Z0-9]+)#',
                '#/artist/([a-zA-Z0-9]+)#',
            ];

            foreach ($alternativePatterns as $pattern) {
                if (preg_match($pattern, $html, $matches)) {
                    $artistId = $matches[1];
                    $this->logger->info("Found Spotify artist ID with alternative pattern for '{$artistName}': {$artistId}");

                    return $artistId;
                }
            }

            $this->logger->info("No Spotify artist ID found on Google for: {$artistName}");

            return null;
        } catch (Exception $e) {
            $this->logger->error("Error searching Google for Spotify artist ID for '{$artistName}': " . $e->getMessage());

            return null;
        }
    }

    /**
     * Search DuckDuckGo for Spotify artist ID.
     */
    private function searchDuckDuckGoForSpotifyId(string $artistName): ?string
    {
        try {
            $query = urlencode("site:open.spotify.com artist \"$artistName\"");
            $duckduckgoUrl = self::DUCKDUCKGO_SEARCH_URL . "?q={$query}";

            $response = $this->httpClient->request('GET', $duckduckgoUrl, [
                'headers' => [
                    'User-Agent' => $this->getUserAgent(),
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Accept-Encoding' => 'gzip, deflate',
                    'Connection' => 'keep-alive',
                ],
                'timeout' => 30,
            ]);

            $html = $response->getContent();

            // Use regex to find spotify artist url in the search results
            if (preg_match(self::SPOTIFY_ARTIST_URL_PATTERN, $html, $matches)) {
                $artistId = $matches[1];
                $this->logger->info("Found Spotify artist ID on DuckDuckGo for '{$artistName}': {$artistId}");

                return $artistId;
            }

            // Try alternative patterns
            $alternativePatterns = [
                '#open\.spotify\.com/artist/([a-zA-Z0-9]+)#',
                '#spotify\.com/artist/([a-zA-Z0-9]+)#',
                '#/artist/([a-zA-Z0-9]+)#',
            ];

            foreach ($alternativePatterns as $pattern) {
                if (preg_match($pattern, $html, $matches)) {
                    $artistId = $matches[1];
                    $this->logger->info("Found Spotify artist ID with alternative pattern on DuckDuckGo for '{$artistName}': {$artistId}");

                    return $artistId;
                }
            }

            $this->logger->info("No Spotify artist ID found on DuckDuckGo for: {$artistName}");

            return null;
        } catch (Exception $e) {
            $this->logger->error("Error searching DuckDuckGo for Spotify artist ID for '{$artistName}': " . $e->getMessage());

            return null;
        }
    }

    /**
     * Get artist image URL from Spotify artist page og:image metadata.
     */
    public function getSpotifyArtistImageUrl(string $spotifyArtistId): ?string
    {
        try {
            $artistUrl = "https://open.spotify.com/artist/{$spotifyArtistId}";

            $response = $this->httpClient->request('GET', $artistUrl, [
                'headers' => [
                    'User-Agent' => $this->getUserAgent(),
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Connection' => 'keep-alive',
                ],
                'timeout' => 30,
            ]);

            $html = $response->getContent();

            // Parse HTML to find og:image meta tag
            $doc = new DOMDocument();
            // Suppress warnings due to malformed HTML
            libxml_use_internal_errors(true);
            $doc->loadHTML($html);
            libxml_clear_errors();

            $xpath = new DOMXPath($doc);

            // Look for the og:image meta property
            $nodes = $xpath->query("//meta[@property='og:image']");
            if ($nodes->length > 0) {
                $imageUrl = $nodes->item(0)->getAttribute('content');
                if ($imageUrl && !empty(mb_trim($imageUrl))) {
                    $this->logger->info("Found artist image URL for Spotify ID '{$spotifyArtistId}': {$imageUrl}");

                    return $imageUrl;
                }
            }

            $this->logger->info("No og:image found for Spotify ID: {$spotifyArtistId}");

            return null;
        } catch (Exception $e) {
            $this->logger->error("Error fetching artist image for Spotify ID '{$spotifyArtistId}': " . $e->getMessage());

            return null;
        }
    }

    /**
     * Get artist image URL directly by artist name.
     */
    public function getArtistImageUrl(string $artistName): ?string
    {
        $spotifyId = $this->getSpotifyArtistId($artistName);

        if (!$spotifyId) {
            return null;
        }

        return $this->getSpotifyArtistImageUrl($spotifyId);
    }

    /**
     * Get artist information including image URL.
     */
    public function getArtistInfo(string $artistName): ?array
    {
        $spotifyId = $this->getSpotifyArtistId($artistName);

        if (!$spotifyId) {
            return null;
        }

        $imageUrl = $this->getSpotifyArtistImageUrl($spotifyId);

        return [
            'name' => $artistName,
            'spotify_id' => $spotifyId,
            'image_url' => $imageUrl,
            'spotify_url' => "https://open.spotify.com/artist/{$spotifyId}",
        ];
    }
}
