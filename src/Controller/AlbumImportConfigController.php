<?php

declare(strict_types=1);

namespace App\Controller;

use App\Configuration\Config\ConfigurationFactory;
use App\Configuration\Domain\ConfigurationDomainRegistry;
use App\Entity\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/album-import-config')]
class AlbumImportConfigController extends AbstractController
{
    public function __construct(
        private ConfigurationDomainRegistry $domainRegistry,
        private ConfigurationFactory $configurationFactory,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    #[Route('/', name: 'album_import_config_index', methods: ['GET'])]
    public function index(): Response
    {
        // Ensure the configuration domain registry is initialized
        if (!$this->domainRegistry->isInitialized()) {
            $this->domainRegistry->initialize();
        }

        // Get the album import domain
        $albumImportDomain = $this->domainRegistry->getDomain('album_import.');
        if ($albumImportDomain) {
            $albumImportDomain->initializeDefaults();
        }

        // Get configuration using the domain and format it for the template
        $domainConfig = $albumImportDomain ? $albumImportDomain->getAllConfig() : [];

        // Format config for template (add domain prefix to keys)
        $config = [];
        foreach ($domainConfig as $key => $value) {
            $config['album_import.' . $key] = $value;
        }

        return $this->render('album_import_config/index.html.twig', [
            'config' => $config,
        ]);
    }

    #[Route('/get', name: 'album_import_config_get', methods: ['GET'])]
    public function get(): JsonResponse
    {
        try {
            $albumImportDomain = $this->domainRegistry->getDomain('album_import.');
            $config = $albumImportDomain ? $albumImportDomain->getAllConfig() : [];

            return $this->json([
                'success' => true,
                'data' => $config,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error getting album import configuration: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/save', name: 'album_import_config_save', methods: ['POST'])]
    public function save(Request $request): JsonResponse
    {
        try {
            // Handle both form data and JSON data
            $data = [];

            $contentType = $request->headers->get('Content-Type');
            $requestContent = $request->getContent();

            // Auto-detect JSON data
            $isJsonRequest = 'application/json' === $contentType
                           || (empty($request->request->all()) && !empty($requestContent) && $this->isValidJson($requestContent));

            if ($isJsonRequest) {
                $data = json_decode($requestContent, true);
                if (null === $data && \JSON_ERROR_NONE !== json_last_error()) {
                    return $this->json([
                        'success' => false,
                        'error' => 'Invalid JSON data',
                    ], 400);
                }
                // If $data is empty array, that's valid - just no config to update
                $data = $data ?? [];

                // Convert JSON data to the expected format
                foreach ($data as $key => $value) {
                    // For non-numeric strings, leave as-is
                    if (is_numeric($value)) {
                        // Check if it's a float (contains decimal point)
                        if (\is_string($value) && false !== mb_strpos($value, '.')) {
                            $data[$key] = (float) $value;
                        } else {
                            $data[$key] = (int) $value;
                        }
                    }
                }
            } else {
                // Handle form data
                $formData = $request->request->all();
                if (empty($formData) || !isset($formData['album_import_config'])) {
                    return $this->json([
                        'success' => false,
                        'error' => 'No form data received',
                    ], 400);
                }

                $formData = $formData['album_import_config'];

                // Convert form data to the expected format
                foreach ($formData as $key => $value) {
                    if (is_numeric($value)) {
                        // Check if it's a float (contains decimal point)
                        if (false !== mb_strpos($value, '.')) {
                            $data[$key] = (float) $value;
                        } else {
                            $data[$key] = (int) $value;
                        }
                    } else {
                        $data[$key] = $value;
                    }
                }
            }

            // Get the album import domain
            $albumImportDomain = $this->domainRegistry->getDomain('album_import.');
            if (!$albumImportDomain) {
                return $this->json([
                    'success' => false,
                    'error' => 'Configuration domain not found',
                ], 400);
            }

            // Basic validation of the data
            $validationErrors = $this->validateConfigurationData($data);
            if (!empty($validationErrors)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid configuration data: ' . implode(', ', $validationErrors),
                ], 400);
            }

            // Save the configuration using the domain
            $this->saveConfigurationToDatabase($data);

            $this->logger->info('Album import configuration updated', [
                'updated_settings' => array_keys($data),
            ]);

            return $this->json(['success' => true]);
        } catch (Exception $e) {
            $this->logger->error('Error saving album import configuration: ' . $e->getMessage(), [
                'exception' => $e,
                'request_data' => $data ?? [],
            ]);

            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/reset', name: 'album_import_config_reset', methods: ['POST'])]
    public function reset(): JsonResponse
    {
        try {
            $albumImportDomain = $this->domainRegistry->getDomain('album_import.');
            if ($albumImportDomain) {
                $albumImportDomain->initializeDefaults();
            }

            $this->logger->info('Album import configuration reset to defaults');

            return $this->json(['success' => true]);
        } catch (Exception $e) {
            $this->logger->error('Error resetting album import configuration: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/delete', name: 'album_import_config_delete', methods: ['DELETE'])]
    public function delete(): JsonResponse
    {
        try {
            $albumImportDomain = $this->domainRegistry->getDomain('album_import.');
            if ($albumImportDomain) {
                $albumImportDomain->clearAllConfig();
            }

            $this->logger->info('Album import configuration cleared');

            return $this->json(['success' => true]);
        } catch (Exception $e) {
            $this->logger->error('Error deleting album import configuration: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save configuration to database.
     */
    private function saveConfigurationToDatabase(array $config): void
    {
        foreach ($config as $key => $value) {
            $fullKey = 'album_import.' . $key;

            // Find existing configuration or create new one
            $existingConfig = $this->entityManager->getRepository(Configuration::class)
                ->findOneBy(['key' => $fullKey]);

            if (null === $existingConfig) {
                $existingConfig = new Configuration();
                $existingConfig->setKey($fullKey);
            }

            $existingConfig->setParsedValue($value);
            $this->entityManager->persist($existingConfig);
        }

        $this->entityManager->flush();
    }

    /**
     * Validate configuration data.
     */
    private function validateConfigurationData(array $data): array
    {
        $errors = [];

        foreach ($data as $key => $value) {
            switch ($key) {
                case 'primary_types':
                case 'secondary_types':
                case 'release_statuses':
                    if (!\is_array($value)) {
                        $errors[] = "Field '$key' must be an array";
                    }

                    break;
            }
        }

        return $errors;
    }

    /**
     * Check if a string is valid JSON.
     */
    private function isValidJson(string $string): bool
    {
        json_decode($string);

        return \JSON_ERROR_NONE === json_last_error();
    }
}
