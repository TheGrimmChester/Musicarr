<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UnmatchedTrackRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UnmatchedTrackRepository::class)]
#[ORM\Table(name: 'unmatched_track')]
class UnmatchedTrack
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $filePath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fileName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $artist = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $album = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $trackNumber = null;

    #[ORM\Column(nullable: true)]
    private ?int $year = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $extension = null;

    #[ORM\Column(nullable: true)]
    private ?int $duration = null;

    #[ORM\Column(nullable: true)]
    private ?int $fileSize = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $discoveredAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $lastAttemptedMatch = null;

    #[ORM\Column]
    private bool $isMatched = false;

    #[ORM\ManyToOne(targetEntity: Library::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Library $library = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lyricsFilepath = null;

    public function __construct()
    {
        $this->discoveredAt = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): static
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(?string $fileName): static
    {
        $this->fileName = $fileName;

        return $this;
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

    public function getArtist(): ?string
    {
        return $this->artist;
    }

    public function setArtist(?string $artist): static
    {
        $this->artist = $artist;

        return $this;
    }

    public function getAlbum(): ?string
    {
        return $this->album;
    }

    public function setAlbum(?string $album): static
    {
        $this->album = $album;

        return $this;
    }

    public function getTrackNumber(): ?string
    {
        return $this->trackNumber;
    }

    public function setTrackNumber(?string $trackNumber): static
    {
        $this->trackNumber = $trackNumber;

        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(?int $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function setExtension(?string $extension): static
    {
        $this->extension = $extension;

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

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(?int $fileSize): static
    {
        $this->fileSize = $fileSize;

        return $this;
    }

    public function getDiscoveredAt(): ?DateTimeInterface
    {
        return $this->discoveredAt;
    }

    public function setDiscoveredAt(DateTime $discoveredAt): static
    {
        $this->discoveredAt = $discoveredAt;

        return $this;
    }

    public function getLastAttemptedMatch(): ?DateTimeInterface
    {
        return $this->lastAttemptedMatch;
    }

    public function setLastAttemptedMatch(?DateTime $lastAttemptedMatch): static
    {
        $this->lastAttemptedMatch = $lastAttemptedMatch;

        return $this;
    }

    public function isMatched(): bool
    {
        return $this->isMatched;
    }

    public function setIsMatched(bool $isMatched): static
    {
        $this->isMatched = $isMatched;

        return $this;
    }

    public function getLibrary(): ?Library
    {
        return $this->library;
    }

    public function setLibrary(?Library $library): static
    {
        $this->library = $library;

        return $this;
    }

    public function getLyricsFilepath(): ?string
    {
        return $this->lyricsFilepath;
    }

    public function setLyricsFilepath(?string $lyricsFilepath): static
    {
        $this->lyricsFilepath = $lyricsFilepath;

        return $this;
    }

    /**
     * Get the audio quality of the track.
     */
    public function getQuality(): ?string
    {
        // Extract quality from filename if available
        if ($this->fileName) {
            // Common quality patterns in filenames
            if (preg_match('/\[(FLAC|MP3|AAC|OGG|WAV|ALAC|APE|M4A)\s*[-\s]*([\d.]+)\s*kbps?[-\s]*(\d+_\d+)?kHz?\]/i', $this->fileName, $matches)) {
                $format = mb_strtoupper($matches[1]);
                $bitrate = $matches[2];
                $sampleRate = isset($matches[3]) ? $matches[3] : '';

                if ($sampleRate) {
                    return \sprintf('%s %s kbps %s kHz', $format, $bitrate, $sampleRate);
                }

                return \sprintf('%s %s kbps', $format, $bitrate);
            }

            // Simple format detection
            if (preg_match('/\.(flac|mp3|aac|ogg|wav|alac|ape|m4a)$/i', $this->fileName, $matches)) {
                return mb_strtoupper($matches[1]);
            }
        }

        return null;
    }

    /**
     * Get the audio format of the track.
     */
    public function getFormat(): ?string
    {
        if ($this->extension) {
            return mb_strtoupper($this->extension);
        }

        if ($this->fileName) {
            if (preg_match('/\.(flac|mp3|aac|ogg|wav|alac|ape|m4a)$/i', $this->fileName, $matches)) {
                return mb_strtoupper($matches[1]);
            }
        }

        return null;
    }

    /**
     * Automatically discovers and sets the lyrics filepath by searching for .lrc files
     * with the same base name as the audio file.
     */
    public function discoverLyricsFilepath(): ?string
    {
        if (!$this->filePath) {
            return null;
        }

        $audioDir = \dirname($this->filePath);
        $audioName = pathinfo($this->filePath, \PATHINFO_FILENAME);

        // Extensions de fichiers de paroles courantes
        $lyricsExtensions = ['lrc', 'txt', 'lyr', 'srt'];

        // Essayer d'abord avec le nom exact dans le même répertoire
        foreach ($lyricsExtensions as $ext) {
            $lyricsPath = $audioDir . '/' . $audioName . '.' . $ext;
            if (file_exists($lyricsPath)) {
                $this->lyricsFilepath = $lyricsPath;

                return $lyricsPath;
            }
        }

        // Si pas trouvé, essayer de nettoyer le nom (enlever les numéros de piste et infos de qualité)
        $cleanName = $this->cleanFileNameForLyrics($audioName);

        if ($cleanName !== $audioName) {
            // Chercher dans le même répertoire
            foreach ($lyricsExtensions as $ext) {
                $lyricsPath = $audioDir . '/' . $cleanName . '.' . $ext;
                if (file_exists($lyricsPath)) {
                    $this->lyricsFilepath = $lyricsPath;

                    return $lyricsPath;
                }
            }

            // Chercher dans les répertoires parents (jusqu'à 2 niveaux)
            $parentDir = \dirname($audioDir);
            foreach ($lyricsExtensions as $ext) {
                $lyricsPath = $parentDir . '/' . $cleanName . '.' . $ext;
                if (file_exists($lyricsPath)) {
                    $this->lyricsFilepath = $lyricsPath;

                    return $lyricsPath;
                }
            }

            // Chercher dans le répertoire parent du parent
            $grandParentDir = \dirname($parentDir);
            foreach ($lyricsExtensions as $ext) {
                $lyricsPath = $grandParentDir . '/' . $cleanName . '.' . $ext;
                if (file_exists($lyricsPath)) {
                    $this->lyricsFilepath = $lyricsPath;

                    return $lyricsPath;
                }
            }

            // Chercher dans les répertoires frères (même niveau que le répertoire audio)
            $parentDir = \dirname($audioDir);

            // Lister les répertoires frères
            $siblingDirs = glob($parentDir . '/*', \GLOB_ONLYDIR);
            if (false === $siblingDirs) {
                $siblingDirs = [];
            }
            foreach ($siblingDirs as $siblingDir) {
                // Chercher avec le nom nettoyé
                foreach ($lyricsExtensions as $ext) {
                    $lyricsPath = $siblingDir . '/' . $cleanName . '.' . $ext;
                    if (file_exists($lyricsPath)) {
                        $this->lyricsFilepath = $lyricsPath;

                        return $lyricsPath;
                    }
                }

                // Chercher avec des patterns plus flexibles (avec qualité)
                foreach ($lyricsExtensions as $ext) {
                    // Pattern avec qualité
                    $lyricsPath = $siblingDir . '/' . $cleanName . ' (FLAC 24 Hi-Res_44.1 kHz).' . $ext;
                    if (file_exists($lyricsPath)) {
                        $this->lyricsFilepath = $lyricsPath;

                        return $lyricsPath;
                    }

                    // Pattern avec qualité alternative
                    $lyricsPath = $siblingDir . '/' . $cleanName . ' (FLAC 16_44.1 kHz).' . $ext;
                    if (file_exists($lyricsPath)) {
                        $this->lyricsFilepath = $lyricsPath;

                        return $lyricsPath;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Nettoie le nom de fichier pour la recherche de paroles.
     */
    private function cleanFileNameForLyrics(string $fileName): string
    {
        // Enlever les numéros de piste au début (ex: "17 - ")
        $cleanName = preg_replace('/^\d+\s*-\s*/', '', $fileName);
        if (null === $cleanName) {
            $cleanName = $fileName;
        }

        // Enlever les informations de qualité à la fin (ex: "[FLAC - 1763.7 kbps - 24_44.1kHz]")
        $cleanName = preg_replace('/\s*\[.*?\]$/', '', $cleanName);
        if (null === $cleanName) {
            $cleanName = $fileName;
        }

        return $cleanName;
    }

    /**
     * Vérifie si la piste non associée a des paroles.
     */
    public function hasLyrics(): bool
    {
        if (!$this->lyricsFilepath) {
            // Try to discover lyrics if not already set
            $this->discoverLyricsFilepath();
        }

        return null !== $this->lyricsFilepath && file_exists($this->lyricsFilepath);
    }

    /**
     * Retourne le chemin relatif du fichier par rapport à la racine de la bibliothèque.
     */
    public function getRelativePath(): ?string
    {
        if (!$this->filePath || !$this->library || !$this->library->getPath()) {
            return $this->fileName;
        }

        $libraryPath = mb_rtrim($this->library->getPath(), '/');
        $filePath = $this->filePath;

        // Si le chemin du fichier commence par le chemin de la bibliothèque
        if (0 === mb_strpos($filePath, $libraryPath)) {
            $relativePath = mb_substr($filePath, mb_strlen($libraryPath));

            return mb_ltrim($relativePath, '/');
        }

        // Fallback vers le nom de fichier si le chemin ne correspond pas
        return $this->fileName;
    }
}
