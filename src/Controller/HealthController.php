<?php

declare(strict_types=1);

namespace App\Controller;

use App\Statistic\StatisticsService;
use DateTime;
use Doctrine\DBAL\Connection;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HealthController extends AbstractController
{
    public function __construct(
        private Connection $connection,
        private StatisticsService $statisticsService
    ) {
    }

    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function healthCheck(): JsonResponse
    {
        $status = 'healthy';
        $checks = [];

        // Database connectivity check
        try {
            $this->connection->executeQuery('SELECT 1');
            $checks['database'] = [
                'status' => 'healthy',
                'message' => 'Database connection successful',
            ];
        } catch (Exception $e) {
            $status = 'unhealthy';
            $checks['database'] = [
                'status' => 'unhealthy',
                'message' => 'Database connection failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }

        // Database file permissions check (for SQLite)
        try {
            $dbPath = $this->getDatabasePath();
            if ($dbPath && file_exists($dbPath)) {
                $isReadable = is_readable($dbPath);
                $isWritable = is_writable($dbPath);

                $checks['database_file'] = [
                    'status' => ($isReadable && $isWritable) ? 'healthy' : 'unhealthy',
                    'path' => $dbPath,
                    'readable' => $isReadable,
                    'writable' => $isWritable,
                    'permissions' => mb_substr(\sprintf('%o', fileperms($dbPath)), -4),
                    'owner' => (function () use ($dbPath) {
                        $owner = fileowner($dbPath);
                        if (false === $owner) {
                            return 'unknown';
                        }
                        $userInfo = posix_getpwuid($owner);

                        return $userInfo ? $userInfo['name'] : 'unknown';
                    })(),
                    'group' => (function () use ($dbPath) {
                        $group = filegroup($dbPath);
                        if (false === $group) {
                            return 'unknown';
                        }
                        $groupInfo = posix_getgrgid($group);

                        return $groupInfo ? $groupInfo['name'] : 'unknown';
                    })(),
                ];

                if (!$isReadable || !$isWritable) {
                    $status = 'unhealthy';
                }
            } else {
                $checks['database_file'] = [
                    'status' => 'unhealthy',
                    'message' => 'Database file not found',
                    'path' => $dbPath,
                ];
                $status = 'unhealthy';
            }
        } catch (Exception $e) {
            $checks['database_file'] = [
                'status' => 'unhealthy',
                'message' => 'Error checking database file: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
            $status = 'unhealthy';
        }

        // Entity count check using statistics service
        try {
            $summary = $this->statisticsService->getStatisticsSummary();

            $checks['entities'] = [
                'status' => 'healthy',
                'counts' => [
                    'artists' => $summary['artists'],
                    'albums' => $summary['albums'],
                    'singles' => $summary['singles'],
                    'tracks' => $summary['tracks'],
                    'libraries' => $summary['libraries'],
                ],
                'completion_rates' => [
                    'albums' => $summary['album_completion_rate'],
                    'singles' => $summary['single_completion_rate'],
                    'tracks' => $summary['track_completion_rate'],
                ],
            ];
        } catch (Exception $e) {
            $checks['entities'] = [
                'status' => 'unhealthy',
                'message' => 'Error getting entity statistics: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
            $status = 'unhealthy';
        }

        $response = [
            'status' => $status,
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            'checks' => $checks,
        ];

        $httpStatus = 'healthy' === $status ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;

        return new JsonResponse($response, $httpStatus);
    }

    #[Route('/health/database', name: 'database_health_check', methods: ['GET'])]
    public function databaseHealthCheck(): JsonResponse
    {
        try {
            // Test basic connection
            $this->connection->executeQuery('SELECT 1');

            // Test a simple query
            $result = $this->connection->executeQuery('SELECT COUNT(*) FROM artist')->fetchOne();

            return new JsonResponse([
                'status' => 'healthy',
                'message' => 'Database connection and query successful',
                'artist_count' => $result,
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return new JsonResponse([
                'status' => 'unhealthy',
                'message' => 'Database connection failed',
                'error' => $e->getMessage(),
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    private function getDatabasePath(): ?string
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        if (!\is_string($projectDir)) {
            return null;
        }

        $databaseUrl = $projectDir . '/.env';
        if (!file_exists($databaseUrl)) {
            return null;
        }

        $envContent = file_get_contents($databaseUrl);
        if (false === $envContent) {
            return null;
        }

        if (preg_match('/DATABASE_URL=sqlite:\/\/(.+)/', $envContent, $matches)) {
            $path = mb_trim($matches[1]);
            if (str_starts_with($path, '/')) {
                return $path;
            }

            return $projectDir . '/' . $path;
        }

        return null;
    }
}
