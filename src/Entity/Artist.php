<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ArtistRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArtistRepository::class)]
class Artist
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true, unique: true)]
    private ?string $mbid = null;

    #[ORM\Column(length: 255, nullable: true, unique: true)]
    private ?string $spotifyId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $disambiguation = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $overview = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTimeInterface $ended = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTimeInterface $started = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $artistFolderPath = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column]
    private bool $monitored = true;

    #[ORM\Column]
    private bool $monitorNewItems = true;

    #[ORM\Column(type: 'datetime')]
    private DateTimeInterface $lastInfoSync;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTimeInterface $lastSearch = null;

    #[ORM\OneToMany(mappedBy: 'artist', targetEntity: Album::class, orphanRemoval: true, cascade: ['remove'])]
    /** @phpstan-ignore-next-line */
    private Collection $albums;

    public function __construct()
    {
        $this->albums = new ArrayCollection();
        $this->lastInfoSync = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getMbid(): ?string
    {
        return $this->mbid;
    }

    public function setMbid(?string $mbid): static
    {
        $this->mbid = $mbid;

        return $this;
    }

    public function getSpotifyId(): ?string
    {
        return $this->spotifyId;
    }

    public function setSpotifyId(?string $spotifyId): static
    {
        $this->spotifyId = $spotifyId;

        return $this;
    }

    public function getDisambiguation(): ?string
    {
        return $this->disambiguation;
    }

    public function setDisambiguation(?string $disambiguation): static
    {
        $this->disambiguation = $disambiguation;

        return $this;
    }

    public function getOverview(): ?string
    {
        return $this->overview;
    }

    public function setOverview(?string $overview): static
    {
        $this->overview = $overview;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getEnded(): ?DateTimeInterface
    {
        return $this->ended;
    }

    public function setEnded(?DateTimeInterface $ended): static
    {
        $this->ended = $ended;

        return $this;
    }

    public function getStarted(): ?DateTimeInterface
    {
        return $this->started;
    }

    public function setStarted(?DateTimeInterface $started): static
    {
        $this->started = $started;

        return $this;
    }

    public function getArtistFolderPath(): ?string
    {
        return $this->artistFolderPath;
    }

    public function setArtistFolderPath(?string $artistFolderPath): static
    {
        $this->artistFolderPath = $artistFolderPath;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;

        return $this;
    }

    public function isMonitored(): bool
    {
        return $this->monitored;
    }

    public function setMonitored(bool $monitored): static
    {
        $this->monitored = $monitored;

        return $this;
    }

    public function isMonitorNewItems(): bool
    {
        return $this->monitorNewItems;
    }

    public function setMonitorNewItems(bool $monitorNewItems): static
    {
        $this->monitorNewItems = $monitorNewItems;

        return $this;
    }

    public function getLastInfoSync(): DateTimeInterface
    {
        return $this->lastInfoSync;
    }

    public function setLastInfoSync(DateTimeInterface $lastInfoSync): static
    {
        $this->lastInfoSync = $lastInfoSync;

        return $this;
    }

    public function getLastSearch(): ?DateTimeInterface
    {
        return $this->lastSearch;
    }

    public function setLastSearch(?DateTimeInterface $lastSearch): static
    {
        $this->lastSearch = $lastSearch;

        return $this;
    }

    /**
     * @return Collection<int, Album>
     */
    public function getAlbums(): Collection
    {
        return $this->albums;
    }

    public function addAlbum(Album $album): static
    {
        if (!$this->albums->contains($album)) {
            $this->albums->add($album);
            $album->setArtist($this);
        }

        return $this;
    }

    public function removeAlbum(Album $album): static
    {
        if ($this->albums->removeElement($album)) {
            if ($album->getArtist() === $this) {
                $album->setArtist(null);
            }
        }

        return $this;
    }

    /**
     * Vérifie si l'image d'artiste existe physiquement.
     */
    public function hasArtistImage(): bool
    {
        // Normaliser l'imageUrl pour la vérification
        $normalizedImageUrl = $this->getArtistImageUrl();

        // Si on a une URL normalisée qui commence par /metadata/artists/ ou /media/artist/, vérifier si le fichier existe
        if ($normalizedImageUrl && (str_starts_with($normalizedImageUrl, '/metadata/artists/') || str_starts_with($normalizedImageUrl, '/media/artist/'))) {
            $projectRoot = \dirname(__DIR__, 2);
            if (str_starts_with($normalizedImageUrl, '/media/artist/')) {
                // Served via controller; assume existing file resolved at runtime
                return true;
            }
            $relativePath = mb_substr($normalizedImageUrl, 1); // Enlever le '/' initial
            $absolutePath = $projectRoot . '/public/' . $relativePath;

            if (file_exists($absolutePath)) {
                // Mettre à jour l'imageUrl si elle n'était pas correctement formatée
                if ($this->imageUrl !== $normalizedImageUrl) {
                    $this->imageUrl = $normalizedImageUrl;
                }

                return true;
            }
        }

        // Si pas d'image_url mais qu'on a un mbid, vérifier si l'image existe
        if (!$this->imageUrl && $this->mbid) {
            // Vérifier si l'image existe dans le dossier public/metadata/artists
            $projectRoot = \dirname(__DIR__, 2);
            $artistsDir = $projectRoot . '/public/metadata/artists';

            // Vérifier plusieurs extensions possibles
            $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            foreach ($extensions as $ext) {
                $imagePath = $artistsDir . '/' . $this->mbid . '.' . $ext;
                if (file_exists($imagePath)) {
                    // Mettre à jour l'imageUrl pour éviter de refaire la vérification
                    $this->imageUrl = '/metadata/artists/' . $this->mbid . '.' . $ext;

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Vérifie si l'image d'artiste est valide (format supporté et taille raisonnable).
     */
    public function isArtistImageValid(): bool
    {
        if (!$this->hasArtistImage()) {
            return false;
        }

        $normalizedImageUrl = $this->getArtistImageUrl();
        if (!$normalizedImageUrl) {
            return false;
        }

        $projectRoot = \dirname(__DIR__, 2);
        $relativePath = mb_substr($normalizedImageUrl, 1);
        $absolutePath = $projectRoot . '/public/' . $relativePath;

        if (!file_exists($absolutePath)) {
            return false;
        }

        // Vérifier la taille du fichier (max 10MB)
        $fileSize = filesize($absolutePath);
        if ($fileSize > 10 * 1024 * 1024) {
            return false;
        }

        // Vérifier le type MIME
        $finfo = finfo_open(\FILEINFO_MIME_TYPE);
        if (false === $finfo) {
            return false;
        }
        $mimeType = finfo_file($finfo, $absolutePath);
        finfo_close($finfo);

        $allowedMimeTypes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
        ];

        return \in_array($mimeType, $allowedMimeTypes, true);
    }

    /**
     * Obtient les informations détaillées de l'image d'artiste.
     *
     * @return array{width: int, height: int, mime_type: string, file_size: int, file_path: string, web_path: string, is_valid: bool}|null
     */
    public function getArtistImageInfo(): ?array
    {
        if (!$this->hasArtistImage()) {
            return null;
        }

        $normalizedImageUrl = $this->getArtistImageUrl();
        if (!$normalizedImageUrl) {
            return null;
        }

        $projectRoot = \dirname(__DIR__, 2);
        $relativePath = mb_substr($normalizedImageUrl, 1);
        $absolutePath = $projectRoot . '/public/' . $relativePath;

        if (!file_exists($absolutePath)) {
            return null;
        }

        $imageInfo = getimagesize($absolutePath);
        if (!$imageInfo) {
            return null;
        }

        $fileSize = filesize($absolutePath);
        if (false === $fileSize) {
            return null;
        }

        return [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'mime_type' => $imageInfo['mime'],
            'file_size' => $fileSize,
            'file_path' => $absolutePath,
            'web_path' => $normalizedImageUrl,
            'is_valid' => $this->isArtistImageValid(),
        ];
    }

    /**
     * Retourne l'URL de l'image d'artiste normalisée.
     */
    public function getArtistImageUrl(): ?string
    {
        if (!$this->imageUrl) {
            return null;
        }

        // Normaliser l'URL de l'image
        $imageUrl = $this->imageUrl;

        // Si l'URL commence par un chemin absolu web, la retourner telle quelle
        if (str_starts_with($imageUrl, '/')) {
            return $imageUrl;
        }

        // Si l'URL commence par "public/metadata/artists/", la convertir en chemin web
        if (str_starts_with($imageUrl, 'public/metadata/artists/')) {
            return '/' . mb_substr($imageUrl, 7); // Enlever "public/" du début
        }

        // Si l'URL commence par "metadata/artists/", ajouter le slash initial
        if (str_starts_with($imageUrl, 'metadata/artists/')) {
            return '/' . $imageUrl;
        }

        // Si l'URL ne commence pas par /metadata/artists/ ou /media/artist/, l'ajouter
        if (!str_starts_with($imageUrl, '/metadata/artists/') && !str_starts_with($imageUrl, '/media/artist/')) {
            return '/metadata/artists/' . $imageUrl;
        }

        return $imageUrl;
    }
}
