<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LibraryStatisticRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LibraryStatisticRepository::class)]
#[ORM\Table(name: 'library_statistics')]
class LibraryStatistic
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Library::class)]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private ?Library $library = null;

    #[ORM\Column]
    private ?int $totalArtists = 0;

    #[ORM\Column]
    private ?int $totalAlbums = 0;

    #[ORM\Column]
    private ?int $totalTracks = 0;

    #[ORM\Column]
    private ?int $downloadedAlbums = 0;

    #[ORM\Column]
    private ?int $downloadedTracks = 0;

    #[ORM\Column]
    private ?int $totalSingles = 0;

    #[ORM\Column]
    private ?int $downloadedSingles = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLibrary(): ?Library
    {
        return $this->library;
    }

    public function setLibrary(Library $library): static
    {
        $this->library = $library;

        return $this;
    }

    public function getTotalArtists(): ?int
    {
        return $this->totalArtists;
    }

    public function setTotalArtists(int $totalArtists): static
    {
        $this->totalArtists = $totalArtists;

        return $this;
    }

    public function getTotalAlbums(): ?int
    {
        return $this->totalAlbums;
    }

    public function setTotalAlbums(int $totalAlbums): static
    {
        $this->totalAlbums = $totalAlbums;

        return $this;
    }

    public function getTotalTracks(): ?int
    {
        return $this->totalTracks;
    }

    public function setTotalTracks(int $totalTracks): static
    {
        $this->totalTracks = $totalTracks;

        return $this;
    }

    public function getDownloadedAlbums(): ?int
    {
        return $this->downloadedAlbums;
    }

    public function setDownloadedAlbums(int $downloadedAlbums): static
    {
        $this->downloadedAlbums = $downloadedAlbums;

        return $this;
    }

    public function getDownloadedTracks(): ?int
    {
        return $this->downloadedTracks;
    }

    public function setDownloadedTracks(int $downloadedTracks): static
    {
        $this->downloadedTracks = $downloadedTracks;

        return $this;
    }

    public function getTotalSingles(): ?int
    {
        return $this->totalSingles;
    }

    public function setTotalSingles(int $totalSingles): static
    {
        $this->totalSingles = $totalSingles;

        return $this;
    }

    public function getDownloadedSingles(): ?int
    {
        return $this->downloadedSingles;
    }

    public function setDownloadedSingles(int $downloadedSingles): static
    {
        $this->downloadedSingles = $downloadedSingles;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Update the updatedAt timestamp.
     */
    public function touch(): static
    {
        $this->updatedAt = new DateTime();

        return $this;
    }

    /**
     * Convert to array format compatible with existing getLibraryStats method.
     */
    public function toArray(): array
    {
        return [
            'totalArtists' => $this->totalArtists,
            'totalAlbums' => $this->totalAlbums,
            'totalTracks' => $this->totalTracks,
            'downloadedAlbums' => $this->downloadedAlbums,
            'downloadedTracks' => $this->downloadedTracks,
            'totalSingles' => $this->totalSingles,
            'downloadedSingles' => $this->downloadedSingles,
            'library_name' => $this->library?->getName(),
            'library_path' => $this->library?->getPath(),
        ];
    }

    /**
     * Check if statistics are stale (older than specified minutes).
     */
    public function isStale(int $maxAgeMinutes = 60): bool
    {
        if (!$this->updatedAt) {
            return true;
        }

        $threshold = new DateTime();
        $threshold->modify("-{$maxAgeMinutes} minutes");

        return $this->updatedAt < $threshold;
    }
}
