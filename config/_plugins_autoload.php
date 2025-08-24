<?php

// Dynamic autoloader for Musicarr plugins.
// This avoids adding per-plugin PSR-4 entries in composer.json.
// It maps namespaces like Musicarr\ExamplePlugin\... to
// either plugins/example-plugin/src or plugins/ExamplePlugin/src automatically.

spl_autoload_register(static function (string $class): void {
    // Only handle Musicarr\ namespaces
    if (strncmp($class, 'Musicarr\\', 9) !== 0) {
        return;
    }

    // Extract top-level plugin segment
    $parts = explode('\\', $class);
    if (count($parts) < 2) {
        return;
    }

    $vendor = $parts[0]; // Musicarr
    $pluginSegment = $parts[1]; // ExamplePlugin
    if ($vendor !== 'Musicarr') {
        return;
    }

    // Remaining relative path inside src
    $relativePath = implode('/', array_slice($parts, 2)) . '.php';

    $pluginsDir = __DIR__ . '/../plugins';

    // Directory candidates for the plugin
    $camelDir = $pluginsDir . '/' . $pluginSegment . '/src/' . $relativePath;

    // Convert CamelCase to kebab-case (ExamplePlugin -> example-plugin)
    $kebabName = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $pluginSegment));
    $kebabDir = $pluginsDir . '/' . $kebabName . '/src/' . $relativePath;

    if (is_file($camelDir)) {
        require $camelDir;
        return;
    }

    if (is_file($kebabDir)) {
        require $kebabDir;
        return;
    }

    // Also try scanning all plugin src roots once to handle nested cases
    static $srcRoots = null;
    if ($srcRoots === null) {
        $srcRoots = glob($pluginsDir . '/*/src', GLOB_ONLYDIR) ?: [];
    }

    foreach ($srcRoots as $root) {
        $candidate = $root . '/' . $relativePath;
        if (is_file($candidate)) {
            require $candidate;
            return;
        }
    }
});
