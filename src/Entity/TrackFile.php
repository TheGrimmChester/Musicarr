<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TrackFileRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrackFileRepository::class)]
class TrackFile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $filePath = null;

    #[ORM\Column]
    private int $fileSize = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $quality = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $format = null;

    #[ORM\Column]
    private int $duration = 0;

    #[ORM\Column(type: 'datetime')]
    private DateTimeInterface $addedAt;

    #[ORM\ManyToOne(inversedBy: 'files')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Track $track = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lyricsPath = null;

    #[ORM\Column()]
    private bool $needRename = true;

    public function __construct()
    {
        $this->addedAt = new DateTime();
        $this->needRename = true; // Default to true, will be calculated during audio analysis
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

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function setFileSize(int $fileSize): static
    {
        $this->fileSize = $fileSize;

        return $this;
    }

    public function getQuality(): ?string
    {
        return $this->quality;
    }

    public function setQuality(?string $quality): static
    {
        $this->quality = $quality;

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

    public function getDuration(): int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function getAddedAt(): DateTimeInterface
    {
        return $this->addedAt;
    }

    public function setAddedAt(DateTimeInterface $addedAt): static
    {
        $this->addedAt = $addedAt;

        return $this;
    }

    public function getTrack(): ?Track
    {
        return $this->track;
    }

    public function setTrack(?Track $track): static
    {
        $this->track = $track;

        return $this;
    }

    public function getLyricsPath(): ?string
    {
        return $this->lyricsPath;
    }

    public function setLyricsPath(?string $lyricsPath): static
    {
        $this->lyricsPath = $lyricsPath;

        return $this;
    }

    public function isNeedRename(): bool
    {
        return $this->needRename;
    }

    public function setNeedRename(bool $needRename): static
    {
        $this->needRename = $needRename;

        return $this;
    }

    public function __toString(): string
    {
        return $this->filePath ?? 'TrackFile#' . $this->id;
    }

    /**
     * Retourne le chemin relatif du fichier par rapport à la racine de la bibliothèque.
     */
    public function getRelativePath(): ?string
    {
        if (!$this->filePath || !$this->track) {
            return basename($this->filePath ?? '');
        }

        $artist = $this->track->getAlbum()?->getArtist();
        if (!$artist || !$artist->getArtistFolderPath()) {
            return basename($this->filePath);
        }

        $artistPath = mb_rtrim($artist->getArtistFolderPath(), '/');
        $filePath = $this->filePath;

        // Si le chemin du fichier commence par le chemin de l'artiste
        if (0 === mb_strpos($filePath, $artistPath)) {
            $relativePath = mb_substr($filePath, mb_strlen($artistPath));

            return mb_ltrim($relativePath, '/');
        }

        // Fallback vers le nom de fichier si le chemin ne correspond pas
        return basename($this->filePath);
    }

    /**
     * Calcule un score de qualité pour déterminer le meilleur fichier.
     */
    public function getQualityScore(): int
    {
        if (!$this->quality || !$this->format) {
            return 0;
        }

        $score = 0;
        $format = mb_strtoupper($this->format);
        $quality = mb_strtolower($this->quality);

        // Score de base par format
        switch ($format) {
            case 'FLAC':
            case 'ALAC':
                $score += 1000; // Formats lossless

                break;
            case 'WAV':
                $score += 900; // Format lossless mais moins compressé

                break;
            case 'AAC':
                $score += 600; // Format lossy mais efficace

                break;
            case 'OGG':
                $score += 500; // Format lossy

                break;
            case 'MP3':
                $score += 400; // Format lossy standard

                break;
            default:
                $score += 200; // Format inconnu
        }

        // Bonus pour les bitrates élevés
        if (preg_match('/(\d+)\s*kbps/i', $quality, $matches)) {
            $bitrate = (int) $matches[1];
            if ($bitrate >= 320) {
                $score += 200;
            } elseif ($bitrate >= 256) {
                $score += 150;
            } elseif ($bitrate >= 192) {
                $score += 100;
            } elseif ($bitrate >= 128) {
                $score += 50;
            }
        }

        // Bonus pour les fréquences d'échantillonnage élevées
        if (preg_match('/(\d+)\s*khz/i', $quality, $matches)) {
            $sampleRate = (int) $matches[1];
            if ($sampleRate >= 96) {
                $score += 100;
            } elseif ($sampleRate >= 48) {
                $score += 50;
            } elseif ($sampleRate >= 44) {
                $score += 25;
            }
        }

        // Bonus pour les bits par échantillon élevés
        if (preg_match('/(\d+)bit/i', $quality, $matches)) {
            $bitsPerSample = (int) $matches[1];
            if ($bitsPerSample >= 24) {
                $score += 100;
            } elseif ($bitsPerSample >= 16) {
                $score += 50;
            }
        }

        // Bonus pour les formats lossless spécifiques
        if (false !== mb_strpos($quality, 'lossless')) {
            $score += 300;
        }

        return $score;
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
                $this->lyricsPath = $lyricsPath;

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
                    $this->lyricsPath = $lyricsPath;

                    return $lyricsPath;
                }
            }

            // Chercher dans les répertoires parents (jusqu'à 2 niveaux)
            $parentDir = \dirname($audioDir);
            foreach ($lyricsExtensions as $ext) {
                $lyricsPath = $parentDir . '/' . $cleanName . '.' . $ext;
                if (file_exists($lyricsPath)) {
                    $this->lyricsPath = $lyricsPath;

                    return $lyricsPath;
                }
            }

            // Chercher dans le répertoire parent du parent
            $grandParentDir = \dirname($parentDir);
            foreach ($lyricsExtensions as $ext) {
                $lyricsPath = $grandParentDir . '/' . $cleanName . '.' . $ext;
                if (file_exists($lyricsPath)) {
                    $this->lyricsPath = $lyricsPath;

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
                        $this->lyricsPath = $lyricsPath;

                        return $lyricsPath;
                    }
                }

                // Chercher avec des patterns plus flexibles (avec qualité)
                foreach ($lyricsExtensions as $ext) {
                    // Pattern avec qualité
                    $lyricsPath = $siblingDir . '/' . $cleanName . ' (FLAC 24 Hi-Res_44.1 kHz).' . $ext;
                    if (file_exists($lyricsPath)) {
                        $this->lyricsPath = $lyricsPath;

                        return $lyricsPath;
                    }

                    // Pattern avec qualité alternative
                    $lyricsPath = $siblingDir . '/' . $cleanName . ' (FLAC 16_44.1 kHz).' . $ext;
                    if (file_exists($lyricsPath)) {
                        $this->lyricsPath = $lyricsPath;

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
}
