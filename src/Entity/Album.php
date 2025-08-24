<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AlbumRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AlbumRepository::class)]
#[ORM\Table(name: 'album', uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'unique_release_group_release', columns: ['release_group_mbid', 'release_mbid']),
])]
class Album
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: false)]
    private ?string $releaseMbid = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $releaseGroupMbid = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $disambiguation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $overview = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?DateTimeInterface $releaseDate = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $path = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column]
    private ?bool $monitored = true;

    #[ORM\Column]
    private ?bool $anyReleaseOk = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $lastInfoSync = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $lastSearch = null;

    #[ORM\ManyToOne(inversedBy: 'albums')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Artist $artist = null;

    #[ORM\OneToMany(mappedBy: 'album', targetEntity: Track::class, orphanRemoval: true, cascade: ['remove'])]
    /** @phpstan-ignore-next-line */
    private Collection $tracks;

    #[ORM\OneToMany(mappedBy: 'album', targetEntity: Medium::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    /** @phpstan-ignore-next-line */
    private Collection $mediums;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $albumType = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    /** @phpstan-ignore-next-line */
    private ?array $secondaryTypes = [];

    #[ORM\Column]
    private ?bool $downloaded = false;

    #[ORM\Column]
    private ?bool $hasFile = false;

    public function __construct()
    {
        $this->tracks = new ArrayCollection();
        $this->mediums = new ArrayCollection();
        $this->status = 'empty';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getReleaseMbid(): ?string
    {
        return $this->releaseMbid;
    }

    public function setReleaseMbid(?string $releaseMbid): static
    {
        $this->releaseMbid = $releaseMbid;

        return $this;
    }

    public function getReleaseGroupMbid(): ?string
    {
        return $this->releaseGroupMbid;
    }

    public function setReleaseGroupMbid(?string $releaseGroupMbid): static
    {
        $this->releaseGroupMbid = $releaseGroupMbid;

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

    public function getReleaseDate(): ?DateTimeInterface
    {
        return $this->releaseDate;
    }

    public function setReleaseDate(?DateTimeInterface $releaseDate): static
    {
        $this->releaseDate = $releaseDate;

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

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): static
    {
        $this->path = $path;

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

    /**
     * Génère l'URL de l'image de couverture en utilisant le release MBID.
     */
    public function getCoverImageUrl(): ?string
    {
        // Si une image URL est déjà définie, l'utiliser
        if ($this->imageUrl) {
            // Normaliser l'URL pour qu'elle commence par /
            if (str_starts_with($this->imageUrl, 'public/')) {
                return '/' . mb_substr($this->imageUrl, 7); // Enlever 'public/' et ajouter '/'
            } elseif (!str_starts_with($this->imageUrl, '/')) {
                return '/' . $this->imageUrl;
            }

            return $this->imageUrl;
        }

        // Sinon, générer l'URL basée sur le release MBID
        if ($this->releaseMbid) {
            return '/metadata/covers/' . $this->releaseMbid . '.jpg';
        }

        return null;
    }

    /**
     * Vérifie si l'image de couverture existe physiquement.
     */
    public function hasCoverImage(): bool
    {
        // Normaliser l'imageUrl pour la vérification
        $normalizedImageUrl = $this->getCoverImageUrl();

        // Si on a une URL normalisée qui commence par /metadata/covers/ ou /media/album/, vérifier si le fichier existe
        if ($normalizedImageUrl && (str_starts_with($normalizedImageUrl, '/metadata/covers/') || str_starts_with($normalizedImageUrl, '/media/album/'))) {
            $projectRoot = \dirname(__DIR__, 2);
            if (str_starts_with($normalizedImageUrl, '/media/album/')) {
                // Served by controller; assume exists
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

        // Si pas d'image_url mais qu'on a un release_mbid, vérifier si l'image existe
        if (!$this->imageUrl && $this->releaseMbid) {
            // Vérifier si l'image existe dans le dossier public/metadata/covers
            $projectRoot = \dirname(__DIR__, 2);
            $coversDir = $projectRoot . '/public/metadata/covers';
            $imagePath = $coversDir . '/' . $this->releaseMbid . '.jpg';

            if (file_exists($imagePath)) {
                // Mettre à jour l'imageUrl pour éviter de refaire la vérification
                $this->imageUrl = '/metadata/covers/' . $this->releaseMbid . '.jpg';

                return true;
            }
        }

        return false;
    }

    public function isMonitored(): ?bool
    {
        return $this->monitored;
    }

    public function setMonitored(bool $monitored): static
    {
        $this->monitored = $monitored;

        return $this;
    }

    public function isAnyReleaseOk(): ?bool
    {
        return $this->anyReleaseOk;
    }

    public function setAnyReleaseOk(bool $anyReleaseOk): static
    {
        $this->anyReleaseOk = $anyReleaseOk;

        return $this;
    }

    public function getLastInfoSync(): ?DateTimeInterface
    {
        return $this->lastInfoSync;
    }

    public function setLastInfoSync(?DateTimeInterface $lastInfoSync): static
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

    public function getAlbumType(): ?string
    {
        return $this->albumType;
    }

    public function setAlbumType(?string $albumType): static
    {
        $this->albumType = $albumType;

        return $this;
    }

    /**
     * @return array<int, string>|null
     */
    public function getSecondaryTypes(): ?array
    {
        return $this->secondaryTypes;
    }

    /**
     * @param array<int, string>|null $secondaryTypes
     */
    public function setSecondaryTypes(?array $secondaryTypes): static
    {
        $this->secondaryTypes = $secondaryTypes;

        return $this;
    }

    public function isDownloaded(): ?bool
    {
        return $this->downloaded;
    }

    public function setDownloaded(bool $downloaded): static
    {
        $this->downloaded = $downloaded;

        return $this;
    }

    public function isHasFile(): ?bool
    {
        return $this->hasFile;
    }

    public function setHasFile(bool $hasFile): static
    {
        $this->hasFile = $hasFile;

        return $this;
    }

    public function getArtist(): ?Artist
    {
        return $this->artist;
    }

    public function setArtist(?Artist $artist): static
    {
        $this->artist = $artist;

        return $this;
    }

    /**
     * @return Collection<int, Track>
     */
    public function getTracks(): Collection
    {
        return $this->tracks;
    }

    /**
     * Check if this album is a single.
     */
    public function isSingle(): bool
    {
        // Check primary album type
        if ('Single' === $this->albumType) {
            return true;
        }

        // Check secondary types for single indicators
        if ($this->secondaryTypes) {
            $singleTypes = ['Single', 'EP'];
            foreach ($this->secondaryTypes as $type) {
                if (\in_array($type, $singleTypes, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function addTrack(Track $track): static
    {
        if (!$this->tracks->contains($track)) {
            $this->tracks->add($track);
            $track->setAlbum($this);
        }

        return $this;
    }

    public function removeTrack(Track $track): static
    {
        if ($this->tracks->removeElement($track)) {
            // set the owning side to null (unless already changed)
            if ($track->getAlbum() === $this) {
                $track->setAlbum(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Medium>
     */
    public function getMediums(): Collection
    {
        return $this->mediums;
    }

    public function addMedium(Medium $medium): static
    {
        if (!$this->mediums->contains($medium)) {
            $this->mediums->add($medium);
            $medium->setAlbum($this);
        }

        return $this;
    }

    public function removeMedium(Medium $medium): static
    {
        if ($this->mediums->removeElement($medium)) {
            // set the owning side to null (unless already changed)
            if ($medium->getAlbum() === $this) {
                $medium->setAlbum(null);
            }

            // Set tracks' medium to null before removing the medium
            foreach ($medium->getTracks() as $track) {
                $track->setMedium(null);
            }
        }

        return $this;
    }
}
