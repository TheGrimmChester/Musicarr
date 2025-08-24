<?php

declare(strict_types=1);

namespace App\Manager;

use App\Client\MusicBrainzApiClient;
use App\Client\SpotifyWebApiClient;
use App\Configuration\Config\ConfigurationFactory;
use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Library;
use App\Entity\Task;
use App\File\FileSanitizer;
use App\Repository\AlbumRepository;
use App\Repository\ArtistRepository;
use App\Repository\LibraryRepository;
use App\Repository\LibraryStatisticRepository;
use App\Repository\TrackRepository;
use App\Task\TaskFactory;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class MusicLibraryManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MusicBrainzApiClient $musicBrainzApiClient,
        private LoggerInterface $logger,
        private ArtistRepository $artistRepository,
        private AlbumRepository $albumRepository,
        private TrackRepository $trackRepository,
        private LibraryRepository $libraryRepository,
        private LibraryStatisticRepository $libraryStatisticRepository,
        private TranslatorInterface $translator,
        private MediaImageManager $mediaImageManager,
        private TaskFactory $taskService,
        private FileSanitizer $fileSanitizer,
        private ConfigurationFactory $configurationFactory,
        private SpotifyWebApiClient $spotifyWebApiClient,
    ) {
    }

    /**
     * Synchronise un artiste (création si inexistant, mise à jour sinon) à partir d'un nom et, si disponible, d'un MBID.
     */
    public function syncArtistWithMbid(string $name, ?string $mbid): ?Artist
    {
        try {
            // Vérifie si l'artiste existe déjà par MBID et met à jour ses informations
            if ($mbid) {
                $existingArtist = $this->artistRepository->findOneBy(['mbid' => $mbid]);
                if ($existingArtist) {
                    $this->logger->info("Artiste {$name} déjà présent dans la base (par MBID) — mise à jour des informations");
                    $this->updateArtistInfo($existingArtist);

                    return $existingArtist;
                }
            }

            // Vérifie si l'artiste existe déjà par nom
            $existingArtistByName = $this->artistRepository->findOneBy(['name' => $name]);
            if ($existingArtistByName) {
                $this->logger->info($this->translator->trans('manager.music_library.artist_already_exists_by_name', ['%name%' => $name]));

                // Si l'artiste existant n'a pas de MBID mais qu'on en a un, on le met à jour
                if (!$existingArtistByName->getMbid() && $mbid) {
                    $this->logger->info($this->translator->trans('manager.music_library.updating_mbid_for_artist', ['%name%' => $name]));
                    $existingArtistByName->setMbid($mbid);
                    $this->entityManager->flush();
                }

                // Mettre à jour les informations de l'artiste existant
                try {
                    $this->updateArtistInfo($existingArtistByName);
                } catch (Throwable $e) {
                    $this->logger->warning($this->translator->trans('api.log.failed_to_update_existing_artist') . ': ' . $e->getMessage());
                }

                return $existingArtistByName;
            }

            $library = $this->libraryRepository->findOneBy([]);
            if (!$library) {
                $this->logger->error($this->translator->trans('api.log.library_not_found_log'));

                return null;
            }

            // Crée le nouvel artiste
            $artist = new Artist();
            $artist->setName($name);
            $artist->setMbid($mbid);
            $artist->setArtistFolderPath($library->getPath() . '/' . $this->sanitizePath($name));
            $artist->setMonitored(true);
            $artist->setStatus('active');

            // Si on a un MBID, récupère les informations détaillées depuis MusicBrainz
            if ($mbid) {
                try {
                    $this->logger->info($this->translator->trans('api.log.artist_detailed_info_retrieval') . ' ' . $name . ' (MBID: ' . $mbid . ')');

                    // Récupère les informations de base de l'artiste
                    /** @var array<string, mixed>|null $detailedArtist */
                    $detailedArtist = $this->musicBrainzApiClient->getArtist($mbid);
                    if ($detailedArtist) {
                        $artist->setDisambiguation($detailedArtist['disambiguation'] ?? null);
                        $artist->setCountry($detailedArtist['country'] ?? null);
                        $artist->setType($detailedArtist['type'] ?? null);
                        $artist->setOverview($detailedArtist['annotation'] ?? null);

                        if (isset($detailedArtist['life-span']['begin'])) {
                            $artist->setStarted(new DateTime($detailedArtist['life-span']['begin']));
                        }
                        if (isset($detailedArtist['life-span']['end'])) {
                            $artist->setEnded(new DateTime($detailedArtist['life-span']['end']));
                        }

                        $artist->setStatus($detailedArtist['life-span']['ended'] ?? false ? 'ended' : 'active');
                    }
                } catch (Exception $e) {
                    $this->logger->warning($this->translator->trans('api.log.artist_details_retrieval_error', ['artist_name' => $name]) . ': ' . $e->getMessage());
                }
            }

            // Enrich with Spotify info: search by name and persist spotifyId, then image
            try {
                $spotify = $this->spotifyWebApiClient->searchArtist($name);
                if ($spotify && ($spotify['id'] ?? null)) {
                    $artist->setSpotifyId($spotify['id']);
                }
            } catch (Throwable $e) {
                $this->logger->warning('Spotify enrichment failed for artist ' . $name . ': ' . $e->getMessage());
            }

            $this->entityManager->persist($artist);
            $this->entityManager->flush();

            // Download and store image if available
            try {
                $artistName = $artist->getName();
                if (null === $artistName) {
                    $this->logger->warning('Artist name is null, skipping image download');
                } else {
                    $imagePath = $this->mediaImageManager->downloadAndStoreArtistImage(
                        $artistName,
                        $artist->getMbid() ?: (string) $artist->getId(),
                        $artist->getMbid(),
                        false,
                        $artist->getSpotifyId(),
                        $library->getPath(),
                        $artist->getId()
                    );
                    if ($imagePath) {
                        $artist->setImageUrl($imagePath);
                        $this->entityManager->flush();
                    }
                }
            } catch (Throwable $e) {
                $this->logger->warning('Artist image download failed for ' . $name . ': ' . $e->getMessage());
            }

            $this->logger->info("Artiste {$name} synchronisé avec succès (ID: {$artist->getId()})");

            return $artist;
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.artist_sync_error', ['artist_name' => $name]) . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Ajoute un album avec un MBID spécifique et récupère ses informations depuis MusicBrainz.
     */
    public function addAlbumWithMbid(string $title, string $releaseMbid, ?string $releaseGroupMbid, int $artistId): ?Album
    {
        try {
            $artist = $this->artistRepository->find($artistId);
            if (!$artist) {
                $this->logger->error("Artiste {$artistId} non trouvé");

                return null;
            }

            // Vérifie si l'album existe déjà par Release MBID
            $existingAlbumByRelease = $this->albumRepository->findOneBy(['releaseMbid' => $releaseMbid]);
            if ($existingAlbumByRelease) {
                $this->logger->info("Album {$title} déjà présent dans la base (par Release MBID)");

                return $existingAlbumByRelease;
            }

            // Crée le nouvel album
            $album = new Album();
            $album->setTitle($title);
            $album->setArtist($artist);
            $album->setReleaseMbid($releaseMbid);
            $album->setReleaseGroupMbid($releaseGroupMbid);
            $album->setMonitored(true);
            $album->setAnyReleaseOk(true);
            $album->setDownloaded(false);
            $album->setHasFile(false);
            $album->setStatus('empty');

            // Récupère les informations détaillées depuis MusicBrainz
            try {
                $this->logger->info($this->translator->trans('api.log.album_detailed_info_retrieval') . ' ' . $title . ' (Release MBID: ' . $releaseMbid . ')');

                /** @var array<string, mixed>|null $releaseData */
                $releaseData = $this->musicBrainzApiClient->getRelease($releaseMbid);
                if ($releaseData) {
                    $album->setTitle($releaseData['title'] ?? $title);
                    $album->setDisambiguation($releaseData['disambiguation'] ?? null);

                    if (isset($releaseData['date'])) {
                        $album->setReleaseDate(new DateTime($releaseData['date']));
                    }

                    if (isset($releaseData['release-group']['primary-type'])) {
                        $album->setAlbumType($releaseData['release-group']['primary-type']);
                    }

                    // Récupérer la couverture de l'album
                    $albumCover = $this->musicBrainzApiClient->getAlbumCover($releaseMbid);
                    if ($albumCover) {
                        // Save locally according to configuration
                        $localCover = $this->mediaImageManager->downloadAndStoreAlbumCoverFromUrl($album, $albumCover, false);
                        if ($localCover) {
                            $album->setImageUrl($localCover);
                        } else {
                            // fallback store original URL
                            $album->setImageUrl($albumCover);
                        }
                    }
                }
            } catch (Exception $e) {
                $this->logger->warning($this->translator->trans('api.log.album_details_error', ['album_title' => $title]) . ': ' . $e->getMessage());
            }

            $this->entityManager->persist($album);
            $this->entityManager->flush();

            $this->logger->info("Album {$title} ajouté avec succès (ID: {$album->getId()})");

            return $album;
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.album_add_error', ['album_title' => $title]) . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Ajoute un artiste par nom (sans MBID).
     */
    public function addArtist(string $name, int $libraryId): ?Artist
    {
        return $this->syncArtistWithMbid($name, null);
    }

    /**
     * Synchronise les albums d'un artiste avec MusicBrainz.
     */
    public function syncArtistAlbums(Artist $artist): bool
    {
        if (!$artist->getMbid()) {
            $this->logger->warning($this->translator->trans('api.log.unable_to_sync_albums_no_mbid', ['artist_name' => $artist->getName()]));

            return false;
        }

        try {
            $this->logger->info($this->translator->trans('api.log.artist_albums_sync_started') . ' ' . $artist->getName());

            // Get configured primary types and secondary types
            $primaryTypes = $this->getAlbumImportPrimaryTypes();
            $secondaryTypes = $this->getAlbumImportSecondaryTypes();
            $this->logger->info('Primary types configured for import: ' . implode(', ', $primaryTypes));
            $this->logger->info('Secondary types configured for import: ' . implode(', ', $secondaryTypes));

            // Récupère les groupes de sorties de l'artiste avec filtrage par type primaire
            $releaseGroups = $this->musicBrainzApiClient->getArtistReleaseGroups($artist->getMbid(), $primaryTypes);

            if (empty($releaseGroups)) {
                $this->logger->info($this->translator->trans('api.log.no_albums_found_filtered', ['artist_name' => $artist->getName(), 'types' => implode(', ', $primaryTypes)]));

                return true;
            }

            $this->logger->info('Release groups found: ' . \count($releaseGroups) . ' (filtré par types primaires: ' . implode(', ', $primaryTypes) . ')');

            // If secondary types are configured, fetch additional release groups for each secondary type
            if (!empty($secondaryTypes)) {
                $allReleaseGroups = $releaseGroups;

                foreach ($secondaryTypes as $secondaryType) {
                    $secondaryReleaseGroups = $this->musicBrainzApiClient->getArtistReleaseGroups($artist->getMbid(), $primaryTypes, $secondaryType);

                    if (!empty($secondaryReleaseGroups)) {
                        // Merge with existing release groups, avoiding duplicates
                        foreach ($secondaryReleaseGroups as $secondaryReleaseGroup) {
                            $exists = false;
                            foreach ($allReleaseGroups as $existingReleaseGroup) {
                                if ($existingReleaseGroup['id'] === $secondaryReleaseGroup['id']) {
                                    $exists = true;

                                    break;
                                }
                            }

                            if (!$exists) {
                                $allReleaseGroups[] = $secondaryReleaseGroup;
                            }
                        }

                        if (!empty($secondaryReleaseGroups)) {
                            $this->logger->info('Added ' . \count($secondaryReleaseGroups) . " release groups for secondary type: {$secondaryType}");
                        }
                    }
                }

                $releaseGroups = $allReleaseGroups;
                $this->logger->info('Total release groups after secondary type filtering: ' . \count($releaseGroups));
            }

            // Filter release groups based on their secondary types
            $filteredReleaseGroups = [];
            foreach ($releaseGroups as $releaseGroup) {
                $releaseGroupSecondaryTypes = $releaseGroup['secondary-types'] ?? [];

                // If no secondary types are configured, accept all release groups
                if (empty($secondaryTypes)) {
                    $filteredReleaseGroups[] = $releaseGroup;

                    continue;
                }

                // If release group has no secondary types, check if "Studio" is in allowed types (default)
                if (empty($releaseGroupSecondaryTypes)) {
                    if (\in_array('Studio', $secondaryTypes, true)) {
                        $filteredReleaseGroups[] = $releaseGroup;
                    } else {
                        $this->logger->info("Excluding release group '{$releaseGroup['title']}' - no secondary types and 'Studio' not in configured types: " . implode(', ', $secondaryTypes));
                    }

                    continue;
                }

                // Check if ALL of the release group's secondary types match the configured types
                $allSecondaryTypesMatch = true;
                foreach ($releaseGroupSecondaryTypes as $releaseGroupSecondaryType) {
                    if (!\in_array($releaseGroupSecondaryType, $secondaryTypes, true)) {
                        $allSecondaryTypesMatch = false;

                        break;
                    }
                }

                if ($allSecondaryTypesMatch) {
                    $filteredReleaseGroups[] = $releaseGroup;
                } else {
                    $this->logger->info("Excluding release group '{$releaseGroup['title']}' - secondary types: " . implode(', ', $releaseGroupSecondaryTypes) . ' (not all in configured types: ' . implode(', ', $secondaryTypes) . ')');
                }
            }

            $releaseGroups = $filteredReleaseGroups;
            $this->logger->info('Release groups after secondary type filtering: ' . \count($releaseGroups));

            if (empty($releaseGroups)) {
                $this->logger->info($this->translator->trans('api.log.no_albums_found_secondary_filter', ['artist_name' => $artist->getName(), 'types' => implode(', ', $secondaryTypes)]));

                return true;
            }

            $addedAlbums = 0;
            foreach ($releaseGroups as $releaseGroup) {
                $albums = $this->processReleaseGroup($releaseGroup, $artist);
                $addedAlbums += \count($albums);
            }

            $this->logger->info($this->translator->trans('api.log.artist_albums_sync_completed') . ' ' . $artist->getName() . ': ' . $addedAlbums . ' albums ajoutés');

            return true;
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.artist_albums_sync_error', ['artist_name' => $artist->getName()]) . ': ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Traite un groupe de sorties.
     */
    public function processReleaseGroup(array $releaseGroupData, Artist $artist): array
    {
        $title = $releaseGroupData['title'] ?? '';
        $mbid = $releaseGroupData['id'] ?? null;
        $primaryType = $releaseGroupData['primary-type'] ?? null;
        $secondaryTypes = $releaseGroupData['secondary-types'] ?? [];

        // Get configured release statuses
        $allowedReleaseStatuses = $this->getAlbumImportReleaseStatuses();
        $this->logger->info('Allowed release statuses: ' . implode(', ', $allowedReleaseStatuses));

        // Get configured secondary types
        $allowedSecondaryTypes = $this->getAlbumImportSecondaryTypes();
        $this->logger->info('Allowed secondary types: ' . implode(', ', $allowedSecondaryTypes));

        // Récupère les sorties détaillées du groupe de sorties avec filtrage
        $releases = [];
        if ($mbid) {
            try {
                $releases = $this->musicBrainzApiClient->getReleasesByReleaseGroup($mbid, $allowedReleaseStatuses);
                $this->logger->info($this->translator->trans('api.log.release_retrieval_for_group') . ' ' . $mbid . ': ' . \count($releases) . ' sorties trouvées (filtrées par statuts: ' . implode(', ', $allowedReleaseStatuses) . ')');
            } catch (Exception $e) {
                $this->logger->warning($this->translator->trans('api.log.unable_to_retrieve_releases', ['mbid' => $mbid]) . ': ' . $e->getMessage());
            }
        }

        if (empty($releases)) {
            $this->logger->info($this->translator->trans('api.log.no_authorized_releases_found', ['title' => $title, 'statuses' => implode(', ', $allowedReleaseStatuses)]));

            return [];
        }

        $this->logger->info($this->translator->trans('api.log.authorized_releases_found') . ' ' . $title . ': ' . \count($releases));

        $createdAlbums = [];

        // Filter releases to exclude those with secondary types not matching the database config
        $filteredReleases = [];
        foreach ($releases as $release) {
            $releaseSecondaryTypes = $release['secondary-types'] ?? [];

            // If no secondary types are configured, accept all releases
            if (empty($allowedSecondaryTypes)) {
                $filteredReleases[] = $release;

                continue;
            }

            // If release has no secondary types, check if "Studio" is in allowed types (default)
            if (empty($releaseSecondaryTypes)) {
                if (\in_array('Studio', $allowedSecondaryTypes, true)) {
                    $filteredReleases[] = $release;
                }

                continue;
            }

            // Check if ALL of the release's secondary types match the configured types
            $allSecondaryTypesMatch = true;
            foreach ($releaseSecondaryTypes as $releaseSecondaryType) {
                if (!\in_array($releaseSecondaryType, $allowedSecondaryTypes, true)) {
                    $allSecondaryTypesMatch = false;

                    break;
                }
            }

            if ($allSecondaryTypesMatch) {
                $filteredReleases[] = $release;
            }
        }

        // If multiple releases remain, keep only the first one (or implement your preferred selection logic)
        if (\count($filteredReleases) > 1) {
            $this->logger->info("Multiple matching releases found for album {$title}, keeping only the first one");
            $filteredReleases = [$filteredReleases[0]];
        }

        // Process all matching releases
        foreach ($filteredReleases as $release) {
            $releaseMbid = $release['id'];

            // Check if this specific release already exists
            $existingAlbum = $this->albumRepository->findOneBy([
                'releaseGroupMbid' => $mbid,
                'releaseMbid' => $releaseMbid,
            ]);

            if ($existingAlbum) {
                $this->logger->info("Release {$releaseMbid} for album {$title} already exists, skipping");
                $createdAlbums[] = $existingAlbum;

                if (0 === $existingAlbum->getTracks()->count()) {
                    $this->taskService->createTask(
                        Task::TYPE_SYNC_ALBUM,
                        null,
                        $existingAlbum->getId(),
                        $existingAlbum->getTitle(),
                        [
                            'new_mbid' => $existingAlbum->getReleaseMbid(),
                            'max_retries' => 3,
                        ],
                        3
                    );
                }

                continue;
            }

            // Create new album for this release
            $album = new Album();
            $album->setTitle($title);
            $album->setReleaseGroupMbid($mbid);
            $album->setReleaseMbid($releaseMbid);
            $album->setArtist($artist);

            $album->setAlbumType($primaryType);
            $album->setMonitored(true);
            $album->setSecondaryTypes($secondaryTypes);

            /** @var array<string, mixed> $release */
            if (isset($release['date'])) {
                $album->setReleaseDate(new DateTime($release['date']));
            }

            // Ensure album is persisted if media processing wasn't reached
            if (!$this->entityManager->contains($album)) {
                $this->entityManager->persist($album);
                $this->entityManager->flush();
            }
            $this->taskService->createTask(
                Task::TYPE_SYNC_ALBUM,
                null,
                $album->getId(),
                $album->getTitle(),
                [
                    'new_mbid' => $album->getReleaseMbid(),
                    'max_retries' => 3,
                ],
                3
            );

            $createdAlbums[] = $album;
            $this->logger->info("Album {$title} (release: {$releaseMbid}) ajouté avec succès");
        }

        return $createdAlbums;
    }

    /**
     * Récupère les artistes par bibliothèque.
     */
    public function getArtistsByLibrary(int $libraryId): array
    {
        // Library filtering removed - return all artists
        return $this->artistRepository->findBy([], ['name' => 'ASC']);
    }

    /**
     * Récupère les albums d'un artiste.
     */
    public function getArtistAlbums(int $artistId): array
    {
        return $this->albumRepository->findBy(['artist' => $artistId], ['releaseDate' => 'DESC']);
    }

    /**
     * Récupère les pistes d'un album.
     */
    public function getAlbumTracks(int $albumId): array
    {
        $tracks = $this->trackRepository->findBy(['album' => $albumId], ['mediumNumber' => 'ASC', 'trackNumber' => 'ASC']);

        return $tracks;
    }

    /**
     * Met à jour les informations d'un artiste depuis MusicBrainz.
     */
    public function updateArtistInfo(Artist $artist): void
    {
        if (!$artist->getMbid()) {
            $this->logger->warning($this->translator->trans('api.log.unable_to_update_artist_no_mbid', ['artist_name' => $artist->getName()]));

            return;
        }

        try {
            $this->logger->info($this->translator->trans('api.log.updating_artist_info', ['artist_name' => $artist->getName()]));

            /** @var array<string, mixed>|null $detailedArtist */
            $detailedArtist = $this->musicBrainzApiClient->getArtist($artist->getMbid());
            if ($detailedArtist) {
                $artist->setDisambiguation($detailedArtist['disambiguation'] ?? null);
                $artist->setCountry($detailedArtist['country'] ?? null);
                $artist->setType($detailedArtist['type'] ?? null);
                $artist->setOverview($detailedArtist['annotation'] ?? null);

                if (isset($detailedArtist['life-span']['begin'])) {
                    $artist->setStarted(new DateTime($detailedArtist['life-span']['begin']));
                }
                if (isset($detailedArtist['life-span']['end'])) {
                    $artist->setEnded(new DateTime($detailedArtist['life-span']['end']));
                }

                $artist->setStatus($detailedArtist['life-span']['ended'] ?? false ? 'ended' : 'active');

                // Ensure Spotify ID is set (search by name if missing)
                if (!$artist->getSpotifyId()) {
                    try {
                        $artistName = $artist->getName();
                        if (null !== $artistName) {
                            /** @var array<string, mixed>|null $spotify */
                            $spotify = $this->spotifyWebApiClient->searchArtist($artistName);
                            if ($spotify && ($spotify['id'] ?? null)) {
                                $artist->setSpotifyId($spotify['id']);
                            }
                        }
                    } catch (Throwable $e) {
                        $artistName = $artist->getName();
                        $this->logger->warning('Spotify enrichment during update failed for ' . ($artistName ?? 'unknown') . ': ' . $e->getMessage());
                    }
                }

                // Récupérer l'image de l'artiste depuis Spotify
                $artistName = $artist->getName();
                if (null !== $artistName) {
                    $artistImagePath = $this->mediaImageManager->downloadAndStoreArtistImage(
                        $artistName,
                        $artist->getMbid(),
                        $artist->getMbid(),
                        true,
                        $artist->getSpotifyId(),
                        $artist->getArtistFolderPath(),
                        $artist->getId()
                    );
                    if ($artistImagePath) {
                        $artist->setImageUrl($artistImagePath);
                        $this->logger->info("Image d'artiste mise à jour pour {$artistName}: {$artistImagePath}");
                    } else {
                        $this->logger->info($this->translator->trans('api.log.no_artist_image_found', ['artist_name' => $artistName]));
                    }
                }

                $this->entityManager->flush();
                $artistName = $artist->getName();
                $this->logger->info("Informations de l'artiste " . ($artistName ?? 'unknown') . ' mises à jour');
            }
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.artist_update_error', ['artist_name' => $artist->getName()]) . ': ' . $e->getMessage());
        }
    }

    /**
     * Scanne une bibliothèque pour détecter les fichiers audio.
     */
    public function scanLibrary(Library $library): void
    {
        $this->logger->info($this->translator->trans('api.log.library_scan_started_log', ['library_name' => $library->getName()]));

        $path = $library->getPath();
        if (null === $path || !is_dir($path)) {
            $this->logger->error("Le chemin de la bibliothèque n'existe pas: {$path}");

            return;
        }

        $this->scanDirectory($path, $library);

        $this->logger->info($this->translator->trans('api.log.library_scan_completed_log', ['library_name' => $library->getName()]));
    }

    /**
     * Scanne récursivement un répertoire.
     */
    private function scanDirectory(string $path, Library $library): void
    {
        $files = scandir($path);
        if (false === $files) {
            $this->logger->error($this->translator->trans('api.log.unable_to_scan_directory', ['path' => $path]));

            return;
        }

        foreach ($files as $file) {
            if ('.' === $file || '..' === $file) {
                continue;
            }

            $filePath = $path . '/' . $file;

            if (is_dir($filePath)) {
                $this->scanDirectory($filePath, $library);
            } elseif ($this->isAudioFile($filePath)) {
                $this->logger->info("Fichier audio détecté: {$filePath}");
            }
        }
    }

    /**
     * Vérifie si un fichier est un fichier audio.
     */
    private function isAudioFile(string $path): bool
    {
        $extension = mb_strtolower(pathinfo($path, \PATHINFO_EXTENSION));
        $audioExtensions = ['mp3', 'flac', 'ogg', 'wav', 'm4a', 'aac'];

        return \in_array($extension, $audioExtensions, true);
    }

    /**
     * Traite un fichier audio.
     */
    private function processAudioFile(string $filePath, Library $library): void
    {
        // Ici vous pouvez ajouter la logique pour traiter les fichiers audio
        // Par exemple, extraire les métadonnées, créer des pistes, etc.
    }

    /**
     * Nettoie un chemin pour le système de fichiers.
     */
    private function sanitizePath(string $path): string
    {
        return $this->fileSanitizer->sanitizePath($path);
    }

    /**
     * Récupère les statistiques d'une bibliothèque depuis le cache ou calcule en temps réel.
     */
    public function getLibraryStats(int $libraryId): array
    {
        $library = $this->libraryRepository->find($libraryId);
        if (!$library) {
            return [];
        }

        // Try to get cached statistics first
        $cachedStats = $this->libraryStatisticRepository->findByLibraryId($libraryId);

        // If cached stats exist and are not stale, use them
        if ($cachedStats && !$cachedStats->isStale(60)) {
            return $cachedStats->toArray();
        }

        // Fallback to real-time calculation using efficient queries
        $this->logger->info("Calculating library stats in real-time for library {$libraryId}");

        return $this->calculateLibraryStatsEfficiently($libraryId, $library);
    }

    /**
     * Calculate library statistics using efficient database queries (fallback method).
     */
    private function calculateLibraryStatsEfficiently(int $_libraryId, Library $library): array
    {
        // Count total artists
        $totalArtists = $this->entityManager->createQuery(
            'SELECT COUNT(a.id) FROM App\Entity\Artist a'
        )->getSingleScalarResult();

        // Count total albums
        $totalAlbums = $this->entityManager->createQuery(
            'SELECT COUNT(al.id) FROM App\Entity\Album al
             JOIN al.artist a'
        )->getSingleScalarResult();

        // Count total tracks
        $totalTracks = $this->entityManager->createQuery(
            'SELECT COUNT(t.id) FROM App\Entity\Track t
             JOIN t.album al JOIN al.artist a'
        )->getSingleScalarResult();

        // Count downloaded albums
        $downloadedAlbums = $this->entityManager->createQuery(
            'SELECT COUNT(al.id) FROM App\Entity\Album al
             JOIN al.artist a AND al.downloaded = true'
        )->getSingleScalarResult();

        // Count downloaded tracks
        $downloadedTracks = $this->entityManager->createQuery(
            'SELECT COUNT(t.id) FROM App\Entity\Track t
             JOIN t.album al JOIN al.artist a AND t.downloaded = true'
        )->getSingleScalarResult();

        // Count total singles (albums with albumType = 'Single' or secondaryTypes containing 'Single' or 'EP')
        // Using SQLite-compatible JSON functions
        $totalSingles = $this->entityManager->createQuery(
            'SELECT COUNT(al.id) FROM App\Entity\Album al
             JOIN al.artist a
             AND (al.albumType = :singleType OR al.secondaryTypes LIKE :singleLike OR al.secondaryTypes LIKE :epLike)'
        )
            ->setParameter('singleType', 'Single')
            ->setParameter('singleLike', '%"Single"%')
            ->setParameter('epLike', '%"EP"%')
            ->getSingleScalarResult();

        // Count downloaded singles
        $downloadedSingles = $this->entityManager->createQuery(
            'SELECT COUNT(al.id) FROM App\Entity\Album al
             JOIN al.artist a AND al.downloaded = true
             AND (al.albumType = :singleType OR al.secondaryTypes LIKE :singleLike OR al.secondaryTypes LIKE :epLike)'
        )
            ->setParameter('singleType', 'Single')
            ->setParameter('singleLike', '%"Single"%')
            ->setParameter('epLike', '%"EP"%')
            ->getSingleScalarResult();

        return [
            'totalArtists' => (int) $totalArtists,
            'totalAlbums' => (int) $totalAlbums,
            'totalTracks' => (int) $totalTracks,
            'downloadedAlbums' => (int) $downloadedAlbums,
            'downloadedTracks' => (int) $downloadedTracks,
            'totalSingles' => (int) $totalSingles,
            'downloadedSingles' => (int) $downloadedSingles,
            'library_name' => $library->getName(),
            'library_path' => $library->getPath(),
        ];
    }

    /**
     * Supprime un artiste et tous ses albums/pistes.
     */
    public function deleteArtist(Artist $artist): void
    {
        $this->logger->info($this->translator->trans('manager.music_library.deleting_artist', ['%name%' => $artist->getName()]));

        // Supprime les albums et pistes associés
        $albums = $artist->getAlbums();
        foreach ($albums as $album) {
            $tracks = $album->getTracks();
            foreach ($tracks as $track) {
                $this->entityManager->remove($track);
            }
            $this->entityManager->remove($album);
        }

        $this->entityManager->remove($artist);
        $this->entityManager->flush();

        $this->logger->info($this->translator->trans('manager.music_library.artist_deleted_successfully', ['%name%' => $artist->getName()]));
    }

    /**
     * Récupère le service MusicBrainz API Client.
     */
    public function getMusicBrainzApiClient(): MusicBrainzApiClient
    {
        return $this->musicBrainzApiClient;
    }

    /**
     * Synchronise les pistes d'un album depuis MusicBrainz.
     */
    public function syncAlbumTracks(Album $album): bool
    {
        if (!$album->getReleaseGroupMbid()) {
            $this->logger->warning($this->translator->trans('api.log.unable_to_sync_tracks_no_mbid', ['album_title' => $album->getTitle()]));

            return false;
        }

        try {
            $this->logger->info($this->translator->trans('api.log.track_sync_started') . ' ' . $album->getTitle());

            // Récupère les pistes de l'album depuis MusicBrainz
            $tracksData = $this->musicBrainzApiClient->getAlbumTracks($album->getReleaseGroupMbid());

            if (empty($tracksData)) {
                $this->logger->info($this->translator->trans('api.log.no_tracks_found_album', ['album_title' => $album->getTitle()]));

                return true;
            }

            $addedTracks = 0;
            foreach ($tracksData as $_trackData) {
                // TODO: processTrack method is missing - needs to be implemented or refactored

                ++$addedTracks;
            }

            // delete tracks that are not in the new release
            $existingTracks = $this->trackRepository->findBy(['album' => $album]);
            foreach ($existingTracks as $track) {
                if (!\in_array($track->getMbid(), array_column($tracksData, 'id'), true)) {
                    $this->entityManager->remove($track);
                }
            }

            $this->entityManager->flush();

            $this->logger->info($this->translator->trans('api.log.track_sync_completed') . ' ' . $album->getTitle() . ': ' . $addedTracks . ' pistes ajoutées');

            return true;
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.album_tracks_sync_error', ['album_title' => $album->getTitle()]) . ': ' . $e->getMessage());

            return false;
        }
    }

    public function getAllArtistsPaginated(int $page = 1, int $limit = 50, ?int $_libraryId = null, string $albumsFilter = '', string $statusFilter = ''): array
    {
        $offset = ($page - 1) * $limit;

        $queryBuilder = $this->entityManager->getRepository(Artist::class)->createQueryBuilder('a');

        // Library filtering removed - libraryId parameter kept for backward compatibility but ignored

        // Apply status filter
        if ($statusFilter && \in_array($statusFilter, ['active', 'ended'], true)) {
            $queryBuilder->andWhere('a.status = :status')
                ->setParameter('status', $statusFilter);
        }

        // Apply album filter using EXISTS/NOT EXISTS subqueries
        if ('with_albums' === $albumsFilter) {
            $queryBuilder->andWhere('EXISTS (SELECT 1 FROM App\Entity\Album al WHERE al.artist = a)');
        } elseif ('without_albums' === $albumsFilter) {
            $queryBuilder->andWhere('NOT EXISTS (SELECT 1 FROM App\Entity\Album al WHERE al.artist = a)');
        }

        /** @var Artist[] $result */
        $result = $queryBuilder->orderBy('a.name', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function countAllArtists(?int $_libraryId = null, string $albumsFilter = '', string $statusFilter = ''): int
    {
        $queryBuilder = $this->entityManager->getRepository(Artist::class)->createQueryBuilder('a')
            ->select('COUNT(a.id)');

        // Library filtering removed - libraryId parameter kept for backward compatibility but ignored

        // Apply status filter
        if ($statusFilter && \in_array($statusFilter, ['active', 'ended'], true)) {
            $queryBuilder->andWhere('a.status = :status')
                ->setParameter('status', $statusFilter);
        }

        // Apply album filter using EXISTS/NOT EXISTS subqueries
        if ('with_albums' === $albumsFilter) {
            $queryBuilder->andWhere('EXISTS (SELECT 1 FROM App\Entity\Album al WHERE al.artist = a)');
        } elseif ('without_albums' === $albumsFilter) {
            $queryBuilder->andWhere('NOT EXISTS (SELECT 1 FROM App\Entity\Album al WHERE al.artist = a)');
        }

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    public function searchArtistsPaginated(string $query, int $page = 1, int $limit = 50, ?int $_libraryId = null, string $albumsFilter = '', string $statusFilter = ''): array
    {
        $offset = ($page - 1) * $limit;

        $queryBuilder = $this->entityManager->getRepository(Artist::class)->createQueryBuilder('a')
            ->andWhere('a.name LIKE :query')
            ->setParameter('query', '%' . $query . '%');

        // Library filtering removed - libraryId parameter kept for backward compatibility but ignored

        // Apply status filter
        if ($statusFilter && \in_array($statusFilter, ['active', 'ended'], true)) {
            $queryBuilder->andWhere('a.status = :status')
                ->setParameter('status', $statusFilter);
        }

        // Apply album filter using EXISTS/NOT EXISTS subqueries
        if ('with_albums' === $albumsFilter) {
            $queryBuilder->andWhere('EXISTS (SELECT 1 FROM App\Entity\Album al WHERE al.artist = a)');
        } elseif ('without_albums' === $albumsFilter) {
            $queryBuilder->andWhere('NOT EXISTS (SELECT 1 FROM App\Entity\Album al WHERE al.artist = a)');
        }

        /** @var Artist[] $result */
        $result = $queryBuilder->orderBy('a.name', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function countSearchArtists(string $query, ?int $_libraryId = null, string $albumsFilter = '', string $statusFilter = ''): int
    {
        $queryBuilder = $this->entityManager->getRepository(Artist::class)->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.name LIKE :query')
            ->setParameter('query', '%' . $query . '%');

        // Library filtering removed - libraryId parameter kept for backward compatibility but ignored

        // Apply status filter
        if ($statusFilter && \in_array($statusFilter, ['active', 'ended'], true)) {
            $queryBuilder->andWhere('a.status = :status')
                ->setParameter('status', $statusFilter);
        }

        // Apply album filter using EXISTS/NOT EXISTS subqueries
        if ('with_albums' === $albumsFilter) {
            $queryBuilder->andWhere('EXISTS (SELECT 1 FROM App\Entity\Album al WHERE al.artist = a)');
        } elseif ('without_albums' === $albumsFilter) {
            $queryBuilder->andWhere('NOT EXISTS (SELECT 1 FROM App\Entity\Album al WHERE al.artist = a)');
        }

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    /**
     * Get album import primary types.
     */
    public function getAlbumImportPrimaryTypes(): array
    {
        $config = $this->configurationFactory->getDefaultConfiguration('album_import.');

        return $config['primary_types'] ?? ['Album', 'EP', 'Single'];
    }

    /**
     * Get album import secondary types.
     */
    public function getAlbumImportSecondaryTypes(): array
    {
        $config = $this->configurationFactory->getDefaultConfiguration('album_import.');

        return $config['secondary_types'] ?? ['Studio', 'Remix'];
    }

    /**
     * Get album import release statuses.
     */
    public function getAlbumImportReleaseStatuses(): array
    {
        $config = $this->configurationFactory->getDefaultConfiguration('album_import.');

        return $config['release_statuses'] ?? ['official'];
    }
}
