<?php

declare(strict_types=1);

namespace App\Configuration\DependencyInjection;

use App\Configuration\Config\AbstractConfigurationTreeBuilder;
use App\Configuration\Config\ConfigurationProcessor;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ConfigurationCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ConfigurationProcessor::class)) {
            return;
        }

        $processorDefinition = $container->getDefinition(ConfigurationProcessor::class);

        // Find all configuration tree builder services
        $taggedServices = $container->findTaggedServiceIds('app.configuration.tree_builder');

        foreach ($taggedServices as $serviceId => $tags) {
            $serviceDefinition = $container->getDefinition($serviceId);
            $serviceClass = $serviceDefinition->getClass();

            // Check if the service extends the required base class
            if ($serviceClass && is_subclass_of($serviceClass, AbstractConfigurationTreeBuilder::class)) {
                // Extract domain from the class name (e.g., DownloaderConfigurationTreeBuilder -> downloader.)
                $domain = $this->extractDomainFromClassName($serviceClass);

                if ($domain) {
                    $processorDefinition->addMethodCall('addTreeBuilder', [
                        $domain,
                        new Reference($serviceId),
                    ]);
                }
            }
        }
    }

    private function extractDomainFromClassName(string $className): ?string
    {
        $parts = explode('\\', $className);
        $shortClassName = end($parts);

        if (preg_match('/^(.+)ConfigurationTreeBuilder$/', $shortClassName, $matches)) {
            $domain = $matches[1];

            // Convert camelCase to snake_case and add trailing dot
            $snakeCase = mb_strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $domain));

            return $snakeCase . '.';
        }

        return null;
    }
}
