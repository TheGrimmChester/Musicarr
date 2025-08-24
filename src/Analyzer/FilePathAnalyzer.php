<?php

declare(strict_types=1);

namespace App\Analyzer;

class FilePathAnalyzer
{
    /**
     * Extract information from file path including artist, album, year, and track number.
     */
    public function extractPathInformation(string $filePath): array
    {
        $pathInfo = $this->initializePathInfo();
        $pathParts = $this->parseDirectoryPath($filePath);
        $relevantParts = $this->filterMusicDirectoryParts($pathParts);

        $pathInfo = $this->extractDirectoryInformation($relevantParts, $pathInfo);
        $pathInfo['track_number'] = $this->extractTrackNumberFromFilename($filePath);
        $pathInfo['directory_structure'] = $pathParts;

        return $pathInfo;
    }

    /**
     * Initialize path information structure.
     */
    private function initializePathInfo(): array
    {
        return [
            'artist' => null,
            'album' => null,
            'year' => null,
            'track_number' => null,
            'directory_structure' => [],
        ];
    }

    /**
     * Parse directory path into parts.
     */
    private function parseDirectoryPath(string $filePath): array
    {
        $dirPath = \dirname($filePath);

        return explode('/', mb_trim($dirPath, '/'));
    }

    /**
     * Filter path parts to focus on music library structure.
     */
    private function filterMusicDirectoryParts(array $pathParts): array
    {
        $musicParts = [];
        $foundMusic = false;

        foreach ($pathParts as $part) {
            if ('Music' === $part || 'music' === $part) {
                $foundMusic = true;

                continue;
            }
            if ($foundMusic) {
                $musicParts[] = $part;
            }
        }

        // If we have music parts, use them; otherwise use all parts
        return !empty($musicParts) ? $musicParts : $pathParts;
    }

    /**
     * Extract information from directory parts.
     */
    private function extractDirectoryInformation(array $relevantParts, array $pathInfo): array
    {
        foreach ($relevantParts as $part) {
            $pathInfo = $this->processDirectoryPart($part, $pathInfo);
        }

        return $pathInfo;
    }

    /**
     * Process a single directory part to extract information.
     */
    private function processDirectoryPart(string $part, array $pathInfo): array
    {
        // Look for year pattern (4 digits) - year at start
        if (preg_match('/^(\d{4})\s*(.+)$/', $part, $matches)) {
            $pathInfo['year'] = (int) $matches[1];
            $pathInfo['album'] = mb_trim($matches[2]);

            return $pathInfo;
        }

        // Look for year pattern (4 digits) - year at end
        if (preg_match('/^(.+)\s+(\d{4})$/', $part, $matches)) {
            $pathInfo['album'] = mb_trim($matches[1]);
            $pathInfo['year'] = (int) $matches[2];

            return $pathInfo;
        }

        // Assume it's artist or album name
        if (!$pathInfo['artist']) {
            $pathInfo['artist'] = $part;

            return $pathInfo;
        }

        if (!$pathInfo['album']) {
            $pathInfo['album'] = $part;
        }

        return $pathInfo;
    }

    /**
     * Extract track number from filename.
     */
    private function extractTrackNumberFromFilename(string $filePath): ?string
    {
        $filename = basename($filePath);

        // Look for vinyl record track numbers (A1, B1, C1, etc.)
        if (preg_match('/^([A-Z])(\d+)/', $filename, $matches)) {
            return $matches[1] . $matches[2];
        }

        // Look for standard track numbers (01, 1, etc.)
        if (preg_match('/^(\d+)/', $filename, $matches)) {
            return $matches[1];
        }

        // Look for track numbers with separators (01., 1-, etc.)
        if (preg_match('/(\d+)[\.\-\s]/', $filename, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract artist name from file path.
     */
    public function extractArtistFromPath(string $filePath): ?string
    {
        $pathInfo = $this->extractPathInformation($filePath);

        return $pathInfo['artist'];
    }

    /**
     * Extract album name from file path.
     */
    public function extractAlbumFromPath(string $filePath): ?string
    {
        $pathInfo = $this->extractPathInformation($filePath);

        return $pathInfo['album'];
    }

    /**
     * Extract year from file path.
     */
    public function extractYearFromPath(string $filePath): ?int
    {
        $pathInfo = $this->extractPathInformation($filePath);

        return $pathInfo['year'];
    }

    /**
     * Extract track number from file path.
     */
    public function extractTrackNumberFromPath(string $filePath): ?string
    {
        return $this->extractTrackNumberFromFilename($filePath);
    }

    /**
     * Check if file path contains year information.
     */
    public function hasYearInPath(string $filePath): bool
    {
        return null !== $this->extractYearFromPath($filePath);
    }

    /**
     * Get the directory structure from file path.
     */
    public function getDirectoryStructure(string $filePath): array
    {
        $pathInfo = $this->extractPathInformation($filePath);

        return $pathInfo['directory_structure'];
    }
}
