<?php

declare(strict_types=1);

namespace App\Manager;

use App\Client\SpotifyScrapingClient;
use App\Client\SpotifyWebApiClient;
use App\Configuration\ConfigurationService;
use App\Entity\Album;
use App\Entity\Artist;
use App\File\FileSanitizer;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class MediaImageManager
{
    private const DEFAULT_USER_AGENT = 'Musicarr/1.0.0 (test@example.com)';
    private const COVERS_DIR = 'covers';
    private const ARTISTS_DIR = 'artists';

    public function __construct(
        private LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        private TranslatorInterface $translator,
        private HttpClientInterface $httpClient,
        private SpotifyScrapingClient $spotifyScrapingClient,
        private SpotifyWebApiClient $spotifyWebApiClient,
        private ?ConfigurationService $configurationService = null,
        private ?FileSanitizer $fileSanitizer = null,
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
     * Télécharge et stocke une image localement avec vérification.
     */
    public function downloadAndStoreImage(
        string $url,
        string $type,
        string $identifier,
        bool $forceRedownload = false,
        ?string $artistName = null,
        ?string $albumTitle = null,
        ?string $libraryRootDir = null,
        ?int $entityIdForServingRoute = null
    ): ?string {
        try {
            $saveInLibrary = $this->isSaveInLibraryEnabled();
            $extension = $this->getImageExtension($url);

            // Determine destination path
            if ($saveInLibrary && $libraryRootDir && $artistName) {
                $filepath = $this->computeLibrarySavePath(
                    $type,
                    $identifier,
                    $extension,
                    $artistName,
                    $albumTitle,
                    $libraryRootDir
                );
            } else {
                $filepath = $this->computePublicSavePath($type, $identifier, $extension);
            }

            // Vérifier si l'image existe déjà et est valide
            if (!$forceRedownload && $this->imageExistsAndValid($type, $identifier)) {
                $this->logger->info("Image valide déjà existante: {$filepath}");

                return $this->buildReturnPath($type, $identifier, $saveInLibrary, $entityIdForServingRoute);
            }

            // Si l'image existe mais n'est pas valide, la supprimer
            if (file_exists($filepath)) {
                $this->logger->info($this->translator->trans('api.log.deleting_invalid_image') . ': ' . $filepath);
                unlink($filepath);
            }

            // Télécharger l'image
            $imageData = $this->downloadImage($url);
            if (!$imageData) {
                $this->logger->error($this->translator->trans('api.log.unable_to_download_image') . ': ' . $url);

                return null;
            }

            // Créer le répertoire de destination si nécessaire
            $dir = \dirname($filepath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Sauvegarder l'image
            if (false === file_put_contents($filepath, $imageData)) {
                $this->logger->error($this->translator->trans('api.log.unable_to_save_image') . ': ' . $filepath);

                return null;
            }

            // Optimiser l'image si possible
            $this->optimizeImage($filepath);

            $this->logger->info($this->translator->trans('api.log.image_downloaded_saved') . ': ' . $filepath);

            return $this->buildReturnPath($type, $identifier, $saveInLibrary, $entityIdForServingRoute);
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.image_download_save_error') . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Optimise une image en réduisant sa taille si nécessaire.
     */
    public function optimizeImage(string $filepath): void
    {
        try {
            $imageInfo = getimagesize($filepath);
            if (!$imageInfo) {
                return;
            }

            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $mimeType = $imageInfo['mime'];

            // Définir les dimensions maximales pour les images d'artiste et d'album
            $maxWidth = 800;
            $maxHeight = 800;

            // Si l'image est plus grande que les dimensions maximales, la redimensionner
            if ($width > $maxWidth || $height > $maxHeight) {
                $this->resizeImage($filepath, $maxWidth, $maxHeight, $mimeType);
                $this->logger->info("Image redimensionnée: {$filepath} ({$width}x{$height} -> {$maxWidth}x{$maxHeight})");
            }

            // Optimiser la qualité JPEG si applicable
            if ('image/jpeg' === $mimeType || 'image/jpg' === $mimeType) {
                $this->optimizeJpegQuality($filepath);
            }
        } catch (Exception $e) {
            $this->logger->warning($this->translator->trans('api.log.image_optimization_error', ['filepath' => $filepath]) . ': ' . $e->getMessage());
        }
    }

    /**
     * Redimensionne une image.
     */
    private function resizeImage(string $filepath, int $maxWidth, int $maxHeight, string $mimeType): void
    {
        $image = match ($mimeType) {
            'image/jpeg', 'image/jpg' => imagecreatefromjpeg($filepath),
            'image/png' => imagecreatefrompng($filepath),
            'image/gif' => imagecreatefromgif($filepath),
            'image/webp' => imagecreatefromwebp($filepath),
            default => null,
        };

        if (!$image) {
            return;
        }

        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);

        // Calculer les nouvelles dimensions en conservant le ratio
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        $newWidth = (int) ($originalWidth * $ratio);
        $newHeight = (int) ($originalHeight * $ratio);

        // Créer la nouvelle image
        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        // Préserver la transparence pour PNG et GIF
        if ('image/png' === $mimeType || 'image/gif' === $mimeType) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
            if (false !== $transparent) {
                imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
            }
        }

        // Redimensionner l'image
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

        // Sauvegarder la nouvelle image
        $quality = 85; // Qualité pour JPEG
        match ($mimeType) {
            'image/jpeg', 'image/jpg' => imagejpeg($newImage, $filepath, $quality),
            'image/png' => imagepng($newImage, $filepath, 6), // Compression PNG
            'image/gif' => imagegif($newImage, $filepath),
            'image/webp' => imagewebp($newImage, $filepath, $quality),
            default => imagejpeg($newImage, $filepath, $quality), // Fallback to JPEG
        };

        // Libérer la mémoire
        imagedestroy($image);
        imagedestroy($newImage);
    }

    /**
     * Optimise la qualité JPEG.
     */
    private function optimizeJpegQuality(string $filepath): void
    {
        // Vérifier si l'image est trop grande et peut être compressée davantage
        $fileSize = filesize($filepath);
        if ($fileSize > 500 * 1024) { // Plus de 500KB
            $image = imagecreatefromjpeg($filepath);
            if ($image) {
                // Recompresser avec une qualité plus basse
                imagejpeg($image, $filepath, 80);
                imagedestroy($image);
            }
        }
    }

    /**
     * Télécharge une image depuis une URL.
     */
    private function downloadImage(string $url): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'User-Agent' => $this->getUserAgent(),
                ],
                'timeout' => 30,
                'max_redirects' => 5,
                'verify_peer' => false,
            ]);

            $statusCode = $response->getStatusCode();

            if (200 !== $statusCode) {
                $this->logger->error('HTTP Error: ' . $statusCode);

                return null;
            }

            return $response->getContent();
        } catch (TransportExceptionInterface $e) {
            $this->logger->error($this->translator->trans('api.log.transport_error') . ': ' . $e->getMessage());

            return null;
        } catch (ClientExceptionInterface|ServerExceptionInterface $e) {
            $this->logger->error($this->translator->trans('api.log.http_error') . ': ' . $e->getMessage());

            return null;
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.download_error') . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Détermine l'extension d'une image basée sur l'URL.
     */
    private function getImageExtension(string $url): string
    {
        $parsedUrl = parse_url($url, \PHP_URL_PATH);
        if (false === $parsedUrl || null === $parsedUrl) {
            return 'jpg'; // Fallback to default
        }

        $extension = pathinfo($parsedUrl, \PATHINFO_EXTENSION);

        if (!$extension) {
            // For relative URLs (like cover art paths), we can't easily detect MIME type
            // without making the actual request, so we'll default to jpg
            if (!parse_url($url, \PHP_URL_SCHEME)) {
                return 'jpg';
            }

            // Essayer de détecter le type MIME avec une requête HEAD pour les URLs complètes
            try {
                $response = $this->httpClient->request('HEAD', $url, [
                    'headers' => [
                        'User-Agent' => $this->getUserAgent(),
                    ],
                    'timeout' => 10,
                    'max_redirects' => 5,
                ]);

                $contentType = $response->getHeaders()['content-type'][0] ?? '';

                switch ($contentType) {
                    case 'image/jpeg':
                    case 'image/jpg':
                        return 'jpg';
                    case 'image/png':
                        return 'png';
                    case 'image/gif':
                        return 'gif';
                    case 'image/webp':
                        return 'webp';
                    default:
                        return 'jpg'; // Par défaut
                }
            } catch (Exception $e) {
                $this->logger->warning($this->translator->trans('api.log.mime_detection_error') . ': ' . $url . ' - ' . $e->getMessage());

                return 'jpg'; // Par défaut
            }
        }

        return mb_strtolower($extension);
    }

    /**
     * Convertit un chemin absolu en chemin relatif.
     */
    private function getRelativePath(string $absolutePath): string
    {
        $projectDir = $this->projectDir;
        if (0 === mb_strpos($absolutePath, $projectDir)) {
            return mb_substr($absolutePath, mb_strlen($projectDir) + 1);
        }

        return $absolutePath;
    }

    /**
     * Get the absolute path to an image file.
     */
    private function getAbsoluteImagePath(string $type, string $identifier): ?string
    {
        // If saving in library, try to find file anywhere under libraries (fallback glob)
        if ($this->isSaveInLibraryEnabled()) {
            // Best-effort glob under known library roots could be implemented here if needed
            // For now, return null so callers can compute path using entities
        }
        $metadataDir = $this->getConfiguredMetadataBaseDir();
        $subDir = 'artist' === $type ? self::ARTISTS_DIR : self::COVERS_DIR;
        $typeDir = mb_rtrim($metadataDir, '/') . '/' . $subDir;

        $files = glob($typeDir . '/' . $identifier . '.*');
        if (!empty($files)) {
            return $files[0];
        }

        return null;
    }

    /**
     * Vérifie si une image existe localement et est valide.
     */
    public function imageExistsAndValid(string $type, string $identifier): bool
    {
        $metadataDir = $this->getConfiguredMetadataBaseDir();
        $subDir = 'artist' === $type ? self::ARTISTS_DIR : self::COVERS_DIR;
        $typeDir = mb_rtrim($metadataDir, '/') . '/' . $subDir;

        $files = glob($typeDir . '/' . $identifier . '.*');
        if (empty($files)) {
            return false;
        }

        $filepath = $files[0];

        // Vérifier si le fichier existe et est lisible
        if (!file_exists($filepath) || !is_readable($filepath)) {
            return false;
        }

        // Vérifier la taille du fichier (doit être > 0)
        $filesize = filesize($filepath);
        if (0 === $filesize || false === $filesize) {
            $this->logger->warning("Image corrompue (taille 0): {$filepath}");

            return false;
        }

        // Vérifier si c'est une image valide en essayant de la charger
        $imageInfo = getimagesize($filepath);
        if (false === $imageInfo) {
            $this->logger->warning("Image invalide (format non reconnu): {$filepath}");

            return false;
        }

        // Vérifier les dimensions minimales (au moins 50x50 pixels)
        if ($imageInfo[0] < 50 || $imageInfo[1] < 50) {
            $this->logger->warning("Image trop petite ({$imageInfo[0]}x{$imageInfo[1]}): {$filepath}");

            return false;
        }

        return true;
    }

    /**
     * Vérifie si une image existe localement (méthode de compatibilité).
     */
    public function imageExists(string $type, string $identifier): bool
    {
        return $this->imageExistsAndValid($type, $identifier);
    }

    /**
     * Récupère le chemin local d'une image avec vérification.
     */
    public function getLocalImagePath(string $type, string $identifier): ?string
    {
        $metadataDir = $this->getConfiguredMetadataBaseDir();
        $subDir = 'artist' === $type ? self::ARTISTS_DIR : self::COVERS_DIR;
        $typeDir = mb_rtrim($metadataDir, '/') . '/' . $subDir;

        $files = glob($typeDir . '/' . $identifier . '.*');
        if (!empty($files)) {
            $filepath = $files[0];

            // Vérifier que l'image est valide
            if ($this->imageExistsAndValid($type, $identifier)) {
                return $this->getRelativePath($filepath);
            }
            // L'image existe mais n'est pas valide, la supprimer
            $this->logger->warning($this->translator->trans('manager.media_image.invalid_local_image_detected', ['%path%' => $filepath]));
            unlink($filepath);

            return null;
        }

        return null;
    }

    /**
     * Supprime une image locale.
     */
    public function deleteLocalImage(string $type, string $identifier): bool
    {
        $metadataDir = $this->getConfiguredMetadataBaseDir();
        $subDir = 'artist' === $type ? self::ARTISTS_DIR : self::COVERS_DIR;
        $typeDir = mb_rtrim($metadataDir, '/') . '/' . $subDir;

        $files = glob($typeDir . '/' . $identifier . '.*');
        if (!empty($files)) {
            foreach ($files as $file) {
                if (unlink($file)) {
                    $this->logger->info("Image supprimée: {$file}");

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Nettoie les images orphelines.
     */
    public function cleanupOrphanedImages(): int
    {
        $metadataDir = $this->getConfiguredMetadataBaseDir();
        $cleaned = 0;

        // Nettoyer les images d'artistes
        $artistDir = mb_rtrim($metadataDir, '/') . '/' . self::ARTISTS_DIR;
        if (is_dir($artistDir)) {
            $files = glob($artistDir . '/*');
            if (false !== $files) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        // Ici vous pourriez vérifier si l'artiste existe encore dans la base
                        // Pour l'instant, on ne supprime rien
                    }
                }
            }
        }

        // Nettoyer les images d'albums
        $coverDir = mb_rtrim($metadataDir, '/') . '/' . self::COVERS_DIR;
        if (is_dir($coverDir)) {
            $files = glob($coverDir . '/*');
            if (false !== $files) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        // Ici vous pourriez vérifier si l'album existe encore dans la base
                        // Pour l'instant, on ne supprime rien
                    }
                }
            }
        }

        return $cleaned;
    }

    /**
     * Récupère les statistiques des images.
     */
    public function getImageStats(): array
    {
        $metadataDir = $this->getConfiguredMetadataBaseDir();
        $artistDir = mb_rtrim($metadataDir, '/') . '/' . self::ARTISTS_DIR;
        $coverDir = mb_rtrim($metadataDir, '/') . '/' . self::COVERS_DIR;

        $artistImages = is_dir($artistDir) ? \count(glob($artistDir . '/*') ?: []) : 0;
        $coverImages = is_dir($coverDir) ? \count(glob($coverDir . '/*') ?: []) : 0;

        $totalSize = 0;
        if (is_dir($artistDir)) {
            $files = glob($artistDir . '/*');
            if (false !== $files) {
                foreach ($files as $file) {
                    $totalSize += filesize($file);
                }
            }
        }
        if (is_dir($coverDir)) {
            $files = glob($coverDir . '/*');
            if (false !== $files) {
                foreach ($files as $file) {
                    $totalSize += filesize($file);
                }
            }
        }

        return [
            'artist_images' => $artistImages,
            'cover_images' => $coverImages,
            'total_images' => $artistImages + $coverImages,
            'total_size' => $totalSize,
            'metadata_dir' => $metadataDir,
        ];
    }

    /**
     * Copie une image existante avec un nouveau nom basé sur l'identifiant.
     */
    public function copyImageWithNewName(string $sourcePath, string $type, string $identifier): ?string
    {
        try {
            // Vérifier que le fichier source existe
            if (!file_exists($sourcePath)) {
                $this->logger->error("Fichier source introuvable: {$sourcePath}");

                return null;
            }

            // Créer le dossier de métadonnées dans public s'il n'existe pas
            $metadataDir = $this->getConfiguredMetadataBaseDir();
            if (!is_dir($metadataDir)) {
                mkdir($metadataDir, 0755, true);
            }

            // Créer le sous-dossier approprié
            $subDir = 'artist' === $type ? self::ARTISTS_DIR : self::COVERS_DIR;
            $typeDir = mb_rtrim($metadataDir, '/') . '/' . $subDir;
            if (!is_dir($typeDir)) {
                mkdir($typeDir, 0755, true);
            }

            // Déterminer l'extension du fichier source
            $extension = pathinfo($sourcePath, \PATHINFO_EXTENSION);
            if (empty($extension)) {
                $extension = 'jpg'; // Extension par défaut
            }

            // Générer le nouveau nom de fichier
            $filename = $identifier . '.' . $extension;
            $newFilepath = $typeDir . '/' . $filename;

            // Copier le fichier
            if (copy($sourcePath, $newFilepath)) {
                $this->logger->info("Image copiée: {$sourcePath} -> {$newFilepath}");

                return $this->getRelativePath($newFilepath);
            }
            $this->logger->error($this->translator->trans('api.log.unable_to_copy_image') . ': ' . $sourcePath . ' -> ' . $newFilepath);

            return null;
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.image_copy_error') . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Télécharge et stocke une image d'artiste depuis Spotify.
     */
    public function downloadAndStoreArtistImage(
        string $artistName,
        string $identifier,
        ?string $mbid = null,
        bool $forceRedownload = false,
        ?string $spotifyId = null,
        ?string $libraryRootDir = null,
        ?int $artistIdForServingRoute = null
    ): ?string {
        try {
            // Vérifier si l'image existe déjà et est valide
            if (!$forceRedownload && $this->imageExistsAndValid('artist', $identifier)) {
                $localPath = $this->getLocalImagePath('artist', $identifier);
                $this->logger->info("Image d'artiste valide déjà existante: {$localPath}");

                $absolutePath = $this->getAbsoluteImagePath('artist', $identifier);
                if (null !== $absolutePath) {
                    return $this->getRelativePath($absolutePath);
                }

                return null;
            }

            // Récupérer l'URL de l'image depuis Spotify Web API si possible (more reliable)
            $imageUrl = null;
            if ($spotifyId) {
                $info = $this->spotifyWebApiClient->searchArtist($artistName);
                if ($info && ($info['id'] ?? null) === $spotifyId) {
                    $imageUrl = $info['image_url'] ?? null;
                }
            }
            if (!$imageUrl) {
                // Fallback: scraping-based method
                $imageUrl = $this->spotifyScrapingClient->getArtistImageUrl($artistName);
            }

            if (!$imageUrl) {
                $this->logger->info($this->translator->trans('api.log.no_image_found_spotify') . ': ' . $artistName . ($mbid ? " (MBID: {$mbid})" : ''));

                return null;
            }

            // Télécharger et stocker l'image
            $localPath = $this->downloadAndStoreImage(
                $imageUrl,
                'artist',
                $identifier,
                $forceRedownload,
                $artistName,
                null,
                $libraryRootDir,
                $artistIdForServingRoute
            );

            if ($localPath) {
                $this->logger->info($this->translator->trans('api.log.artist_image_downloaded_spotify') . ': ' . $artistName . ' -> ' . $localPath);
            } else {
                $this->logger->error($this->translator->trans('api.log.spotify_download_failed') . ': ' . $artistName);
            }

            return $localPath;
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.spotify_artist_image_download_error') . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Save album cover using context from Album entity.
     */
    public function downloadAndStoreAlbumCoverFromUrl(Album $album, string $coverUrl, bool $forceRedownload = false): ?string
    {
        try {
            $artist = $album->getArtist();
            $artistName = $artist ? $artist->getName() : null;
            $albumTitle = $album->getTitle();
            $libraryRoot = $artist ? $artist->getArtistFolderPath() : null;

            return $this->downloadAndStoreImage(
                $coverUrl,
                'album',
                (string) $album->getReleaseMbid(),
                $forceRedownload,
                $artistName,
                $albumTitle,
                $libraryRoot,
                $album->getId()
            );
        } catch (Throwable $e) {
            $this->logger->error($this->translator->trans('api.log.cover_save_error') . ': ' . $e->getMessage());

            return null;
        }
    }

    private function isSaveInLibraryEnabled(): bool
    {
        return (bool) ($this->configurationService?->get('metadata.save_in_library', false) ?? false);
    }

    private function getConfiguredMetadataBaseDir(): string
    {
        $configured = (string) ($this->configurationService?->get('metadata.base_dir', 'public/metadata') ?? 'public/metadata');
        if (str_starts_with($configured, '/')) {
            return $configured;
        }

        return mb_rtrim($this->projectDir, '/') . '/' . mb_ltrim($configured, '/');
    }

    private function computePublicSavePath(string $type, string $identifier, string $extension): string
    {
        $baseDir = mb_rtrim($this->getConfiguredMetadataBaseDir(), '/');
        $subDir = 'artist' === $type ? self::ARTISTS_DIR : self::COVERS_DIR;
        $typeDir = $baseDir . '/' . $subDir;
        if (!is_dir($typeDir)) {
            mkdir($typeDir, 0755, true);
        }

        return $typeDir . '/' . $identifier . '.' . $extension;
    }

    private function computeLibrarySavePath(
        string $type,
        string $identifier,
        string $extension,
        string $_artistName,
        ?string $albumTitle,
        string $libraryRootDir
    ): string {
        $sanitizer = $this->fileSanitizer ?? new FileSanitizer();

        $basePath = mb_rtrim($libraryRootDir, '/');
        if ('artist' === $type) {
            $basePath = mb_rtrim($libraryRootDir, '/');

            return $basePath . '/' . $identifier . '.' . $extension;
        }
        $safeAlbum = $sanitizer->sanitizePath($albumTitle ?? 'album');

        return $basePath . '/' . $safeAlbum . $identifier . '.' . $extension;
    }

    private function buildReturnPath(string $type, string $identifier, bool $savedInLibrary, ?int $entityIdForServingRoute): string
    {
        if ($savedInLibrary && $entityIdForServingRoute) {
            // Served through controller
            if ('artist' === $type) {
                return '/media/artist/' . $entityIdForServingRoute;
            }

            return '/media/album/' . $entityIdForServingRoute;
        }

        // Served directly from public dir
        $baseDir = mb_rtrim($this->getConfiguredMetadataBaseDir(), '/');
        $subDir = 'artist' === $type ? self::ARTISTS_DIR : self::COVERS_DIR;

        // If baseDir points to .../public/metadata, convert to web path
        // Try to find actual saved file with any extension
        $absoluteTypeDir = $baseDir . '/' . $subDir;
        $files = glob($absoluteTypeDir . '/' . $identifier . '.*');
        $filename = $identifier;
        if ($files && !empty($files)) {
            $filename = basename($files[0]);
        }

        // Compute web path: assume metadata is under public folder
        return '/metadata/' . $subDir . '/' . $filename;
    }

    /**
     * Resolve artist image path for a specific folder path
     * Useful for operations like moving images between folders.
     */
    public function resolveArtistImagePathForFolder(Artist $artist, ?string $folderPath): ?string
    {
        $saveInLibrary = $this->isSaveInLibraryEnabled();
        $identifier = $artist->getMbid() ?: (string) $artist->getId();

        if ($saveInLibrary && $folderPath) {
            $artistDir = mb_rtrim($folderPath, '/');
            $candidates = glob($artistDir . '/' . $identifier . '.*') ?: [];

            return $candidates[0] ?? null;
        }

        // Fallback to public metadata dir
        $base = mb_rtrim($this->getConfiguredMetadataBaseDir(), '/');
        $candidates = glob($base . '/artists/' . $identifier . '.*') ?: [];

        return $candidates[0] ?? null;
    }

    /**
     * Move artist image from old folder to new folder.
     */
    public function moveArtistImage(Artist $artist, ?string $oldFolderPath, string $newFolderPath): void
    {
        try {
            if (!$oldFolderPath) {
                return;
            }

            // Resolve the old image path for the specific folder
            $oldImagePath = $this->resolveArtistImagePathForFolder($artist, $oldFolderPath);

            if (!$oldImagePath || !file_exists($oldImagePath)) {
                $this->logger->info("No artist image found in old folder for {$artist->getName()}");

                return;
            }

            $identifier = $artist->getMbid() ?: (string) $artist->getId();

            // Get file extension
            $extension = pathinfo($oldImagePath, \PATHINFO_EXTENSION);
            if (empty($extension)) {
                $extension = 'jpg'; // Default extension
            }

            // Create new image path
            $newImagePath = mb_rtrim($newFolderPath, '/') . '/' . $identifier . '.' . $extension;

            // Create directory if it doesn't exist
            $newDir = \dirname($newImagePath);
            if (!is_dir($newDir)) {
                mkdir($newDir, 0755, true);
            }

            // Move the image file
            if (rename($oldImagePath, $newImagePath)) {
                $this->logger->info("Artist image moved for {$artist->getName()}: {$oldImagePath} -> {$newImagePath}");

                // Update the artist's image URL to use the media controller route
                $artist->setImageUrl("/media/artist/{$artist->getId()}");
            } else {
                $this->logger->error("Failed to move artist image for {$artist->getName()}: {$oldImagePath} -> {$newImagePath}");
            }
        } catch (Exception $e) {
            $this->logger->error("Error moving artist image for {$artist->getName()}: " . $e->getMessage());
        }
    }

    /**
     * Resolve artist image path.
     */
    public function resolveArtistImagePath(Artist $artist): ?string
    {
        $saveInLibrary = $this->isSaveInLibraryEnabled();
        $identifier = $artist->getMbid() ?: (string) $artist->getId();

        if ($saveInLibrary && $artist->getArtistFolderPath()) {
            $artistDir = mb_rtrim($artist->getArtistFolderPath(), '/');
            $candidates = glob($artistDir . '/' . $identifier . '.*') ?: [];

            return $candidates[0] ?? null;
        }

        // Fallback to public metadata dir
        $base = mb_rtrim($this->getConfiguredMetadataBaseDir(), '/');
        $candidates = glob($base . '/artists/' . $identifier . '.*') ?: [];

        return $candidates[0] ?? null;
    }

    /**
     * Resolve album cover path.
     */
    public function resolveAlbumCoverPath(Album $album): ?string
    {
        $saveInLibrary = $this->isSaveInLibraryEnabled();
        $artist = $album->getArtist();
        $identifier = (string) $album->getReleaseMbid();

        if ($saveInLibrary && $artist && $artist->getArtistFolderPath()) {
            $artistDir = mb_rtrim($artist->getArtistFolderPath(), '/');
            $safeAlbum = $album->getTitle() ?? '';
            $dir = $artistDir;
            $candidates = glob($dir . '/' . $safeAlbum . $identifier . '.*') ?: [];

            return $candidates[0] ?? null;
        }

        $base = mb_rtrim($this->getConfiguredMetadataBaseDir(), '/');
        $candidates = glob($base . '/covers/' . $identifier . '.*') ?: [];

        return $candidates[0] ?? null;
    }
}
