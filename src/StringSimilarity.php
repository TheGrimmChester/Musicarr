<?php

declare(strict_types=1);

namespace App;

class StringSimilarity
{
    /**
     * Calculate similarity between two strings using Levenshtein distance.
     */
    public function calculateSimilarity(string $str1, string $str2): float
    {
        $str1 = $this->normalizeString($str1);
        $str2 = $this->normalizeString($str2);

        if ($str1 === $str2) {
            return 1.0;
        }

        $maxLength = max(mb_strlen($str1), mb_strlen($str2));
        if (0 === $maxLength) {
            return 0.0;
        }

        $distance = levenshtein($str1, $str2);

        return 1 - ($distance / $maxLength);
    }

    /**
     * Calculate similarity with additional normalization.
     */
    public function calculateNormalizedSimilarity(string $str1, string $str2): float
    {
        $normalized1 = $this->normalizeString($str1);
        $normalized2 = $this->normalizeString($str2);

        return $this->calculateSimilarity($normalized1, $normalized2);
    }

    /**
     * Normalize string for better comparison.
     */
    private function normalizeString(string $str): string
    {
        // Convert to lowercase and trim
        $str = mb_strtolower(mb_trim($str));

        // Normalize apostrophes
        $str = str_replace(['’', '‘', '“', '”'], "'", $str);

        // Transliterate accented characters to their ASCII equivalents
        if (\function_exists('transliterator_transliterate')) {
            $str = transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $str);
        } else {
            // Fallback for systems without transliterator
            $str = $this->basicTransliterate($str);
        }

        // Remove special characters and extra spaces
        $str = preg_replace('/[^\w\s]/', ' ', $str);
        $str = preg_replace('/\s+/', ' ', $str);

        return mb_trim($str);
    }

    /**
     * Basic transliteration fallback for systems without transliterator extension.
     */
    private function basicTransliterate(string $str): string
    {
        $accented = [
            'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï',
            'ð', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'þ', 'ÿ',
            'À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï',
            'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'Þ', 'Ÿ',
        ];

        $ascii = [
            'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i',
            'o', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'th', 'y',
            'A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I',
            'O', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 'TH', 'Y',
        ];

        return str_replace($accented, $ascii, $str);
    }

    /**
     * Normalize apostrophes by replacing straight quotes with curly quotes.
     */
    public function normalizeApostrophes(?string $str): ?string
    {
        if (null === $str) {
            return null;
        }

        // Replace straight apostrophes with curly apostrophes
        return str_replace('’', "'", $str);
    }

    /**
     * Check if two strings are similar above a threshold.
     */
    public function isSimilar(string $str1, string $str2, float $threshold = 0.8): bool
    {
        return $this->calculateSimilarity($str1, $str2) >= $threshold;
    }

    /**
     * Find the best match from a list of candidates.
     */
    public function findBestMatch(string $target, array $candidates): ?array
    {
        $bestMatch = null;
        $bestScore = 0.0;

        foreach ($candidates as $candidate) {
            $score = $this->calculateSimilarity($target, $candidate);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $candidate;
            }
        }

        return $bestMatch ? ['match' => $bestMatch, 'score' => $bestScore] : null;
    }
}
