<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Configuration\ConfigurationService;
use App\Configuration\Domain\ConfigurationDomainRegistry;
use App\Entity\Configuration;
use App\Repository\ConfigurationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use DOMElement;
use Exception;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AbstractFunctionalTestCase extends WebTestCase
{
    protected $client;
    protected EntityManagerInterface $entityManager;
    protected ConfigurationService $configurationService;
    protected ConfigurationDomainRegistry $domainRegistry;
    protected TestConfigurationService $testConfigService;

    private static bool $schemaCreated = false;

    protected function setUp(): void
    {
        // Create client first
        $this->client = static::createClient();

        // Get services after client is created
        $container = $this->client->getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->configurationService = $container->get(ConfigurationService::class);
        $this->domainRegistry = $container->get(ConfigurationDomainRegistry::class);
        $this->testConfigService = new TestConfigurationService(
            $this->entityManager,
            $container->get(ConfigurationRepository::class)
        );

        // Create database schema only once
        if (!self::$schemaCreated) {
            $this->createDatabaseSchema();
            self::$schemaCreated = true;
        }

        // Clear database before each test
        $this->clearDatabase();
    }

    protected function tearDown(): void
    {
        $this->clearDatabase();

        // Clean up test database file
        $testDbPath = __DIR__ . '/../../var/test.db';
        if (file_exists($testDbPath)) {
            unlink($testDbPath);
        }

        parent::tearDown();
    }

    protected function clearDatabase(): void
    {
        // Clear all entities - only include entities that actually exist
        $this->entityManager->createQuery('DELETE FROM App\Entity\Configuration')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Album')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Artist')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Track')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\TrackFile')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\AlbumStatistic')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\ArtistStatistic')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\LibraryStatistic')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Task')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Library')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Medium')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\UnmatchedTrack')->execute();

        $this->entityManager->flush();
    }

    protected function assertConfigurationExists(string $key, $expectedValue = null): void
    {
        $config = $this->entityManager->getRepository(Configuration::class)->findOneBy(['key' => $key]);
        $this->assertNotNull($config, "Configuration with key '{$key}' should exist");
        // Ensure we have the latest persisted value if it was recently updated in another request
        $this->entityManager->refresh($config);

        if (null !== $expectedValue) {
            $this->assertEquals($expectedValue, $config->getParsedValue(), "Configuration '{$key}' should have expected value");
        }
    }

    protected function assertConfigurationNotExists(string $key): void
    {
        $config = $this->entityManager->getRepository(Configuration::class)->findOneBy(['key' => $key]);
        $this->assertNull($config, "Configuration with key '{$key}' should not exist");
    }

    protected function assertJsonResponseSuccess(): void
    {
        $response = $this->client->getResponse();
        $this->assertTrue($response->isSuccessful(), 'Response should be successful');

        $content = $response->getContent();
        $this->assertNotEmpty($content, 'Response should have content');

        $data = json_decode($content, true);
        $this->assertIsArray($data, 'Response should be valid JSON');
        $this->assertArrayHasKey('success', $data, 'Response should have success key');
    }

    protected function assertJsonResponseError(): void
    {
        $response = $this->client->getResponse();
        $this->assertFalse($response->isSuccessful(), 'Response should not be successful');

        $content = $response->getContent();
        $this->assertNotEmpty($content, 'Response should have content');

        $data = json_decode($content, true);
        $this->assertIsArray($data, 'Response should be valid JSON');
        $this->assertArrayHasKey('success', $data, 'Response should have success key');
        $this->assertFalse($data['success'], 'Success should be false for error responses');
        $this->assertArrayHasKey('error', $data, 'Response should contain an error message');
    }

    protected function getJsonResponseData(): array
    {
        $response = $this->client->getResponse();
        $content = $response->getContent();
        $data = json_decode($content, true);

        if (!\is_array($data)) {
            throw new RuntimeException('Response is not valid JSON');
        }

        return $data;
    }

    protected function createTestConfiguration(string $key, $value): Configuration
    {
        $config = new Configuration();
        $config->setKey($key);
        $config->setParsedValue($value);

        $this->entityManager->persist($config);
        $this->entityManager->flush();

        return $config;
    }

    protected function getTestConfiguration(string $key): ?Configuration
    {
        return $this->entityManager->getRepository(Configuration::class)->findOneBy(['key' => $key]);
    }

    protected function assertFormFieldExists(string $fieldName, string $selector): void
    {
        $crawler = $this->client->getCrawler();
        $this->assertGreaterThan(0, $crawler->filter($selector)->count(), "Form field '{$fieldName}' should exist with selector '{$selector}'");
    }

    protected function assertFormFieldValue(string $fieldName, string $selectorOrExpected, $expectedValue = null): void
    {
        $crawler = $this->client->getCrawler();
        // Backward-compatible signature: if only two args provided, treat second as expected value
        if (null === $expectedValue) {
            $expectedValue = $selectorOrExpected;
            $selector = \sprintf('select[name="%s"], input[name="%s"], textarea[name="%s"]', $fieldName, $fieldName, $fieldName);
        } else {
            $selector = $selectorOrExpected;
        }

        $field = $crawler->filter($selector)->first();
        $this->assertGreaterThan(0, $field->count(), "Form field '{$fieldName}' should exist");

        // Determine actual value, handling <select> elements specially
        $actualValue = null;
        $node = $field->getNode(0);
        if ($node instanceof DOMElement && 'select' === mb_strtolower($node->nodeName)) {
            $selected = $field->filter('option[selected]');
            if ($selected->count() > 0) {
                $actualValue = $selected->attr('value');
            } else {
                // Fallback to the first option's value if none explicitly selected
                $firstOption = $field->filter('option')->first();
                $actualValue = $firstOption->count() > 0 ? $firstOption->attr('value') : null;
            }
        } else {
            $actualValue = $field->attr('value') ?? $field->attr('content');
        }
        $this->assertEquals($expectedValue, $actualValue, "Form field '{$fieldName}' should have expected value");
    }

    protected function assertPageContainsText(string $text): void
    {
        $crawler = $this->client->getCrawler();
        $this->assertGreaterThan(0, $crawler->filter("body:contains('{$text}')")->count(), "Page should contain text '{$text}'");
    }

    protected function assertPageNotContainsText(string $text): void
    {
        $crawler = $this->client->getCrawler();
        $this->assertEquals(0, $crawler->filter("body:contains('{$text}')")->count(), "Page should not contain text '{$text}'");
    }

    protected function assertRedirectsTo(string $expectedRoute): void
    {
        $this->assertResponseRedirects();
        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertStringContainsString($expectedRoute, $location, "Should redirect to route containing '{$expectedRoute}'");
    }

    protected function assertResponseStatusCode(int $expectedStatusCode): void
    {
        $this->assertEquals($expectedStatusCode, $this->client->getResponse()->getStatusCode());
    }

    protected function assertFlashMessageExists(string $type, ?string $message = null): void
    {
        $session = $this->client->getContainer()->get('session');
        $flashBag = $session->getFlashBag();

        if (null === $message) {
            $this->assertTrue($flashBag->has($type), "Flash message of type '{$type}' should exist");
        } else {
            $messages = $flashBag->get($type);
            $this->assertContains($message, $messages, "Flash message '{$message}' of type '{$type}' should exist");
        }
    }

    protected function assertNoFlashMessages(): void
    {
        $session = $this->client->getContainer()->get('session');
        $flashBag = $session->getFlashBag();

        $allTypes = $flashBag->keys();
        $this->assertEmpty($allTypes, 'No flash messages should exist');
    }

    protected function loginAsUser(): void
    {
        // For now, we'll assume no authentication is required in tests
        // This can be extended later if authentication is implemented
    }

    protected function createDatabaseSchema(): void
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);

        try {
            // For SQLite in-memory database, just create the schema without dropping
            $schemaTool->createSchema($metadata);
        } catch (Exception $e) {
            // If tables already exist, try to update schema instead
            try {
                $schemaTool->updateSchema($metadata);
            } catch (Exception $e2) {
                // If update fails, that's ok - tables might already be correct
            }
        }
    }
}
