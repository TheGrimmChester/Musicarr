<?php

declare(strict_types=1);

namespace App\File;

class FileSanitizer
{
    /**
     * Sanitize a filename for filesystem compatibility.
     */
    public function sanitizeFileName(string $fileName, string $fallbackPattern = ''): string
    {
        $sanitized = $this->removeInvalidCharacters($fileName);
        $sanitized = $this->normalizeWhitespace($sanitized);
        $sanitized = $this->validateSanitizedFileName($sanitized);

        return $sanitized ?: $fallbackPattern;
    }

    /**
     * Sanitize a path for filesystem compatibility.
     */
    public function sanitizePath(string $path): string
    {
        // Replace problematic characters
        $path = preg_replace('/[<>:"\/\\|?*]/', '_', $path);
        if (null === $path) {
            $path = '';
        }

        // Remove leading and trailing spaces
        $path = mb_trim($path);

        // Limit length
        if (mb_strlen($path) > 255) {
            $path = mb_substr($path, 0, 255);
        }

        return $path;
    }

    /**
     * Remove invalid filesystem characters from filename.
     */
    private function removeInvalidCharacters(string $fileName): string
    {
        // Clean the complete path while preserving directory separators
        $result = preg_replace('/[<>:"\\|?*]/', '_', $fileName); // Don't replace /

        return $result ?? $fileName;
    }

    /**
     * Normalize whitespace in filename.
     */
    private function normalizeWhitespace(string $fileName): string
    {
        $result = preg_replace('/\s+/', ' ', $fileName);

        return $result ?? $fileName;
    }

    /**
     * Validate sanitized filename is not empty.
     */
    private function validateSanitizedFileName(string $fileName): string
    {
        return mb_trim($fileName);
    }
}
