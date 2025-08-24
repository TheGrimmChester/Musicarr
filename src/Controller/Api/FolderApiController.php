<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * API controller for folder operations.
 */
#[Route('/api/folders')]
class FolderApiController extends AbstractController
{
    /**
     * Browse folder contents.
     */
    #[Route('/browse', name: 'api_folders_browse', methods: ['GET'])]
    public function browse(Request $request): JsonResponse
    {
        try {
            $path = $request->query->get('path', '/');

            // Security check: ensure path is within allowed directories
            if (!$this->isPathAllowed($path)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Access denied to this directory',
                ], 403);
            }

            // Normalize path
            $path = $this->normalizePath($path);

            if (!is_dir($path)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Directory does not exist',
                ], 404);
            }

            $folders = [];
            $files = [];

            try {
                $items = scandir($path);

                foreach ($items as $item) {
                    if ('.' === $item || '..' === $item) {
                        continue;
                    }

                    $fullPath = $path . \DIRECTORY_SEPARATOR . $item;

                    if (is_dir($fullPath)) {
                        // Check if directory is readable
                        if (is_readable($fullPath)) {
                            $folders[] = [
                                'name' => $item,
                                'path' => $fullPath,
                                'selectable' => $this->isDirectorySelectable($fullPath),
                            ];
                        }
                    } elseif (is_file($fullPath)) {
                        // Only show common media file types
                        if ($this->isMediaFile($fullPath)) {
                            $files[] = [
                                'name' => $item,
                                'path' => $fullPath,
                                'size' => filesize($fullPath),
                                'type' => pathinfo($fullPath, \PATHINFO_EXTENSION),
                            ];
                        }
                    }
                }

                // Sort folders and files alphabetically
                usort($folders, function ($a, $b) {
                    return strcasecmp($a['name'], $b['name']);
                });
                usort($files, function ($a, $b) {
                    return strcasecmp($a['name'], $b['name']);
                });

                return $this->json([
                    'success' => true,
                    'folders' => $folders,
                    'files' => $files,
                    'currentPath' => $path,
                ]);
            } catch (Exception $e) {
                return $this->json([
                    'success' => false,
                    'error' => 'Error reading directory contents: ' . $e->getMessage(),
                ], 500);
            }
        } catch (Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Unexpected error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available root directories.
     */
    #[Route('/roots', name: 'api_folders_roots', methods: ['GET'])]
    public function getRoots(): JsonResponse
    {
        try {
            $roots = [];

            // Common system directories
            $commonRoots = [
                '/home' => 'Home',
                '/mnt' => 'Mount Points',
                '/media' => 'Media',
                '/opt' => 'Optional Applications',
            ];

            foreach ($commonRoots as $path => $label) {
                if (is_dir($path) && is_readable($path)) {
                    $roots[] = [
                        'path' => $path,
                        'label' => $label,
                        'selectable' => false,
                    ];
                }
            }

            // Add user's home directory
            $userHome = getenv('HOME') ?: getenv('USERPROFILE');
            if ($userHome && is_dir($userHome)) {
                $roots[] = [
                    'path' => $userHome,
                    'label' => 'User Home',
                    'selectable' => false,
                ];
            }

            return $this->json([
                'success' => true,
                'roots' => $roots,
            ]);
        } catch (Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Error getting root directories: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if a path is allowed for browsing.
     */
    private function isPathAllowed(string $path): bool
    {
        // Normalize path
        $path = $this->normalizePath($path);

        // For security, restrict to common safe directories
        $allowedPrefixes = [
            '/home',
            '/mnt',
            '/media',
            '/opt',
            '/usr/local',
            '/var/music',
            '/var/media',
        ];

        foreach ($allowedPrefixes as $prefix) {
            if (0 === mb_strpos($path, $prefix)) {
                return true;
            }
        }

        // Allow user's home directory
        $userHome = getenv('HOME') ?: getenv('USERPROFILE');
        if ($userHome && 0 === mb_strpos($path, $userHome)) {
            return true;
        }

        return false;
    }

    /**
     * Check if a directory is selectable (not system directories).
     */
    private function isDirectorySelectable(string $path): bool
    {
        // Don't allow selecting system directories
        $systemDirs = [
            '/proc',
            '/sys',
            '/dev',
            '/tmp',
            '/var/tmp',
            '/var/cache',
            '/var/log',
        ];

        foreach ($systemDirs as $systemDir) {
            if (0 === mb_strpos($path, $systemDir)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a file is a media file.
     */
    private function isMediaFile(string $path): bool
    {
        $extension = mb_strtolower(pathinfo($path, \PATHINFO_EXTENSION));

        $mediaExtensions = [
            'mp3', 'wav', 'flac', 'aac', 'ogg', 'wma',
            'mp4', 'avi', 'mkv', 'mov', 'wmv', 'flv',
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff',
        ];

        return \in_array($extension, $mediaExtensions, true);
    }

    /**
     * Normalize a file path.
     */
    private function normalizePath(string $path): string
    {
        // Remove any null bytes
        $path = str_replace("\0", '', $path);

        // Resolve any relative path components
        $path = realpath($path) ?: $path;

        // Ensure path is absolute (compatible with older PHP versions)
        if (!$this->isAbsolutePath($path)) {
            $path = getcwd() . \DIRECTORY_SEPARATOR . $path;
        }

        return $path;
    }

    /**
     * Check if a path is absolute (compatible with older PHP versions).
     */
    private function isAbsolutePath(string $path): bool
    {
        // On Unix-like systems, absolute paths start with /
        if (\DIRECTORY_SEPARATOR === '/') {
            return '/' === $path[0];
        }

        // On Windows, absolute paths start with drive letter or \\
        if (\DIRECTORY_SEPARATOR === '\\') {
            return (mb_strlen($path) > 1 && ':' === $path[1])
                   || (mb_strlen($path) > 1 && '\\' === $path[0] && '\\' === $path[1]);
        }

        return false;
    }
}
