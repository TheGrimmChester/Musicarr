<?php

declare(strict_types=1);

namespace App\Client;

use Exception;
use Psr\Log\LoggerInterface;

class MusicBrainzPaginationHelper
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /**
     * Fetch all results from a paginated MusicBrainz API endpoint.
     *
     * @param callable $fetchPage  Function that fetches a single page (should accept offset and limit)
     * @param int      $pageSize   Maximum results per page (MusicBrainz default is 25, max is 100)
     * @param int      $maxResults Maximum total results to fetch (0 = unlimited)
     *
     * @return array All results combined
     */
    public function fetchAllResults(callable $fetchPage, int $pageSize = 100, int $maxResults = 0): array
    {
        $allResults = [];
        $offset = 0;
        $totalCount = null;
        $pageCount = 0;
        $maxPages = $maxResults > 0 ? ceil($maxResults / $pageSize) : 0;

        while (true) {
            ++$pageCount;

            if ($maxPages > 0 && $pageCount > $maxPages) {
                break;
            }

            try {
                $pageData = $fetchPage($offset, $pageSize);

                if (!$pageData || !\is_array($pageData)) {
                    $this->logger->warning("Invalid page data received for page {$pageCount}");

                    break;
                }

                // Extract results and pagination info
                $results = $this->extractResultsFromPage($pageData);
                $pageInfo = $this->extractPaginationInfo($pageData);

                if (null === $pageInfo) {
                    $this->logger->warning("No pagination info found in page {$pageCount}, treating as single page");
                    $allResults = array_merge($allResults, $results);

                    break;
                }

                $allResults = array_merge($allResults, $results);

                // Update total count if not set
                if (null === $totalCount) {
                    $totalCount = $pageInfo['total'];
                }

                // Check if we've reached the end
                if (\count($results) < $pageSize || $offset + \count($results) >= $totalCount) {
                    break;
                }

                // Check if we've reached max results
                if ($maxResults > 0 && \count($allResults) >= $maxResults) {
                    $allResults = \array_slice($allResults, 0, $maxResults);

                    break;
                }

                $offset += $pageSize;
            } catch (Exception $e) {
                $this->logger->error("Error fetching page {$pageCount}: " . $e->getMessage());

                break;
            }
        }

        return $allResults;
    }

    /**
     * Extract results array from a page response.
     */
    private function extractResultsFromPage(array $pageData): array
    {
        // Handle different response structures
        if (isset($pageData['artists'])) {
            return $pageData['artists'];
        }

        if (isset($pageData['releases'])) {
            return $pageData['releases'];
        }

        if (isset($pageData['release-groups'])) {
            return $pageData['release-groups'];
        }

        if (isset($pageData['recordings'])) {
            return $pageData['recordings'];
        }

        if (isset($pageData['works'])) {
            return $pageData['works'];
        }

        if (isset($pageData['labels'])) {
            return $pageData['labels'];
        }

        // If no known structure, return empty array
        $this->logger->warning('Unknown response structure: ' . implode(', ', array_keys($pageData)));

        return [];
    }

    /**
     * Extract pagination information from a page response.
     */
    private function extractPaginationInfo(array $pageData): ?array
    {
        // Handle different pagination structures
        if (isset($pageData['count']) && isset($pageData['offset'])) {
            return [
                'total' => $pageData['count'],
                'offset' => $pageData['offset'],
            ];
        }

        if (isset($pageData['release-count']) && isset($pageData['release-offset'])) {
            return [
                'total' => $pageData['release-count'],
                'offset' => $pageData['release-offset'],
            ];
        }

        if (isset($pageData['release-group-count']) && isset($pageData['release-group-offset'])) {
            return [
                'total' => $pageData['release-group-count'],
                'offset' => $pageData['release-group-offset'],
            ];
        }

        if (isset($pageData['recording-count']) && isset($pageData['recording-offset'])) {
            return [
                'total' => $pageData['recording-count'],
                'offset' => $pageData['recording-offset'],
            ];
        }

        if (isset($pageData['work-count']) && isset($pageData['work-offset'])) {
            return [
                'total' => $pageData['work-count'],
                'offset' => $pageData['work-offset'],
            ];
        }

        if (isset($pageData['label-count']) && isset($pageData['label-offset'])) {
            return [
                'total' => $pageData['label-count'],
                'offset' => $pageData['label-offset'],
            ];
        }

        return null;
    }
}
