<?php

declare(strict_types=1);

namespace App\Controller;

use App\Configuration\Domain\ConfigurationDomainRegistry;
use App\Entity\Configuration;
use App\Statistic\AssociationStatistics;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/association-config')]
class AssociationConfigController extends AbstractController
{
    public function __construct(
        private ConfigurationDomainRegistry $domainRegistry,
        private AssociationStatistics $statisticsService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    #[Route('/', name: 'association_config_index', methods: ['GET'])]
    public function index(): Response
    {
        // Get the association domain from the registry
        $associationDomain = $this->domainRegistry->getDomain('association.');

        // Initialize association configuration with defaults
        if ($associationDomain) {
            $associationDomain->initializeDefaults();
        }

        // Get configuration using the new domain system
        $config = $associationDomain ? $associationDomain->getAllConfig() : [];
        $statistics = $this->statisticsService->getAssociationStatistics();

        return $this->render('association/config.html.twig', [
            'config' => $config,
            'statistics' => $statistics,
        ]);
    }

    #[Route('/save', name: 'association_config_save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        try {
            // Handle both JSON and form data
            $content = $request->getContent();
            if ('json' === $request->getContentTypeFormat() || (!empty($content) && null !== json_decode($content))) {
                $data = json_decode($content, true);
                // For JSON data, wrap it in association_config if it's not already wrapped
                if (\is_array($data) && !isset($data['association_config'])) {
                    $data = ['association_config' => $data];
                }
            } else {
                $data = $request->request->all();
            }

            // Check for invalid data - different handling for JSON vs form data
            if (null === $data || false === $data) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid data',
                ], 400);
            }

            // For form submissions, empty data is an error
            if ('json' !== $request->getContentTypeFormat() && empty($data)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid data',
                ], 400);
            }

            // Get the association domain from the registry
            $associationDomain = $this->domainRegistry->getDomain('association.');

            if (!$associationDomain) {
                throw new Exception('Association domain not found');
            }

            // Save each configuration value
            $booleanFields = ['exact_artist_match', 'exact_album_match', 'exact_duration_match', 'exact_year_match', 'exact_title_match', 'auto_association'];

            // Initialize the config array to save
            $configToSave = [];

            // Validate the configuration data
            $associationConfig = \is_array($data) ? ($data['association_config'] ?? []) : [];
            $validationErrors = $this->validateConfigurationData($associationConfig);
            if (!empty($validationErrors)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid configuration data: ' . implode(', ', $validationErrors),
                ], 400);
            }

            // Process the submitted form data
            foreach ($associationConfig as $key => $value) {
                // Convert checkbox values
                if (\is_string($value) && 'on' === $value) {
                    $value = true;
                } elseif (\is_string($value) && 'off' === $value) {
                    $value = false;
                }

                // Store the value to save to database later
                $configToSave[$key] = $value;
            }

            // For form submissions (not JSON), set unsubmitted boolean fields to false
            if ('json' !== $request->getContentTypeFormat()) {
                // Only set boolean fields to false if they weren't submitted in a form
                foreach ($booleanFields as $field) {
                    if (!isset($configToSave[$field])) {
                        $configToSave[$field] = false;
                    }
                }
            }

            // Save all configurations to database
            $this->saveConfigurationToDatabase($configToSave);

            return $this->json(['success' => true]);
        } catch (Exception $e) {
            $this->logger->error('Error saving association configuration: ' . $e->getMessage());

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
            $fullKey = 'association.' . $key;

            // Find existing configuration or create new one
            $existingConfig = $this->entityManager->getRepository(Configuration::class)
                ->findOneBy(['key' => $fullKey]);

            if (null !== $existingConfig) {
                // Update existing configuration
                $existingConfig->setParsedValue($value);
                $this->entityManager->persist($existingConfig);
            } else {
                // Create new configuration
                $newConfig = new Configuration();
                $newConfig->setKey($fullKey);
                $newConfig->setParsedValue($value);
                $newConfig->setDescription('Association configuration');

                $this->entityManager->persist($newConfig);
            }
        }

        $this->entityManager->flush();
    }

    #[Route('/test-threshold', name: 'association_config_test_threshold', methods: ['POST'])]
    public function testThreshold(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!\is_array($data)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid JSON data',
                ], 400);
            }

            $minScore = (float) ($data['min_score'] ?? 85.0);

            // Validate min_score is between 0 and 100
            if ($minScore < 0 || $minScore > 100) {
                return $this->json([
                    'success' => false,
                    'error' => 'Min score must be between 0 and 100',
                ], 400);
            }

            // This could be expanded to show how many tracks would be affected
            // by the new threshold setting

            return $this->json([
                'success' => true,
                'min_score' => $minScore,
                'message' => "Min score set to {$minScore}. Tracks with scores below this value will not be automatically associated.",
            ]);
        } catch (Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Error testing threshold',
            ], 500);
        }
    }

    #[Route('/reset', name: 'association_config_reset', methods: ['POST'])]
    public function reset(): JsonResponse
    {
        try {
            // Reset to default values using the entity manager
            $defaultConfigs = [
                'auto_association' => true,
                'min_score' => 85.0,
                'exact_artist_match' => false,
                'exact_album_match' => false,
                'exact_duration_match' => false,
                'exact_year_match' => false,
                'exact_title_match' => false,
            ];

            foreach ($defaultConfigs as $key => $value) {
                $fullKey = 'association.' . $key;

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

            $this->logger->info('Association configuration reset to defaults');

            return $this->json([
                'success' => true,
                'message' => 'Configuration reset successfully',
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error resetting association configuration: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'Error resetting configuration',
            ], 500);
        }
    }

    #[Route('/statistics', name: 'association_config_statistics', methods: ['GET'])]
    public function getStatistics(): JsonResponse
    {
        try {
            $statistics = $this->statisticsService->getAssociationStatistics();

            return $this->json([
                'success' => true,
                'data' => $statistics,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error fetching association statistics: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'Error fetching statistics',
            ], 500);
        }
    }

    /**
     * Validate configuration data.
     */
    private function validateConfigurationData(array $data): array
    {
        $errors = [];

        foreach ($data as $key => $value) {
            switch ($key) {
                case 'exact_artist_match':
                case 'exact_album_match':
                case 'exact_duration_match':
                case 'exact_year_match':
                case 'exact_title_match':
                case 'auto_association':
                    if (!\is_bool($value) && !\in_array($value, ['on', 'off', 'true', 'false', '1', '0', 1, 0], true)) {
                        $errors[] = "Field '$key' must be a boolean value";
                    }

                    break;

                case 'min_score':
                    $numValue = is_numeric($value) ? (float) $value : null;
                    if (null === $numValue || $numValue < 0 || $numValue > 100) {
                        $errors[] = "Field '$key' must be a number between 0 and 100";
                    }

                    break;

                case 'artist_similarity_threshold':
                case 'album_similarity_threshold':
                case 'title_similarity_threshold':
                    $numValue = is_numeric($value) ? (float) $value : null;
                    if (null === $numValue || $numValue < 0 || $numValue > 1) {
                        $errors[] = "Field '$key' must be a number between 0 and 1";
                    }

                    break;

                case 'max_duration_difference':
                case 'max_year_difference':
                    $numValue = is_numeric($value) ? (int) $value : null;
                    if (null === $numValue || $numValue < 0) {
                        $errors[] = "Field '$key' must be a non-negative number";
                    }

                    break;
            }
        }

        return $errors;
    }
}
