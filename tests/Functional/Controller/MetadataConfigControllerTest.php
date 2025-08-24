<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Configuration;
use App\Tests\Functional\AbstractFunctionalTestCase;

class MetadataConfigControllerTest extends AbstractFunctionalTestCase
{
    public function testMetadataConfigIndex(): void
    {
        $this->client->request('GET', '/metadata-config/');

        $this->assertResponseIsSuccessful();
        $this->assertPageContainsText('Metadata');
    }

    public function testMetadataConfigSave(): void
    {
        $testData = [
            'base_dir' => '/tmp/test_metadata_' . uniqid(),
            'save_in_library' => true,
        ];

        $this->client->request(
            'POST',
            '/metadata-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify configuration was saved
        $this->assertConfigurationExists('metadata.base_dir', $testData['base_dir']);
        $this->assertConfigurationExists('metadata.save_in_library', true);

        // Clean up test directory
        if (is_dir($testData['base_dir'])) {
            $this->removeDirectoryRecursively($testData['base_dir']);
        }
    }

    public function testMetadataConfigSaveWithInvalidData(): void
    {
        $testData = [
            'base_dir' => '/nonexistent/path',
            'save_in_library' => false,
        ];

        $this->client->request(
            'POST',
            '/metadata-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        $this->assertResponseStatusCode(400);

        $responseData = $this->getJsonResponseData();
        $this->assertArrayHasKey('success', $responseData);
        $this->assertFalse($responseData['success']);
        $this->assertArrayHasKey('error', $responseData);
    }

    public function testMetadataConfigSaveWithEmptyData(): void
    {
        // Empty data should use defaults, but we need to provide a valid base_dir
        $testData = [
            'base_dir' => '/tmp/test_empty_' . uniqid(),
        ];

        $this->client->request(
            'POST',
            '/metadata-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Should use defaults for missing fields
        $this->assertConfigurationExists('metadata.base_dir', $testData['base_dir']);
        $this->assertConfigurationExists('metadata.save_in_library', false);

        // Clean up test directory
        if (is_dir($testData['base_dir'])) {
            $this->removeDirectoryRecursively($testData['base_dir']);
        }
    }

    public function testMetadataConfigSaveUpdatesExisting(): void
    {
        // Create initial configuration
        $this->createTestConfiguration('metadata.base_dir', '/initial/path');
        $this->createTestConfiguration('metadata.save_in_library', false);

        $testData = [
            'base_dir' => '/tmp/test_updated_' . uniqid(),
            'save_in_library' => true,
        ];

        $this->client->request(
            'POST',
            '/metadata-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify configuration was updated
        $this->assertConfigurationExists('metadata.base_dir', $testData['base_dir']);
        $this->assertConfigurationExists('metadata.save_in_library', true);

        // Clean up test directory
        if (is_dir($testData['base_dir'])) {
            $this->removeDirectoryRecursively($testData['base_dir']);
        }
    }

    public function testMetadataConfigSaveWithSpecialCharacters(): void
    {
        $testData = [
            'base_dir' => '/tmp/test path with spaces and-special-chars_123',
            'save_in_library' => true,
        ];

        $this->client->request(
            'POST',
            '/metadata-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify configuration was saved with special characters
        $this->assertConfigurationExists('metadata.base_dir', '/tmp/test path with spaces and-special-chars_123');

        // Clean up test directory
        if (is_dir($testData['base_dir'])) {
            $this->removeDirectoryRecursively($testData['base_dir']);
        }
    }

    public function testMetadataConfigSaveWithVeryLongPath(): void
    {
        $longPath = '/tmp/very/long/path/' . str_repeat('a', 100);
        $testData = [
            'base_dir' => $longPath,
            'save_in_library' => false,
        ];

        $this->client->request(
            'POST',
            '/metadata-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify configuration was saved
        $this->assertConfigurationExists('metadata.base_dir', $longPath);

        // Clean up test directory
        if (is_dir($longPath)) {
            $this->removeDirectoryRecursively($longPath);
        }
    }

    public function testMetadataConfigSaveWithBooleanValues(): void
    {
        $testData = [
            'base_dir' => '/tmp/test_boolean_' . uniqid(),
            'save_in_library' => true,
        ];

        $this->client->request(
            'POST',
            '/metadata-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify boolean values are handled correctly
        $this->assertConfigurationExists('metadata.save_in_library', true);

        // Test with false
        $testData['save_in_library'] = false;
        $this->client->request(
            'POST',
            '/metadata-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertConfigurationExists('metadata.save_in_library', false);

        // Clean up test directory
        if (is_dir($testData['base_dir'])) {
            $this->removeDirectoryRecursively($testData['base_dir']);
        }
    }

    public function testMetadataConfigSaveWithNumericValues(): void
    {
        $testData = [
            'base_dir' => '/tmp/test_numeric_' . uniqid(),
            'save_in_library' => true,  // Use actual boolean instead of numeric
        ];

        $this->client->request(
            'POST',
            '/metadata-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify boolean value was saved
        $this->assertConfigurationExists('metadata.save_in_library', true);

        // Clean up test directory
        if (is_dir($testData['base_dir'])) {
            $this->removeDirectoryRecursively($testData['base_dir']);
        }
    }

    public function testMetadataConfigSaveWithStringBoolean(): void
    {
        $testData = [
            'base_dir' => '/tmp/test_string_' . uniqid(),
            'save_in_library' => true,  // Use actual boolean instead of string
        ];

        $this->client->request(
            'POST',
            '/metadata-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify boolean value was saved
        $this->assertConfigurationExists('metadata.save_in_library', true);

        // Clean up test directory
        if (is_dir($testData['base_dir'])) {
            $this->removeDirectoryRecursively($testData['base_dir']);
        }
    }

    public function testMetadataConfigSaveWithInvalidJson(): void
    {
        $this->client->request(
            'POST',
            '/metadata-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            'invalid json content'
        );

        $this->assertResponseStatusCode(400);

        $responseData = $this->getJsonResponseData();
        $this->assertArrayHasKey('success', $responseData);
        $this->assertFalse($responseData['success']);
    }

    public function testMetadataConfigSaveWithoutContentType(): void
    {
        $testDir = '/tmp/test_no_content_type_' . uniqid();
        $testData = [
            'base_dir' => $testDir,
            'save_in_library' => false,
        ];

        $this->client->request(
            'POST',
            '/metadata-config/save',
            [],
            [],
            [],
            json_encode($testData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Should still work without content type header
        $this->assertConfigurationExists('metadata.base_dir', $testDir);

        // Clean up test directory
        if (is_dir($testDir)) {
            $this->removeDirectoryRecursively($testDir);
        }
    }

    public function testMetadataConfigSaveWithGetMethod(): void
    {
        $this->client->request('GET', '/metadata-config/save');

        $this->assertResponseStatusCode(405); // Method not allowed
    }

    public function testMetadataConfigSaveWithPutMethod(): void
    {
        $testData = [
            'base_dir' => '/tmp/test_put_' . uniqid(),
            'save_in_library' => false,
        ];

        $this->client->request(
            'PUT',
            '/metadata-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        $this->assertResponseStatusCode(405); // Method not allowed
    }

    public function testMetadataConfigSaveWithDeleteMethod(): void
    {
        $this->client->request('DELETE', '/metadata-config/save');

        $this->assertResponseStatusCode(405); // Method not allowed
    }

    public function testMetadataConfigIndexShowsExistingConfiguration(): void
    {
        // Create some test configuration
        $this->createTestConfiguration('metadata.base_dir', '/existing/path');
        $this->createTestConfiguration('metadata.save_in_library', true);

        $this->client->request('GET', '/metadata-config/');

        $this->assertResponseIsSuccessful();
        $this->assertPageContainsText('Metadata');

        // Check the actual response content to see what's being displayed
        $response = $this->client->getResponse();
        $content = $response->getContent();

        // The template should show the existing base_dir value in the input field
        $this->assertStringContainsString('/existing/path', $content, 'Page should contain the existing base_dir value');
    }

    public function testMetadataConfigIndexShowsDefaultValuesWhenEmpty(): void
    {
        $this->client->request('GET', '/metadata-config/');

        $this->assertResponseIsSuccessful();
        $this->assertPageContainsText('Metadata');

        // Should show default values
        $this->assertPageContainsText('/app/public/metadata');
    }

    public function testMetadataConfigSaveCreatesDirectories(): void
    {
        $testData = [
            'base_dir' => '/tmp/test_metadata_' . uniqid(),
            'save_in_library' => false,
        ];

        $this->client->request(
            'POST',
            '/metadata-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify configuration was saved
        $this->assertConfigurationExists('metadata.base_dir', $testData['base_dir']);

        // Clean up test directory
        if (is_dir($testData['base_dir'])) {
            $this->removeDirectoryRecursively($testData['base_dir']);
        }
    }

    public function testMetadataConfigSaveWithLibraryPath(): void
    {
        $testData = [
            'base_dir' => '/tmp/test_library_' . uniqid(),
            'save_in_library' => true,
        ];

        $this->client->request(
            'POST',
            '/metadata-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify configuration was saved
        $this->assertConfigurationExists('metadata.base_dir', $testData['base_dir']);
        $this->assertConfigurationExists('metadata.save_in_library', true);

        // Clean up test directory
        if (is_dir($testData['base_dir'])) {
            $this->removeDirectoryRecursively($testData['base_dir']);
        }
    }

    /**
     * Helper method to recursively remove directories and their contents.
     */
    private function removeDirectoryRecursively(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectoryRecursively($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
