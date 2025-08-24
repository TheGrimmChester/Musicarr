<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TrackRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrackRepository::class)]
class Track
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true, unique: true)]
    private ?string $mbid = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $disambiguation = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $overview = null;

    #[ORM\Column(length: 10)]
    private string $trackNumber = '';

    #[ORM\Column]
    private int $mediumNumber = 0;

    #[ORM\Column(nullable: true)]
    private ?int $duration = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $path = null;

    #[ORM\Column]
    private bool $monitored = true;

    #[ORM\Column]
    private bool $downloaded = false;

    #[ORM\Column]
    private bool $hasFile = false;

    #[ORM\Column(type: 'datetime')]
    private DateTimeInterface $lastInfoSync;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTimeInterface $lastSearch = null;

    #[ORM\ManyToOne(inversedBy: 'tracks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Album $album = null;

    #[ORM\ManyToOne(inversedBy: 'tracks')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Medium $medium = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $artistName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $albumTitle = null;

    #[ORM\OneToMany(mappedBy: 'track', targetEntity: TrackFile::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    /** @phpstan-ignore-next-line */
    private Collection $files;

    public function __construct()
    {
        $this->lastInfoSync = new DateTime();
        $this->files = new ArrayCollection();
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

    public function getMbid(): ?string
    {
        return $this->mbid;
    }

    public function setMbid(?string $mbid): static
    {
        $this->mbid = $mbid;

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

    public function getTrackNumber(): string
    {
        return $this->trackNumber;
    }

    public function setTrackNumber(string $trackNumber): static
    {
        $this->trackNumber = $trackNumber;

        return $this;
    }

    public function getMediumNumber(): int
    {
        return $this->mediumNumber;
    }

    public function setMediumNumber(int $mediumNumber): static
    {
        $this->mediumNumber = $mediumNumber;

        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): static
    {
        $this->duration = $duration;

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

    public function isMonitored(): bool
    {
        return $this->monitored;
    }

    public function setMonitored(bool $monitored): static
    {
        $this->monitored = $monitored;

        return $this;
    }

    public function isDownloaded(): bool
    {
        return $this->downloaded;
    }

    public function setDownloaded(bool $downloaded): static
    {
        $this->downloaded = $downloaded;

        return $this;
    }

    public function isHasFile(): bool
    {
        return $this->hasFile;
    }

    public function setHasFile(bool $hasFile): static
    {
        $this->hasFile = $hasFile;

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

    public function getAlbum(): ?Album
    {
        return $this->album;
    }

    public function setAlbum(?Album $album): static
    {
        $this->album = $album;

        return $this;
    }

    public function getMedium(): ?Medium
    {
        return $this->medium;
    }

    public function setMedium(?Medium $medium): static
    {
        $this->medium = $medium;

        return $this;
    }

    public function getArtistName(): ?string
    {
        return $this->artistName;
    }

    public function setArtistName(?string $artistName): static
    {
        $this->artistName = $artistName;

        return $this;
    }

    public function getAlbumTitle(): ?string
    {
        return $this->albumTitle;
    }

    public function setAlbumTitle(?string $albumTitle): static
    {
        $this->albumTitle = $albumTitle;

        return $this;
    }

    /**
     * @return Collection<int, TrackFile>
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function addFile(TrackFile $file): static
    {
        if (!$this->files->contains($file)) {
            $this->files->add($file);
            $file->setTrack($this);
        }

        return $this;
    }

    public function removeFile(TrackFile $file): static
    {
        if ($this->files->removeElement($file)) {
            // set the owning side to null (unless already changed)
            if ($file->getTrack() === $this) {
                $file->setTrack(null);
            }
        }

        return $this;
    }

    public function needRename(): ?bool
    {
        if ($this->files->isEmpty()) {
            return false;
        }

        /** @var TrackFile $file */
        foreach ($this->files as $file) {
            if ($file->isNeedRename()) {
                return true;
            }
        }

        return false;
    }

    public function hasFiles(): bool
    {
        return !$this->files->isEmpty();
    }

    /**
     * Vérifie si la piste a des paroles.
     */
    public function hasLyrics(): bool
    {
        /** @var TrackFile $file */
        foreach ($this->files as $file) {
            if ($file->getLyricsPath() && file_exists($file->getLyricsPath())) {
                return true;
            }
        }

        return false;
    }
}
