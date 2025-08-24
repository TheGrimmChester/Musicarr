<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Configuration\ConfigurationService;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/configuration')]
class ConfigurationApiController extends AbstractController
{
    public function __construct(
        private ConfigurationService $configurationService
    ) {
    }

    #[Route('/{key}', name: 'api_configuration_get', methods: ['GET'])]
    public function get(string $key, Request $request): JsonResponse
    {
        $defaultValue = $request->query->get('default');
        $value = $this->configurationService->get($key, $defaultValue);

        return $this->json([
            'key' => $key,
            'value' => $value,
            'success' => true,
        ]);
    }

    #[Route('', name: 'api_configuration_set', methods: ['POST'])]
    public function set(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['key']) || !isset($data['value'])) {
                return $this->json([
                    'success' => false,
                    'error' => 'Missing required fields: key and value',
                ], Response::HTTP_BAD_REQUEST);
            }

            $this->configurationService->set(
                $data['key'],
                $data['value'],
                $data['description'] ?? null
            );

            return $this->json([
                'success' => true,
                'message' => 'Configuration updated successfully',
            ]);
        } catch (Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to update configuration: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{key}', name: 'api_configuration_delete', methods: ['DELETE'])]
    public function delete(string $key): JsonResponse
    {
        $result = $this->configurationService->delete($key);

        return $this->json([
            'success' => $result,
            'message' => $result ? 'Configuration deleted successfully' : 'Configuration not found',
        ]);
    }

    #[Route('', name: 'api_configuration_get_all', methods: ['GET'])]
    public function getAll(): JsonResponse
    {
        $configurations = $this->configurationService->getAll();

        return $this->json([
            'configurations' => $configurations,
            'success' => true,
        ]);
    }

    #[Route('/prefix/{prefix}', name: 'api_configuration_get_by_prefix', methods: ['GET'])]
    public function getByPrefix(string $prefix): JsonResponse
    {
        $configurations = $this->configurationService->getByPrefix($prefix);

        return $this->json([
            'prefix' => $prefix,
            'configurations' => $configurations,
            'success' => true,
        ]);
    }

    #[Route('/bulk', name: 'api_configuration_bulk_set', methods: ['POST'])]
    public function bulkSet(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['configurations']) || !\is_array($data['configurations'])) {
                return $this->json([
                    'success' => false,
                    'error' => 'Missing or invalid configurations array',
                ], Response::HTTP_BAD_REQUEST);
            }

            foreach ($data['configurations'] as $config) {
                if (!isset($config['key']) || !isset($config['value'])) {
                    return $this->json([
                        'success' => false,
                        'error' => 'Each configuration must have key and value',
                    ], Response::HTTP_BAD_REQUEST);
                }

                $this->configurationService->set(
                    $config['key'],
                    $config['value'],
                    $config['description'] ?? null
                );
            }

            return $this->json([
                'success' => true,
                'message' => 'Bulk configuration update completed successfully',
            ]);
        } catch (Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to update configurations: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
