<?php

// Ensure the bundles_enabled.json file exists
$bundlesEnabledPath = __DIR__.'/../var/persistent/config/bundles_enabled.json';
if (!file_exists($bundlesEnabledPath)) {
    // Create the directory if it doesn't exist
    $dir = dirname($bundlesEnabledPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    // Create the file with empty JSON
    file_put_contents($bundlesEnabledPath, '{}');
}

$enabledPlugins = json_decode(file_get_contents($bundlesEnabledPath), true) ?? [];


// Auto-discover and register plugins from the plugins directory
$bundles = [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Twig\Extra\TwigExtraBundle\TwigExtraBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
    Symfony\Bundle\MakerBundle\MakerBundle::class => ['dev' => true],
    Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class => ['dev' => true, 'test' => true],
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
    Symfony\WebpackEncoreBundle\WebpackEncoreBundle::class => ['all' => true],
    Symfony\UX\StimulusBundle\StimulusBundle::class => ['all' => true],
    Symfony\UX\TwigComponent\TwigComponentBundle::class => ['all' => true],
    Symfony\UX\LiveComponent\LiveComponentBundle::class => ['all' => true],
    Sylius\TwigHooks\SyliusTwigHooksBundle::class => ['all' => true],
];

// Auto-discover enabled plugins
foreach ($enabledPlugins as $class => $enabled) {
    if ($enabled && class_exists($class)) {
        $bundles[$class] = ['all' => true];
    }
}

return $bundles;
