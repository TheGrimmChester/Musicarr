<?php

declare(strict_types=1);

namespace App\Analyzer;

use Exception;
use getID3;
use Symfony\Contracts\Translation\TranslatorInterface;

class Id3AudioQualityAnalyzer
{
    private getID3 $getID3;
    private TranslatorInterface $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->getID3 = new getID3();
        $this->translator = $translator;
    }

    public function rawAudioFile(string $filePath): array
    {
        return $this->getID3->analyze($filePath);
    }

    /**
     * Analyse un fichier audio et retourne ses informations de qualité et métadonnées.
     */
    public function analyzeAudioFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [
                'error' => $this->translator->trans('api.log.file_not_found'),
                'format' => null,
                'channels' => null,
                'bitrate' => null,
                'sample_rate' => null,
                'bits_per_sample' => null,
                'duration' => null,
                'quality_string' => null,
                'metadata' => [
                    'artist' => null,
                    'album' => null,
                    'title' => null,
                    'track_number' => null,
                    'year' => null,
                    'genre' => null,
                    'comment' => null,
                    'composer' => null,
                    'album_artist' => null,
                    'disc_number' => null,
                    'total_tracks' => null,
                    'total_discs' => null,
                ],
            ];
        }

        try {
            $fileInfo = $this->getID3->analyze($filePath);

            if (isset($fileInfo['error'])) {
                return [
                    'error' => $this->translator->trans('api.log.analysis_error') . ': ' . implode(', ', $fileInfo['error']),
                    'format' => null,
                    'channels' => null,
                    'bitrate' => null,
                    'sample_rate' => null,
                    'bits_per_sample' => null,
                    'duration' => null,
                    'quality_string' => null,
                    'metadata' => [
                        'artist' => null,
                        'album' => null,
                        'title' => null,
                        'track_number' => null,
                        'year' => null,
                        'genre' => null,
                        'comment' => null,
                        'composer' => null,
                        'album_artist' => null,
                        'disc_number' => null,
                        'total_tracks' => null,
                        'total_discs' => null,
                    ],
                ];
            }

            // Extraire les informations de base
            $format = mb_strtoupper($fileInfo['fileformat'] ?? 'UNKNOWN');
            $channels = $fileInfo['audio']['channels'] ?? null;
            $bitrate = $fileInfo['audio']['bitrate'] ?? null;
            $sampleRate = $fileInfo['audio']['sample_rate'] ?? null;
            $bitsPerSample = $fileInfo['audio']['bits_per_sample'] ?? null;
            $duration = $fileInfo['playtime_seconds'] ?? null;

            // Extraire les métadonnées
            $metadata = $this->extractMetadata($fileInfo);

            // Formater le bitrate
            $formattedBitrate = $this->formatBitrate((int) $bitrate);

            // Formater la fréquence d'échantillonnage
            $formattedSampleRate = $this->formatSampleRate($sampleRate);

            // Formater les canaux
            $formattedChannels = $this->formatChannels($channels);

            // Formater les bits par échantillon
            $formattedBitsPerSample = $this->formatBitsPerSample($bitsPerSample);

            // Construire la chaîne de qualité
            $qualityString = $this->buildQualityString(
                $format,
                $formattedChannels,
                $formattedBitrate,
                $formattedSampleRate,
                $formattedBitsPerSample
            );

            return [
                'error' => null,
                'format' => $format,
                'channels' => $channels,
                'bitrate' => $bitrate,
                'sample_rate' => $sampleRate,
                'bits_per_sample' => $bitsPerSample,
                'duration' => $duration,
                'quality_string' => $qualityString,
                'formatted' => [
                    'channels' => $formattedChannels,
                    'bitrate' => $formattedBitrate,
                    'sample_rate' => $formattedSampleRate,
                    'bits_per_sample' => $formattedBitsPerSample,
                ],
                'metadata' => $metadata,
            ];
        } catch (Exception $e) {
            return [
                'error' => $this->translator->trans('api.log.analysis_exception') . ': ' . $e->getMessage(),
                'format' => null,
                'channels' => null,
                'bitrate' => null,
                'sample_rate' => null,
                'bits_per_sample' => null,
                'duration' => null,
                'quality_string' => null,
                'metadata' => [
                    'artist' => null,
                    'album' => null,
                    'title' => null,
                    'track_number' => null,
                    'year' => null,
                    'genre' => null,
                    'comment' => null,
                    'composer' => null,
                    'album_artist' => null,
                    'disc_number' => null,
                    'total_tracks' => null,
                    'total_discs' => null,
                ],
            ];
        }
    }

    /**
     * Extrait les métadonnées du fichier audio.
     */
    private function extractMetadata(array $fileInfo): array
    {
        $metadata = [
            'artist' => null,
            'album' => null,
            'title' => null,
            'track_number' => null,
            'year' => null,
            'genre' => null,
            'comment' => null,
            'composer' => null,
            'album_artist' => null,
            'performer' => null,
            'disc_number' => null,
            'total_tracks' => null,
            'total_discs' => null,
        ];

        $tagSources = ['id3v2', 'id3v1', 'vorbiscomment', 'quicktime', 'ape', 'asf', 'mp4'];

        foreach ($tagSources as $source) {
            if (!isset($fileInfo['tags'][$source])) {
                continue;
            }

            $tags = $fileInfo['tags'][$source];

            $metadata['artist'] = $metadata['artist'] ?? $this->extractTagValue($tags, ['artist']);
            $metadata['album'] = $metadata['album'] ?? $this->cleanAlbumName($this->extractTagValue($tags, ['album']));
            $metadata['title'] = $metadata['title'] ?? $this->extractTagValue($tags, ['title']);
            $metadata['track_number'] = $metadata['track_number'] ?? $this->extractTrackNumber($tags, ['track_number', 'tracknumber', 'track', 'track_position', 'position']);
            $metadata['year'] = $metadata['year'] ?? $this->extractYear($tags, ['year', 'date', 'creation_date']);
            $metadata['genre'] = $metadata['genre'] ?? $this->extractTagValue($tags, ['genre']);
            $metadata['comment'] = $metadata['comment'] ?? $this->extractTagValue($tags, ['comment']);
            $metadata['composer'] = $metadata['composer'] ?? $this->extractTagValue($tags, ['composer']);
            $metadata['album_artist'] = $metadata['album_artist'] ?? $this->extractTagValue($tags, ['albumartist']);
            $metadata['performer'] = $metadata['performer'] ?? $this->extractTagValue($tags, ['performer']);
            $metadata['disc_number'] = $metadata['disc_number'] ?? $this->extractDiscNumber($tags, ['discnumber', 'disc']);
            $metadata['total_tracks'] = $metadata['total_tracks'] ?? $this->extractTotalTracks($tags, ['track']);
            $metadata['total_discs'] = $metadata['total_discs'] ?? $this->extractTotalDiscs($tags, ['disc']);
        }

        // Nettoyer les valeurs
        foreach ($metadata as $key => $value) {
            if (\is_string($value)) {
                $metadata[$key] = mb_trim($value);
            }
        }

        return $metadata;
    }

    /**
     * Extrait une valeur de tag depuis un tableau de tags.
     */
    private function extractTagValue(array $tags, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($tags[$key])) {
                $value = $tags[$key];
                if (\is_array($value)) {
                    return $value[0] ?? null;
                }

                return $value;
            }
        }

        return null;
    }

    /**
     * Nettoie le nom d'album en supprimant les indicateurs de qualité entre crochets.
     */
    private function cleanAlbumName(?string $albumName): ?string
    {
        if (!$albumName) {
            return null;
        }

        // Supprimer les indicateurs de qualité entre crochets comme [FLAC], [320], [Lossless], etc.
        $cleaned = preg_replace('/\s*\[[^\]]*\]\s*/', ' ', $albumName);

        // Nettoyer les espaces multiples et les espaces en début/fin
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        $cleaned = mb_trim($cleaned);

        return $cleaned ?: null;
    }

    /**
     * Extrait le numéro de piste depuis un tag.
     */
    private function extractTrackNumber(array $tags, array $keys): ?string
    {
        $value = $this->extractTagValue($tags, $keys);
        if (!$value) {
            return null;
        }

        // Handle vinyl record track numbers (A1, B1, etc.)
        if (preg_match('/^([A-Z])(\d+)/', $value, $matches)) {
            return $matches[1] . $matches[2];
        }

        // Handle standard track numbers
        if (is_numeric($value)) {
            return (string) $value;
        }

        // Handle track numbers with text (e.g., "Track 1", "1/12")
        if (preg_match('/(\d+)/', $value, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extrait l'année depuis un tag.
     */
    private function extractYear(array $tags, array $keys): ?int
    {
        $value = $this->extractTagValue($tags, $keys);
        if (!$value) {
            return null;
        }

        // Gérer différents formats de date
        if (preg_match('/^(\d{4})/', $value, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Extrait le numéro de disque depuis un tag.
     */
    private function extractDiscNumber(array $tags, array $keys): ?int
    {
        $value = $this->extractTagValue($tags, $keys);
        if (!$value) {
            return null;
        }

        // Gérer les formats comme "1/2" ou "01/02"
        if (preg_match('/^(\d+)(?:\/\d+)?$/', $value, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Extrait le nombre total de pistes depuis un tag.
     */
    private function extractTotalTracks(array $tags, array $keys): ?int
    {
        $value = $this->extractTagValue($tags, $keys);
        if (!$value) {
            return null;
        }

        // Gérer les formats comme "1/12" ou "01/12"
        if (preg_match('/^\d+\/(\d+)$/', $value, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Extrait le nombre total de disques depuis un tag.
     */
    private function extractTotalDiscs(array $tags, array $keys): ?int
    {
        $value = $this->extractTagValue($tags, $keys);
        if (!$value) {
            return null;
        }

        // Gérer les formats comme "1/2" ou "01/02"
        if (preg_match('/^\d+\/(\d+)$/', $value, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Formate le bitrate en format lisible.
     */
    private function formatBitrate(?int $bitrate): string
    {
        if (!$bitrate) {
            return 'Unknown';
        }

        if ($bitrate >= 1000000) {
            return round($bitrate / 1000000, 1) . ' Mbps';
        } elseif ($bitrate >= 1000) {
            return round($bitrate / 1000, 0) . ' kbps';
        }

        return $bitrate . ' bps';
    }

    /**
     * Formate la fréquence d'échantillonnage.
     */
    private function formatSampleRate(?int $sampleRate): string
    {
        if (!$sampleRate) {
            return 'Unknown';
        }

        if ($sampleRate >= 1000) {
            return round($sampleRate / 1000, 1) . ' kHz';
        }

        return $sampleRate . ' Hz';
    }

    /**
     * Formate les canaux audio.
     */
    private function formatChannels(?int $channels): string
    {
        if (!$channels) {
            return 'Unknown';
        }

        switch ($channels) {
            case 1:
                return 'Mono';
            case 2:
                return 'Stereo';
            case 6:
                return '5.1 Surround';
            case 8:
                return '7.1 Surround';
            default:
                return $channels . ' Channels';
        }
    }

    /**
     * Formate les bits par échantillon.
     */
    private function formatBitsPerSample(?int $bitsPerSample): string
    {
        if (!$bitsPerSample) {
            return 'Unknown';
        }

        return $bitsPerSample . ' bit';
    }

    /**
     * Construit une chaîne de qualité complète.
     */
    private function buildQualityString(
        string $format,
        string $channels,
        string $bitrate,
        string $sampleRate,
        string $bitsPerSample
    ): string {
        $parts = [];

        if ('UNKNOWN' !== $format) {
            $parts[] = $format;
        }

        if ('Unknown' !== $channels) {
            $parts[] = $channels;
        }

        if ('Unknown' !== $bitrate) {
            $parts[] = $bitrate;
        }

        if ('Unknown' !== $sampleRate) {
            $parts[] = $sampleRate;
        }

        if ('Unknown' !== $bitsPerSample) {
            $parts[] = $bitsPerSample;
        }

        return implode(' / ', $parts);
    }

    /**
     * Détermine la qualité audio basée sur les paramètres.
     */
    public function determineQualityLevel(array $analysis): string
    {
        if (isset($analysis['error']) && $analysis['error']) {
            return 'unknown';
        }

        $format = $analysis['format'] ?? '';
        $bitrate = $analysis['bitrate'] ?? 0;
        $sampleRate = $analysis['sample_rate'] ?? 0;
        $bitsPerSample = $analysis['bits_per_sample'] ?? 0;

        // Logique de détermination de la qualité
        if ('FLAC' === $format || 'WAV' === $format) {
            if ($sampleRate >= 96000 && $bitsPerSample >= 24) {
                return 'hi-res';
            } elseif ($sampleRate >= 44100 && $bitsPerSample >= 16) {
                return 'lossless';
            }
        } elseif ('MP3' === $format) {
            if ($bitrate >= 320000) {
                return 'high';
            } elseif ($bitrate >= 192000) {
                return 'medium';
            }

            return 'low';
        } elseif ('AAC' === $format || 'M4A' === $format) {
            if ($bitrate >= 256000) {
                return 'high';
            } elseif ($bitrate >= 128000) {
                return 'medium';
            }

            return 'low';
        }

        return 'unknown';
    }

    /**
     * Compare deux analyses de qualité.
     */
    public function compareQuality(array $analysis1, array $analysis2): array
    {
        $quality1 = $this->determineQualityLevel($analysis1);
        $quality2 = $this->determineQualityLevel($analysis2);

        $qualityLevels = [
            'unknown' => 0,
            'low' => 1,
            'medium' => 2,
            'high' => 3,
            'lossless' => 4,
            'hi-res' => 5,
        ];

        $level1 = $qualityLevels[$quality1] ?? 0;
        $level2 = $qualityLevels[$quality2] ?? 0;

        if ($level1 > $level2) {
            return ['winner' => 'first', 'difference' => $level1 - $level2];
        } elseif ($level2 > $level1) {
            return ['winner' => 'second', 'difference' => $level2 - $level1];
        }

        return ['winner' => 'equal', 'difference' => 0];
    }

    /**
     * Récupère les statistiques de qualité d'un dossier.
     */
    public function analyzeDirectoryQuality(string $directory): array
    {
        $stats = [
            'total_files' => 0,
            'formats' => [],
            'quality_levels' => [
                'unknown' => 0,
                'low' => 0,
                'medium' => 0,
                'high' => 0,
                'lossless' => 0,
                'hi-res' => 0,
            ],
            'average_bitrate' => 0,
            'total_bitrate' => 0,
        ];

        $files = glob($directory . '/*.{mp3,flac,wav,m4a,aac,ogg}', \GLOB_BRACE);
        if (false === $files) {
            return $stats;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                $analysis = $this->analyzeAudioFile($file);

                if (!isset($analysis['error']) || !$analysis['error']) {
                    ++$stats['total_files'];

                    $format = $analysis['format'] ?? 'Unknown';
                    $stats['formats'][$format] = ($stats['formats'][$format] ?? 0) + 1;

                    $qualityLevel = $this->determineQualityLevel($analysis);
                    ++$stats['quality_levels'][$qualityLevel];

                    if ($analysis['bitrate']) {
                        $stats['total_bitrate'] += $analysis['bitrate'];
                    }
                }
            }
        }

        if ($stats['total_files'] > 0) {
            $stats['average_bitrate'] = $stats['total_bitrate'] / $stats['total_files'];
        }

        return $stats;
    }
}
