<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AlbumStatisticRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AlbumStatisticRepository::class)]
#[ORM\Table(name: 'album_statistics')]
class AlbumStatistic
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Album::class)]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private ?Album $album = null;

    #[ORM\Column]
    private ?int $totalTracks = 0;

    #[ORM\Column]
    private ?int $downloadedTracks = 0;

    #[ORM\Column]
    private ?int $monitoredTracks = 0;

    #[ORM\Column]
    private ?int $tracksWithFiles = 0;

    #[ORM\Column(nullable: true)]
    private ?int $totalDuration = null; // in seconds

    #[ORM\Column(nullable: true)]
    private ?int $averageTrackDuration = null; // in seconds

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $completionPercentage = null;

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

    public function getAlbum(): ?Album
    {
        return $this->album;
    }

    public function setAlbum(Album $album): static
    {
        $this->album = $album;

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

    public function getDownloadedTracks(): ?int
    {
        return $this->downloadedTracks;
    }

    public function setDownloadedTracks(int $downloadedTracks): static
    {
        $this->downloadedTracks = $downloadedTracks;

        return $this;
    }

    public function getMonitoredTracks(): ?int
    {
        return $this->monitoredTracks;
    }

    public function setMonitoredTracks(int $monitoredTracks): static
    {
        $this->monitoredTracks = $monitoredTracks;

        return $this;
    }

    public function getTracksWithFiles(): ?int
    {
        return $this->tracksWithFiles;
    }

    public function setTracksWithFiles(int $tracksWithFiles): static
    {
        $this->tracksWithFiles = $tracksWithFiles;

        return $this;
    }

    public function getTotalDuration(): ?int
    {
        return $this->totalDuration;
    }

    public function setTotalDuration(?int $totalDuration): static
    {
        $this->totalDuration = $totalDuration;

        return $this;
    }

    public function getAverageTrackDuration(): ?int
    {
        return $this->averageTrackDuration;
    }

    public function setAverageTrackDuration(?int $averageTrackDuration): static
    {
        $this->averageTrackDuration = $averageTrackDuration;

        return $this;
    }

    public function getCompletionPercentage(): ?string
    {
        return $this->completionPercentage;
    }

    public function setCompletionPercentage(?string $completionPercentage): static
    {
        $this->completionPercentage = $completionPercentage;

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
     * Convert to array format.
     */
    public function toArray(): array
    {
        return [
            'totalTracks' => $this->totalTracks,
            'downloadedTracks' => $this->downloadedTracks,
            'monitoredTracks' => $this->monitoredTracks,
            'tracksWithFiles' => $this->tracksWithFiles,
            'totalDuration' => $this->totalDuration,
            'averageTrackDuration' => $this->averageTrackDuration,
            'completionPercentage' => $this->completionPercentage,
            'album_title' => $this->album?->getTitle(),
            'album_type' => $this->album?->getAlbumType(),
            'artist_name' => $this->album?->getArtist()?->getName(),
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

    /**
     * Get formatted total duration.
     */
    public function getFormattedTotalDuration(): ?string
    {
        if (!$this->totalDuration) {
            return null;
        }

        $hours = (int) ($this->totalDuration / 3600);
        $minutes = (int) (($this->totalDuration % 3600) / 60);
        $seconds = $this->totalDuration % 60;

        if ($hours > 0) {
            return \sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return \sprintf('%d:%02d', $minutes, $seconds);
    }

    /**
     * Get formatted average track duration.
     */
    public function getFormattedAverageTrackDuration(): ?string
    {
        if (!$this->averageTrackDuration) {
            return null;
        }

        $minutes = (int) ($this->averageTrackDuration / 60);
        $seconds = $this->averageTrackDuration % 60;

        return \sprintf('%d:%02d', $minutes, $seconds);
    }
}
