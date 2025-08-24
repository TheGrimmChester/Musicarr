<?php

declare(strict_types=1);

namespace App\Tests\Unit\Plugin;

use App\Plugin\PluginManager;
use PHPUnit\Framework\TestCase;

class PluginManagerTest extends TestCase
{
    private string $testPluginsDir;
    private PluginManager $pluginManager;

    protected function setUp(): void
    {
        // Create a temporary test plugins directory
        $this->testPluginsDir = sys_get_temp_dir() . '/test-plugins-' . uniqid();
        mkdir($this->testPluginsDir, 0777, true);

        // Create test plugin JSON files
        $this->createTestPlugin('test-plugin-1', [
            'name' => 'test-plugin-1',
            'version' => '1.0.0',
            'description' => 'Test Description 1',
            'author' => 'Test Author 1',
            'bundle_class' => 'Test\\Plugin1\\TestPlugin1Bundle',
        ]);

        $this->createTestPlugin('test-plugin-2', [
            'name' => 'test-plugin-2',
            'version' => '2.0.0',
            'description' => 'Test Description 2',
            'author' => 'Test Author 2',
            'bundle_class' => 'Test\\Plugin2\\TestPlugin2Bundle',
        ]);

        // Create PluginManager with test plugins directory
        $this->pluginManager = new PluginManager($this->testPluginsDir);
    }

    protected function tearDown(): void
    {
        // Clean up test plugins directory
        if (is_dir($this->testPluginsDir)) {
            $this->removeDirectory($this->testPluginsDir);
        }
    }

    private function createTestPlugin(string $name, array $data): void
    {
        $pluginDir = $this->testPluginsDir . '/' . $name;
        mkdir($pluginDir, 0777, true);

        $pluginJsonPath = $pluginDir . '/plugin.json';
        file_put_contents($pluginJsonPath, json_encode($data, \JSON_PRETTY_PRINT));
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public function testGetPlugins(): void
    {
        $plugins = $this->pluginManager->getPlugins();

        $this->assertCount(2, $plugins);
        $this->assertArrayHasKey('test-plugin-1', $plugins);
        $this->assertArrayHasKey('test-plugin-2', $plugins);
    }

    public function testGetPlugin(): void
    {
        $plugin = $this->pluginManager->getPlugin('test-plugin-1');

        $this->assertNotNull($plugin);
        $this->assertIsArray($plugin);
        $this->assertEquals('test-plugin-1', $plugin['name']);
    }

    public function testGetNonExistentPlugin(): void
    {
        $plugin = $this->pluginManager->getPlugin('non-existent');

        $this->assertNull($plugin);
    }

    public function testHasPlugin(): void
    {
        $this->assertTrue($this->pluginManager->hasPlugin('test-plugin-1'));
        $this->assertTrue($this->pluginManager->hasPlugin('test-plugin-2'));
        $this->assertFalse($this->pluginManager->hasPlugin('non-existent'));
    }

    public function testGetPluginPath(): void
    {
        $path = $this->pluginManager->getPluginPath('test-plugin-1');

        $this->assertNotNull($path);
        $this->assertStringContainsString('test-plugin-1', $path);
    }

    public function testGetPluginBundleClass(): void
    {
        $bundleClass = $this->pluginManager->getPluginBundleClass('test-plugin-1');

        $this->assertEquals('Test\\Plugin1\\TestPlugin1Bundle', $bundleClass);
    }

    public function testGetPluginDirectoryByBundleClass(): void
    {
        $path = $this->pluginManager->getPluginDirectoryByBundleClass('Test\\Plugin1\\TestPlugin1Bundle');

        $this->assertNotNull($path);
        $this->assertStringContainsString('test-plugin-1', $path);
    }

    public function testRefreshPlugins(): void
    {
        // Initially we have 2 plugins
        $this->assertCount(2, $this->pluginManager->getPlugins());

        // Refresh should maintain the same plugins
        $this->pluginManager->refreshPlugins();

        $this->assertCount(2, $this->pluginManager->getPlugins());
    }
}
