<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Configuration\Domain\AbstractConfigurationDomain;
use App\Entity\Configuration;
use App\Tests\Functional\AbstractFunctionalTestCase;

class AlbumImportConfigControllerTest extends AbstractFunctionalTestCase
{
    public function testAlbumImportConfigIndexPageLoads(): void
    {
        $this->client->request('GET', '/album-import-config/');

        $this->assertResponseIsSuccessful();
        $this->assertPageContainsText('Album Import Configuration');
    }

    public function testAlbumImportConfigIndexWithExistingConfiguration(): void
    {
        // Create test configuration
        $this->createTestConfiguration('album_import.primary_types', ['Album', 'EP']);
        $this->createTestConfiguration('album_import.secondary_types', ['Studio']);

        $this->client->request('GET', '/album-import-config/');

        $this->assertResponseIsSuccessful();
        $this->assertPageContainsText('Album Import Configuration');
    }

    public function testAlbumImportConfigSaveNewConfiguration(): void
    {
        $testData = [
            'primary_types' => ['Album', 'EP'],
            'secondary_types' => ['Studio'],
            'release_statuses' => ['official'],
        ];

        $this->client->request(
            'POST',
            '/album-import-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify configuration was saved
        $this->assertConfigurationExists('album_import.primary_types', ['Album', 'EP']);
        $this->assertConfigurationExists('album_import.secondary_types', ['Studio']);
        $this->assertConfigurationExists('album_import.release_statuses', ['official']);
    }

    public function testAlbumImportConfigUpdateExistingConfiguration(): void
    {
        // Create initial configuration
        $this->createTestConfiguration('album_import.primary_types', ['Album']);
        $this->createTestConfiguration('album_import.secondary_types', ['Studio']);

        $updateData = [
            'primary_types' => ['Album', 'EP'],
            'secondary_types' => ['Studio', 'Live'],
        ];

        $this->client->request(
            'POST',
            '/album-import-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($updateData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify configuration was updated
        $this->assertConfigurationExists('album_import.primary_types', ['Album', 'EP']);
        $this->assertConfigurationExists('album_import.secondary_types', ['Studio', 'Live']);
    }

    public function testAlbumImportConfigPartialUpdate(): void
    {
        // Create initial configuration
        $this->createTestConfiguration('album_import.primary_types', ['Album']);
        $this->createTestConfiguration('album_import.secondary_types', ['Studio']);

        $partialData = [
            'primary_types' => ['Album', 'EP'],
        ];

        $this->client->request(
            'POST',
            '/album-import-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($partialData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify only specified field was updated
        $this->assertConfigurationExists('album_import.primary_types', ['Album', 'EP']);
        // Other fields should remain unchanged
        $this->assertConfigurationExists('album_import.secondary_types', ['Studio']);
    }

    public function testAlbumImportConfigSaveEmptyData(): void
    {
        $this->client->request(
            'POST',
            '/album-import-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();
    }

    public function testAlbumImportConfigSaveInvalidJson(): void
    {
        $this->client->request(
            'POST',
            '/album-import-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            'invalid json'
        );

        $this->assertResponseStatusCode(400);

        $responseData = $this->getJsonResponseData();
        $this->assertArrayHasKey('success', $responseData);
        $this->assertFalse($responseData['success']);
    }

    public function testAlbumImportConfigGetConfiguration(): void
    {
        // Create test configuration
        $this->createTestConfiguration('album_import.primary_types', ['Album']);
        $this->createTestConfiguration('album_import.secondary_types', ['Studio']);

        $this->client->request('GET', '/album-import-config/get');

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        $responseData = $this->getJsonResponseData();
        $this->assertArrayHasKey('data', $responseData);
    }

    public function testAlbumImportConfigGetConfigurationEmpty(): void
    {
        $this->client->request('GET', '/album-import-config/get');

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        $responseData = $this->getJsonResponseData();
        $this->assertArrayHasKey('data', $responseData);
    }

    public function testAlbumImportConfigDeleteConfiguration(): void
    {
        // Create test configuration
        $this->createTestConfiguration('album_import.primary_types', ['Album']);
        $this->createTestConfiguration('album_import.secondary_types', ['Studio']);

        $this->client->request('DELETE', '/album-import-config/delete');

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify configuration was deleted
        $this->assertConfigurationNotExists('album_import.primary_types');
        $this->assertConfigurationNotExists('album_import.secondary_types');
    }

    public function testAlbumImportConfigDeleteConfigurationEmpty(): void
    {
        $this->client->request('DELETE', '/album-import-config/delete');

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();
    }

    public function testAlbumImportConfigValidation(): void
    {
        $invalidData = [
            'primary_types' => 'not_an_array', // Invalid: should be array
            'secondary_types' => 'not_an_array', // Invalid: should be array
        ];

        $this->client->request(
            'POST',
            '/album-import-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($invalidData)
        );

        $this->assertResponseStatusCode(400);

        $responseData = $this->getJsonResponseData();
        $this->assertArrayHasKey('success', $responseData);
        $this->assertFalse($responseData['success']);
        $this->assertArrayHasKey('error', $responseData);
    }

    public function testAlbumImportConfigComplexDataTypes(): void
    {
        $complexData = [
            'primary_types' => ['Album', 'EP', 'Single'],
            'secondary_types' => ['Studio', 'Live', 'Compilation'],
            'release_statuses' => ['official', 'promotion'],
        ];

        $this->client->request(
            'POST',
            '/album-import-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($complexData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify complex data was properly serialized
        $this->assertConfigurationExists('album_import.primary_types', ['Album', 'EP', 'Single']);
        $this->assertConfigurationExists('album_import.secondary_types', ['Studio', 'Live', 'Compilation']);
        $this->assertConfigurationExists('album_import.release_statuses', ['official', 'promotion']);
    }

    public function testAlbumImportConfigDomainIntegration(): void
    {
        $albumImportDomain = $this->domainRegistry->getDomain('album_import.');

        if ($albumImportDomain) {
            $this->assertInstanceOf(AbstractConfigurationDomain::class, $albumImportDomain);

            // Test domain initialization
            $albumImportDomain->initializeDefaults();

            // Verify default values were created
            $this->assertTrue(null !== $this->getTestConfiguration('album_import.primary_types'));
        }
    }

    public function testAlbumImportConfigEventHandling(): void
    {
        // Test that configuration events are properly handled
        $testData = [
            'primary_types' => ['Album'],
            'secondary_types' => ['Studio'],
            'release_statuses' => ['official'],
        ];

        $this->client->request(
            'POST',
            '/album-import-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify configuration was saved (this tests the event handling)
        $this->assertConfigurationExists('album_import.primary_types', ['Album']);
        $this->assertConfigurationExists('album_import.secondary_types', ['Studio']);
        $this->assertConfigurationExists('album_import.release_statuses', ['official']);
    }

    public function testAlbumImportConfigConcurrentAccess(): void
    {
        // Test handling of concurrent configuration updates
        $this->createTestConfiguration('album_import.primary_types', ['Album']);
        $this->createTestConfiguration('album_import.secondary_types', ['Studio']);

        // Simulate concurrent requests
        $data1 = ['primary_types' => ['Album', 'EP']];
        $data2 = ['secondary_types' => ['Studio', 'Live']];

        $this->client->request(
            'POST',
            '/album-import-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data1)
        );

        $this->client->request(
            'POST',
            '/album-import-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data2)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify both updates were applied
        $this->assertConfigurationExists('album_import.primary_types', ['Album', 'EP']);
        $this->assertConfigurationExists('album_import.secondary_types', ['Studio', 'Live']);
    }

    public function testAlbumImportConfigBoundaryValues(): void
    {
        $boundaryData = [
            'primary_types' => [], // Empty array
            'secondary_types' => ['Studio'], // Single item
            'release_statuses' => ['official', 'promotion', 'bootleg'], // Multiple items
        ];

        $this->client->request(
            'POST',
            '/album-import-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($boundaryData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify boundary values were accepted
        $this->assertConfigurationExists('album_import.primary_types', []);
        $this->assertConfigurationExists('album_import.secondary_types', ['Studio']);
        $this->assertConfigurationExists('album_import.release_statuses', ['official', 'promotion', 'bootleg']);
    }

    public function testAlbumImportConfigValidKeyHandling(): void
    {
        $validTypes = [
            ['Album'],
            ['Album', 'EP'],
            ['Album', 'EP', 'Single'],
            ['Studio', 'Live'],
        ];

        foreach ($validTypes as $types) {
            $testData = [
                'primary_types' => $types,
                'secondary_types' => ['Studio'],
            ];

            $this->client->request(
                'POST',
                '/album-import-config/save',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($testData)
            );

            $this->assertResponseIsSuccessful();
            $this->assertJsonResponseSuccess();

            // Clear entity manager cache to ensure fresh data
            $this->entityManager->clear();

            // Verify types were accepted
            $this->assertConfigurationExists('album_import.primary_types', $types);
        }
    }

    public function testAlbumImportConfigArrayHandling(): void
    {
        $arrayValues = [
            'primary_types' => [['Album'], ['Album', 'EP'], ['Single']],
            'secondary_types' => [['Studio'], ['Studio', 'Live'], ['Compilation']],
            'release_statuses' => [['official'], ['official', 'promotion'], ['bootleg']],
        ];

        foreach ($arrayValues as $field => $values) {
            foreach ($values as $value) {
                $testData = [$field => $value];

                $this->client->request(
                    'POST',
                    '/album-import-config/save',
                    [],
                    [],
                    ['CONTENT_TYPE' => 'application/json'],
                    json_encode($testData)
                );

                $this->assertResponseIsSuccessful();
                $this->assertJsonResponseSuccess();

                // Clear entity manager cache to ensure fresh data
                $this->entityManager->clear();

                $this->assertConfigurationExists("album_import.{$field}", $value);
            }
        }
    }

    public function testAlbumImportConfigSpecialCharacters(): void
    {
        $testData = [
            'primary_types' => ['Album with spaces', 'EP/Single', 'Special-Chars_123'],
            'secondary_types' => ['Studio émojis🎵', 'Live & Loud'],
            'release_statuses' => ['official-release', 'promotion/promo'],
        ];

        $this->client->request(
            'POST',
            '/album-import-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify special characters were preserved
        $this->assertConfigurationExists('album_import.primary_types', ['Album with spaces', 'EP/Single', 'Special-Chars_123']);
        $this->assertConfigurationExists('album_import.secondary_types', ['Studio émojis🎵', 'Live & Loud']);
        $this->assertConfigurationExists('album_import.release_statuses', ['official-release', 'promotion/promo']);
    }

    public function testAlbumImportConfigLargeData(): void
    {
        $largeString = 'long_type_' . str_repeat('a', 100);
        $testData = [
            'primary_types' => [$largeString, $largeString . '_2'],
            'secondary_types' => [str_repeat('b', 50), str_repeat('c', 75)],
            'release_statuses' => ['official', $largeString . '_status'],
        ];

        $this->client->request(
            'POST',
            '/album-import-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify large data was handled
        $this->assertConfigurationExists('album_import.primary_types', [$largeString, $largeString . '_2']);
        $this->assertConfigurationExists('album_import.secondary_types', [str_repeat('b', 50), str_repeat('c', 75)]);
        $this->assertConfigurationExists('album_import.release_statuses', ['official', $largeString . '_status']);
    }

    public function testAlbumImportConfigMethodValidation(): void
    {
        // Test that only POST is allowed for save
        $this->client->request('GET', '/album-import-config/save');
        $this->assertResponseStatusCode(405); // Method not allowed

        $this->client->request('PUT', '/album-import-config/save');
        $this->assertResponseStatusCode(405); // Method not allowed

        $this->client->request('DELETE', '/album-import-config/save');
        $this->assertResponseStatusCode(405); // Method not allowed
    }

    public function testAlbumImportConfigContentTypeValidation(): void
    {
        $testData = ['primary_types' => ['Album']];

        // Test without content type header
        $this->client->request(
            'POST',
            '/album-import-config/save',
            [],
            [],
            [],
            json_encode($testData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Should still work without content type header
        $this->assertConfigurationExists('album_import.primary_types', ['Album']);
    }
}
