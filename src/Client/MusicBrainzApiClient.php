<?php

declare(strict_types=1);

namespace App\Client;

use App\Manager\MediaImageManager;
use Exception;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MusicBrainzApiClient
{
    private const API_BASE_URL = 'https://musicbrainz.org/ws/2';
    private const DEFAULT_USER_AGENT = 'Musicarr/1.0.0';

    public function __construct(
        private LoggerInterface $logger,
        private MediaImageManager $imageService,
        #[Autowire('@musicbrainz.client')]
        private HttpClientInterface $musicbrainzClient,
        #[Autowire('@coverart.client')]
        private HttpClientInterface $coverartClient,
        private CacheInterface $artistCache,
        private CacheInterface $albumCache,
        private CacheInterface $trackCache,
        private CacheInterface $coverCache,
        private CacheInterface $imageCache,
        private CacheInterface $httpCache,
        private MusicBrainzPaginationHelper $paginationHelper,
        private TranslatorInterface $translator,
        #[Autowire('%env(MUSICBRAINZ_USER_AGENT)%')]
        private ?string $userAgent = null
    ) {
    }

    /**
     * Get the user agent for MusicBrainz API requests.
     */
    private function getUserAgent(): string
    {
        return $this->userAgent ?? self::DEFAULT_USER_AGENT;
    }

    /**
     * Build a URL with pagination parameters.
     */
    private function buildUrlWithPagination(string $endpoint, array $params = [], int $offset = 0, int $limit = 100): string
    {
        $url = self::API_BASE_URL . $endpoint;

        // Add pagination parameters
        $params['offset'] = $offset;
        $params['limit'] = $limit;
        $params['fmt'] = 'json';

        // Build query string
        $queryParams = [];
        foreach ($params as $key => $value) {
            if (null !== $value && '' !== $value) {
                $queryParams[] = $key . '=' . urlencode((string) $value);
            }
        }

        if (!empty($queryParams)) {
            $url .= '?' . implode('&', $queryParams);
        }

        return $url;
    }

    /**
     * Determine which cache pool to use based on URL patterns
     * Order matters - more specific patterns should come first.
     */
    private function getCachePool(string $url): CacheInterface
    {
        // Cover art URLs (must come before general image check)
        if (preg_match('#coverartarchive\.org|/front-500#i', $url)) {
            return $this->coverCache;
        }

        // Image URLs (Last.fm images, Spotify images, etc.)
        if (preg_match('#lastfm-img|spotify.*image|\.jpg|\.png|\.jpeg|\.gif#i', $url)) {
            return $this->imageCache;
        }

        // Artist-related URLs
        if (preg_match('#/artist[/?]|artist\.getinfo|spotify\.com/artist#i', $url)) {
            return $this->artistCache;
        }

        // Album/Release-related URLs
        if (preg_match('#/release[/?]|/release-group[/?]#i', $url)) {
            return $this->albumCache;
        }

        // Track/Recording-related URLs
        if (preg_match('#/recording[/?]#i', $url)) {
            return $this->trackCache;
        }

        // Default to general HTTP cache
        return $this->httpCache;
    }

    /**
     * Determine which tags to use based on URL patterns.
     * Specific patterns with IDs should come first.
     */
    private function getTagsForUrl(string $url): array
    {
        // Artist-related URLs from MusicBrainz
        if (preg_match('#/artist/([0-9a-f-]{36})#i', $url, $matches)) {
            return ['artist_cache', 'artist_mbid_' . $matches[1]];
        }

        // Album/Release-related URLs from MusicBrainz
        if (preg_match('#/release/([0-9a-f-]{36})#i', $url, $matches)) {
            return ['album_cache', 'album_mbid_' . $matches[1]];
        }
        if (preg_match('#/release-group/([0-9a-f-]{36})#i', $url, $matches)) {
            return ['album_cache', 'album_mbid_' . $matches[1]];
        }

        // Cover art URLs from Cover Art Archive
        if (preg_match('#coverartarchive\.org/release/([0-9a-f-]{36})#i', $url, $matches)) {
            return ['cover_cache', 'album_mbid_' . $matches[1]];
        }

        // Fallback to single tags for broader categories

        // Cover art URLs (must come before general image check)
        if (preg_match('#coverartarchive\.org|/front-500#i', $url)) {
            return ['cover_cache'];
        }

        // Image URLs (Last.fm images, Spotify images, etc.)
        if (preg_match('#lastfm-img|spotify.*image|\.jpg|\.png|\.jpeg|\.gif#i', $url)) {
            return ['image_cache'];
        }

        // Artist-related URLs
        if (preg_match('#/artist[/?]|artist\.getinfo|spotify\.com/artist#i', $url)) {
            return ['artist_cache'];
        }

        // Album/Release-related URLs
        if (preg_match('#/release[/?]|/release-group[/?]#i', $url)) {
            return ['album_cache'];
        }

        // Track/Recording-related URLs
        if (preg_match('#/recording[/?]#i', $url)) {
            return ['track_cache'];
        }

        // Default to general HTTP cache tag
        return ['http_cache'];
    }

    /**
     * Effectue une requête HTTP.
     */
    private function makeRequest(string $url): ?array
    {
        $method = 'GET';
        $options = [];

        // Determine which cache pool to use
        $cache = $this->getCachePool($url);        // Determine the appropriate tags for this cache entry
        $tags = $this->getTagsForUrl($url);

        $cacheKey = implode('_', $tags) . '_' . md5('GET' . $url);

        return $cache->get($cacheKey, function (ItemInterface $item) use ($method, $url, $options, $cache, $tags) {
            // Cache duration is set by the individual cache pools
            $options['headers'] = [
                'User-Agent' => $this->getUserAgent(),
            ];
            $response = $this->musicbrainzClient->request($method, $url, $options);

            if (200 !== $response->getStatusCode()) {
                // Ne pas mettre en cache — on renvoie une exception spéciale pour dire "pas de cache"
                throw new RuntimeException('Non-cacheable response (HTTP ' . $response->getStatusCode() . ')');
            }

            // Set tags if the cache supports it
            if ($cache instanceof TagAwareCacheInterface && !empty($tags)) {
                $item->tag($tags);
            }

            $content = $response->getContent(false);

            $data = json_decode($content, true);

            if (\JSON_ERROR_NONE !== json_last_error()) {
                $this->logger->error($this->translator->trans('api.log.json_error') . ': ' . json_last_error_msg());

                return null;
            }

            return $data;
        });
    }

    /**
     * Recherche d'artistes par nom.
     */
    public function searchArtist(string $name, int $maxResults = 0): array
    {
        try {
            $fetchPage = function (int $offset, int $limit) use ($name) {
                $url = $this->buildUrlWithPagination('/artist', [
                    'query' => $name,
                ], $offset, $limit);

                return $this->makeRequest($url);
            };

            $results = $this->paginationHelper->fetchAllResults($fetchPage, 100, $maxResults);

            $this->logger->info($this->translator->trans('api.log.artist_search_success', ['name' => $name, 'count' => \count($results)]));

            return $results;
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.artist_search_error') . ': ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Récupère les détails d'un artiste par MBID.
     */
    public function getArtist(string $mbid): ?array
    {
        try {
            $url = self::API_BASE_URL . '/artist/' . $mbid . '?fmt=json&inc=releases+release-groups+tags+ratings';

            $data = $this->makeRequest($url);
            if (!$data) {
                return null;
            }

            $this->logger->info($this->translator->trans('api.log.artist_details_retrieved', ['mbid' => $mbid]));

            return $data;
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.artist_details_error') . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Recherche d'albums par titre et nom d'artiste.
     */
    public function searchAlbum(string $title, string $artistName, int $maxResults = 0): array
    {
        try {
            $query = $title . ' AND artist:' . $artistName;

            $fetchPage = function (int $offset, int $limit) use ($query) {
                $url = $this->buildUrlWithPagination('/release', [
                    'query' => $query,
                ], $offset, $limit);

                return $this->makeRequest($url);
            };

            $results = $this->paginationHelper->fetchAllResults($fetchPage, 100, $maxResults);

            $this->logger->info($this->translator->trans('api.log.album_search_success', ['title' => $title, 'artist' => $artistName, 'count' => \count($results)]));

            return $results;
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.album_search_error') . ': ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Récupère les détails d'un album par MBID.
     */
    public function getAlbum(string $mbid): ?array
    {
        try {
            $url = self::API_BASE_URL . '/release/' . $mbid . '?fmt=json&inc=recordings+media+release-groups+tags+ratings';

            $data = $this->makeRequest($url);
            if (!$data) {
                return null;
            }

            $this->logger->info($this->translator->trans('api.log.album_details_retrieved') . ': ' . $mbid);

            return $data;
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.album_details_error') . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Récupère les sorties par groupe de sorties avec filtrage optionnel.
     */
    public function getReleasesByReleaseGroup(string $releaseGroupMbid, ?array $statuses = null, int $maxResults = 0): array
    {
        try {
            $params = [
                'release-group' => $releaseGroupMbid,
                'inc' => 'media+recordings',
            ];

            // Add status filter if provided
            if ($statuses && !empty($statuses)) {
                $params['status'] = implode('|', array_map('strtolower', $statuses));
            }

            $fetchPage = function (int $offset, int $limit) use ($params) {
                $url = $this->buildUrlWithPagination('/release', $params, $offset, $limit);

                return $this->makeRequest($url);
            };

            $this->logger->info('Fetching releases for release group: ' . $releaseGroupMbid);

            $results = $this->paginationHelper->fetchAllResults($fetchPage, 100, $maxResults);

            $this->logger->info($this->translator->trans('api.log.releases_retrieved', ['mbid' => $releaseGroupMbid, 'count' => \count($results)]));

            return $results;
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.error_retrieving_releases') . ': ' . $e->getMessage());
            $this->logger->error('Stack trace: ' . $e->getTraceAsString());

            return [];
        }
    }

    /**
     * Récupère les pistes d'une sortie.
     */
    public function getReleaseTracks(string $releaseMbid): array
    {
        try {
            $url = self::API_BASE_URL . '/release/' . $releaseMbid . '?fmt=json&inc=recordings+media';

            $data = $this->makeRequest($url);
            if (!$data) {
                return [];
            }

            $tracks = [];
            if (isset($data['media'])) {
                foreach ($data['media'] as $mediaIndex => $media) {
                    if (isset($media['tracks'])) {
                        foreach ($media['tracks'] as $track) {
                            $tracks[] = [
                                'id' => $track['id'],
                                'number' => $track['number'],
                                'title' => $track['title'],
                                'position' => $track['position'],
                                'length' => $track['length'] ?? null,
                                'recording' => $track['recording'] ?? null,
                                'mediumNumber' => $mediaIndex + 1,
                                'mediumTitle' => $media['title'] ?? null,
                                'mediumFormat' => $media['format'] ?? null,
                                'mediumPosition' => $media['position'] ?? $mediaIndex + 1,
                                'mediumTrackCount' => \count($media['tracks']),
                            ];
                        }
                    }
                }
            }

            $this->logger->info($this->translator->trans('api.log.tracks_retrieved', ['mbid' => $releaseMbid, 'count' => \count($tracks)]));

            return $tracks;
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.track_retrieval_error') . ': ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Récupère les médias d'une sortie avec leurs informations.
     */
    public function getReleaseMedia(string $releaseMbid): array
    {
        try {
            $url = self::API_BASE_URL . '/release/' . $releaseMbid . '?fmt=json&inc=recordings+media';

            $data = $this->makeRequest($url);
            if (!$data) {
                return [];
            }

            $media = [];
            if (isset($data['media'])) {
                foreach ($data['media'] as $mediaIndex => $medium) {
                    $tracks = [];
                    if (isset($medium['tracks'])) {
                        foreach ($medium['tracks'] as $track) {
                            $tracks[] = [
                                'id' => $track['id'],
                                'number' => $track['number'],
                                'title' => $track['title'],
                                'position' => $track['position'],
                                'length' => $track['length'] ?? null,
                                'recording' => $track['recording'] ?? null,
                            ];
                        }
                    }

                    $discId = null;
                    if (isset($medium['discs']) && !empty($medium['discs'])) {
                        $discId = $medium['discs'][0]['id'] ?? null;
                    }

                    $media[] = [
                        'id' => $medium['id'],
                        'title' => $medium['title'] ?? null,
                        'format' => $medium['format'] ?? null,
                        'position' => $medium['position'] ?? $mediaIndex + 1,
                        'trackCount' => \count($tracks),
                        'discId' => $discId,
                        'tracks' => $tracks,
                    ];
                }
            }

            $this->logger->info($this->translator->trans('api.log.media_retrieved', ['mbid' => $releaseMbid, 'count' => \count($media)]));

            return $media;
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.media_retrieval_error') . ': ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Récupère les pistes d'un album par MBID.
     */
    public function getAlbumTracks(string $mbid): array
    {
        try {
            $url = self::API_BASE_URL . '/release/' . $mbid . '?fmt=json&inc=recordings+media';

            $data = $this->makeRequest($url);
            if (!$data) {
                return [];
            }

            $tracks = [];
            if (isset($data['media'])) {
                foreach ($data['media'] as $mediaIndex => $media) {
                    if (isset($media['tracks'])) {
                        foreach ($media['tracks'] as $track) {
                            $tracks[] = [
                                'id' => $track['id'],
                                'number' => $track['number'],
                                'title' => $track['title'],
                                'position' => $track['position'],
                                'mediumNumber' => $mediaIndex + 1,
                                'mediumTitle' => $media['title'] ?? null,
                                'mediumFormat' => $media['format'] ?? null,
                                'mediumPosition' => $media['position'] ?? $mediaIndex + 1,
                                'mediumTrackCount' => \count($media['tracks']),
                                'length' => $track['length'] ?? null,
                                'recording' => $track['recording'] ?? null,
                            ];
                        }
                    }
                }
            }

            $this->logger->info($this->translator->trans('api.log.album_tracks_retrieved') . ': ' . $mbid . ', résultats: ' . \count($tracks));

            return $tracks;
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.album_tracks_retrieval_error') . ': ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Récupère les albums d'un artiste par MBID.
     */
    public function getArtistAlbums(string $artistMbid, int $maxResults = 0): array
    {
        try {
            $params = [
                'artist' => $artistMbid,
                'type' => 'album',
            ];

            $fetchPage = function (int $offset, int $limit) use ($params) {
                $url = $this->buildUrlWithPagination('/release-group', $params, $offset, $limit);

                return $this->makeRequest($url);
            };

            $results = $this->paginationHelper->fetchAllResults($fetchPage, 100, $maxResults);

            $this->logger->info($this->translator->trans('api.log.artist_albums_retrieved') . ': ' . $artistMbid . ', résultats: ' . \count($results));

            return $results;
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.artist_albums_retrieval_error') . ': ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Recherche avancée d'artistes.
     */
    public function searchArtistAdvanced(string $name, ?string $country = null, ?string $type = null, int $maxResults = 0): array
    {
        try {
            $query = $name;
            if ($country) {
                $query .= ' AND country:' . $country;
            }
            if ($type) {
                $query .= ' AND type:' . $type;
            }

            $fetchPage = function (int $offset, int $limit) use ($query) {
                $url = $this->buildUrlWithPagination('/artist', [
                    'query' => $query,
                ], $offset, $limit);

                return $this->makeRequest($url);
            };

            $results = $this->paginationHelper->fetchAllResults($fetchPage, 100, $maxResults);

            $this->logger->info('Recherche avancée réussie pour: ' . $name . ', résultats: ' . \count($results));

            return $results;
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.advanced_artist_search_error') . ': ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Récupère la couverture d'un album.
     */
    public function getAlbumCover(string $mbid): ?string
    {
        try {
            // Vérifier d'abord si l'image existe localement et est valide
            if ($this->imageService->hasAlbumCover($mbid)) {
                return $this->imageService->getAlbumCoverPath($mbid);
            }

            // Récupérer les images depuis MusicBrainz
            $url = 'release/' . $mbid . '/front-500';

            try {
                $response = $this->coverartClient->request('GET', $url);
                $statusCode = $response->getStatusCode();

                if (200 !== $statusCode) {
                    $this->logger->warning($this->translator->trans('api.log.unable_to_retrieve_cover') . ': ' . $mbid . ' (HTTP: ' . $statusCode . ')');

                    return null;
                }
            } catch (TransportExceptionInterface|ClientExceptionInterface|ServerExceptionInterface $e) {
                $this->logger->warning($this->translator->trans('api.log.unable_to_retrieve_cover') . ': ' . $mbid . ' - ' . $e->getMessage());

                return null;
            }

            // Télécharger et stocker l'image
            $fullUrl = 'https://coverartarchive.org/' . $url;
            // Prefer using album-context aware saver from higher-level code; keep fallback here
            $localPath = $this->imageService->downloadAndStoreImage($fullUrl, 'album', $mbid);
            if ($localPath) {
                $this->logger->info($this->translator->trans('api.log.album_cover_downloaded') . ': ' . $mbid);

                return $localPath;
            }

            return null;
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.cover_retrieval_error') . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Récupère l'image d'un artiste.
     */
    public function getArtistImage(string $mbid): ?string
    {
        try {
            // Vérifier d'abord si l'image existe localement et est valide
            if ($this->imageService->hasArtistImage($mbid)) {
                return $this->imageService->getArtistImagePath($mbid);
            }

            // Pour les artistes, on peut utiliser d'autres sources d'images
            // Pour l'instant, on retourne null car MusicBrainz n'a pas d'images d'artistes
            $this->logger->info('Pas d\'image d\'artiste disponible pour: ' . $mbid);

            return null;
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.artist_image_retrieval_error') . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Force le re-téléchargement de la couverture d'un album.
     */
    public function forceRedownloadAlbumCover(string $mbid): ?string
    {
        try {
            $url = 'release/' . $mbid . '/front-500';

            try {
                $response = $this->coverartClient->request('GET', $url);
                $statusCode = $response->getStatusCode();

                if (200 !== $statusCode) {
                    $this->logger->warning($this->translator->trans('api.log.unable_to_retrieve_cover') . ': ' . $mbid . ' (HTTP: ' . $statusCode . ')');

                    return null;
                }
            } catch (TransportExceptionInterface|ClientExceptionInterface|ServerExceptionInterface $e) {
                $this->logger->warning($this->translator->trans('api.log.unable_to_retrieve_cover') . ': ' . $mbid . ' - ' . $e->getMessage());

                return null;
            }

            // Forcer le re-téléchargement
            $fullUrl = 'https://coverartarchive.org/' . $url;
            $localPath = $this->imageService->downloadAndStoreImage($fullUrl, 'album', $mbid, true);
            if ($localPath) {
                $this->logger->info($this->translator->trans('api.log.album_cover_redownloaded') . ': ' . $mbid);

                return $localPath;
            }

            return null;
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.error_redownloading_cover') . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Récupère les groupes de sorties d'un artiste avec filtrage optionnel par type primaire.
     */
    public function getArtistReleaseGroups(string $artistMbid, ?array $primaryTypes = null, ?string $secondaryType = null, int $maxResults = 0): array
    {
        try {
            // Before: 'inc' => 'tags+ratings+user-ratings+user-tags+secondary-types+disambiguation+aliases+annotation+genres'
            // After: 'inc' => 'tags+ratings+aliases'
            $params = [
                'artist' => $artistMbid,
                'inc' => 'tags+ratings+aliases+annotation+genres',
            ];

            // Add primary type filter if provided
            if ($primaryTypes && !empty($primaryTypes)) {
                $typeParam = implode('|', array_map('strtolower', $primaryTypes));
                $params['type'] = $typeParam;

                if (null !== $secondaryType) {
                    $params['type'] .= '|' . mb_strtolower($secondaryType);
                }
            }

            $fetchPage = function (int $offset, int $limit) use ($params) {
                $url = $this->buildUrlWithPagination('/release-group', $params, $offset, $limit);

                return $this->makeRequest($url);
            };

            $this->logger->info('Fetching release groups for artist: ' . $artistMbid . ' with params: ' . json_encode($params));

            $results = $this->paginationHelper->fetchAllResults($fetchPage, 100, $maxResults);

            $this->logger->info('Release groups retrieved for artist: ' . $artistMbid . ', results: ' . \count($results));

            // Log some sample data for debugging
            if (!empty($results)) {
                $sampleGroup = $results[0];
                $this->logger->info('Sample release group data: ' . json_encode([
                    'id' => $sampleGroup['id'] ?? 'N/A',
                    'title' => $sampleGroup['title'] ?? 'N/A',
                    'primary-type' => $sampleGroup['primary-type'] ?? 'N/A',
                    'first-release-date' => $sampleGroup['first-release-date'] ?? 'N/A',
                    'secondary-types' => $sampleGroup['secondary-types'] ?? [],
                ]));
            }

            return $results;
        } catch (Exception $e) {
            $this->logger->error('Error retrieving release groups from direct endpoint: ' . $e->getMessage() . ' for artist: ' . $artistMbid);

            // Try fallback method using artist endpoint
            try {
                $this->logger->info('Attempting fallback method using artist endpoint for: ' . $artistMbid);
                $fallbackData = $this->getArtist($artistMbid);

                if ($fallbackData && isset($fallbackData['release-groups'])) {
                    $fallbackGroups = $fallbackData['release-groups'];
                    $this->logger->info('Fallback successful, retrieved ' . \count($fallbackGroups) . ' release groups for artist: ' . $artistMbid);

                    return $fallbackGroups;
                }
                $this->logger->warning('Fallback method returned no release groups for artist: ' . $artistMbid);
            } catch (Exception $fallbackException) {
                $this->logger->error('Fallback method also failed for artist ' . $artistMbid . ': ' . $fallbackException->getMessage());
            }

            $this->logger->error('Stack trace for artist ' . $artistMbid . ': ' . $e->getTraceAsString());

            return [];
        }
    }

    /**
     * Test the MusicBrainz API connection and get raw response for debugging.
     */
    public function testApiConnection(string $artistMbid): array
    {
        try {
            $params = [
                'artist' => $artistMbid,
            ];

            $fetchPage = function (int $offset, int $limit) use ($params) {
                $url = $this->buildUrlWithPagination('/release-group', $params, $offset, $limit);

                return $this->makeRequest($url);
            };

            $this->logger->info('Testing API connection for artist: ' . $artistMbid);

            $data = $this->paginationHelper->fetchAllResults($fetchPage, 5, 5);

            return [
                'success' => true,
                'url' => 'Test completed with pagination',
                'data' => $data,
                'release_groups_count' => \count($data),
                'raw_response' => $data,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'url' => 'N/A',
            ];
        }
    }

    /**
     * Récupère les groupes de sorties par type.
     */
    private function getReleaseGroupsByType(string $artistMbid, string $type): array
    {
        try {
            $params = [
                'artist' => $artistMbid,
                'type' => $type,
            ];

            $fetchPage = function (int $offset, int $limit) use ($params) {
                $url = $this->buildUrlWithPagination('/release-group', $params, $offset, $limit);

                return $this->makeRequest($url);
            };

            return $this->paginationHelper->fetchAllResults($fetchPage, 100, 0);
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.release_group_type_retrieval_error') . ': ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Récupère les autres sorties d'un album.
     */
    public function getOtherReleases(string $albumMbid, ?array $statuses = null): array
    {
        try {
            // D'abord, récupérer le groupe de sorties de l'album
            $album = $this->getAlbum($albumMbid);
            if (!$album || !isset($album['release-group']['id'])) {
                return [];
            }

            $releaseGroupMbid = $album['release-group']['id'];

            // Récupérer toutes les sorties du groupe avec filtrage optionnel
            $releases = $this->getReleasesByReleaseGroup($releaseGroupMbid, $statuses);

            // Filtrer pour exclure la sortie actuelle
            $otherReleases = array_filter($releases, function ($release) use ($albumMbid) {
                return $release['id'] !== $albumMbid;
            });

            // Enrichir chaque release avec les informations de média
            $enrichedReleases = [];
            foreach ($otherReleases as $release) {
                $mediaInfo = $this->getReleaseMedia($release['id']);
                $enrichedReleases[] = array_merge($release, [
                    'media' => $mediaInfo,
                    'totalTracks' => array_sum(array_map(function ($medium) {
                        return \count($medium['tracks'] ?? []);
                    }, $mediaInfo)),
                    'mediaCount' => \count($mediaInfo),
                ]);
            }

            $this->logger->info($this->translator->trans('api.log.other_releases_retrieved') . ': ' . $albumMbid . ', résultats: ' . \count($enrichedReleases));

            return array_values($enrichedReleases);
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.other_releases_retrieval_error') . ': ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Récupère les détails d'un groupe de sorties.
     */
    public function getReleaseGroup(string $mbid): ?array
    {
        try {
            $url = self::API_BASE_URL . '/release-group/' . $mbid . '?fmt=json&inc=releases+tags+ratings';

            $data = $this->makeRequest($url);
            if (!$data) {
                return null;
            }

            $this->logger->info($this->translator->trans('api.log.release_group_details_retrieved') . ': ' . $mbid);

            return $data;
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.release_group_details_retrieval_error') . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Récupère les détails d'une sortie.
     */
    public function getRelease(string $releaseMbid): ?array
    {
        try {
            $url = self::API_BASE_URL . '/release/' . $releaseMbid . '?fmt=json&inc=recordings+media+release-groups+tags+ratings';

            $data = $this->makeRequest($url);
            if (!$data) {
                return null;
            }

            $this->logger->info($this->translator->trans('api.log.release_details_retrieved') . ': ' . $releaseMbid);

            return $data;
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.release_details_retrieval_error') . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Récupère les sorties d'un artiste avec filtrage optionnel.
     */
    public function getArtistReleases(string $artistMbid, ?array $statuses = null, int $maxResults = 0): array
    {
        try {
            $params = [
                'artist' => $artistMbid,
            ];

            // Add status filter if provided
            if ($statuses && !empty($statuses)) {
                $params['status'] = implode('|', array_map('strtolower', $statuses));
            }

            $fetchPage = function (int $offset, int $limit) use ($params) {
                $url = $this->buildUrlWithPagination('/release', $params, $offset, $limit);

                return $this->makeRequest($url);
            };

            $results = $this->paginationHelper->fetchAllResults($fetchPage, 100, $maxResults);

            $this->logger->info($this->translator->trans('api.log.artist_releases_retrieved') . ': ' . $artistMbid . ', résultats: ' . \count($results));

            return $results;
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.artist_releases_retrieval_error') . ': ' . $e->getMessage());

            return [];
        }
    }
}
