<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LibraryRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LibraryRepository::class)]
class Library
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $path = null;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column]
    private bool $scanAutomatically = true;

    #[ORM\Column]
    private int $scanInterval = 60;

    #[ORM\Column(type: 'datetime')]
    private DateTimeInterface $lastScan;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $qualityProfile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $metadataProfile = null;

    #[ORM\Column]
    private bool $monitorNewItems = true;

    #[ORM\Column]
    private bool $monitorExistingItems = true;

    public function __construct()
    {
        $this->lastScan = new DateTime();
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

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function isScanAutomatically(): bool
    {
        return $this->scanAutomatically;
    }

    public function setScanAutomatically(bool $scanAutomatically): static
    {
        $this->scanAutomatically = $scanAutomatically;

        return $this;
    }

    public function getScanInterval(): int
    {
        return $this->scanInterval;
    }

    public function setScanInterval(int $scanInterval): static
    {
        $this->scanInterval = $scanInterval;

        return $this;
    }

    public function getLastScan(): DateTimeInterface
    {
        return $this->lastScan;
    }

    public function setLastScan(DateTimeInterface $lastScan): static
    {
        $this->lastScan = $lastScan;

        return $this;
    }

    public function getQualityProfile(): ?string
    {
        return $this->qualityProfile;
    }

    public function setQualityProfile(?string $qualityProfile): static
    {
        $this->qualityProfile = $qualityProfile;

        return $this;
    }

    public function getMetadataProfile(): ?string
    {
        return $this->metadataProfile;
    }

    public function setMetadataProfile(?string $metadataProfile): static
    {
        $this->metadataProfile = $metadataProfile;

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

    public function isMonitorExistingItems(): bool
    {
        return $this->monitorExistingItems;
    }

    public function setMonitorExistingItems(bool $monitorExistingItems): static
    {
        $this->monitorExistingItems = $monitorExistingItems;

        return $this;
    }

    /**
     * Get all artists in this library
     * This method is used by the task processor to get artists for status updates.
     */
    public function getArtists(): array
    {
        // This method should return an array of Artist entities
        // Since this is a simple entity without relationships, we'll return an empty array
        // The actual implementation should be handled by the repository or service layer
        return [];
    }
}
