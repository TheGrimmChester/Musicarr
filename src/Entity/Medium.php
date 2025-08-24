<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MediumRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MediumRepository::class)]
#[ORM\Table(name: 'medium')]
class Medium
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $discId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mbid = null;

    #[ORM\Column]
    private int $position = 1;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $format = null;

    #[ORM\Column]
    private int $trackCount = 0;

    #[ORM\ManyToOne(inversedBy: 'mediums')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Album $album = null;

    #[ORM\OneToMany(mappedBy: 'medium', targetEntity: Track::class)]
    /** @phpstan-ignore-next-line */
    private Collection $tracks;

    public function __construct()
    {
        $this->tracks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDiscId(): ?string
    {
        return $this->discId;
    }

    public function setDiscId(?string $discId): static
    {
        $this->discId = $discId;

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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(?string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function getTrackCount(): int
    {
        return $this->trackCount;
    }

    public function setTrackCount(int $trackCount): static
    {
        $this->trackCount = $trackCount;

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

    /**
     * @return Collection<int, Track>
     */
    public function getTracks(): Collection
    {
        return $this->tracks;
    }

    public function addTrack(Track $track): static
    {
        if (!$this->tracks->contains($track)) {
            $this->tracks->add($track);
            $track->setMedium($this);
        }

        return $this;
    }

    public function removeTrack(Track $track): static
    {
        if ($this->tracks->removeElement($track)) {
            // set the owning side to null (unless already changed)
            if ($track->getMedium() === $this) {
                $track->setMedium(null);
            }
        }

        return $this;
    }

    /**
     * Get the display name for this medium (e.g., "CD 1", "Vinyl", "Digital Media").
     */
    public function getDisplayName(): string
    {
        if ($this->title) {
            return $this->title;
        }

        $format = $this->format ?: 'Medium';

        // For multi-disc releases, add position number
        if ($this->album && $this->album->getMediums()->count() > 1) {
            return $format . ' ' . $this->position;
        }

        return $format;
    }
}
