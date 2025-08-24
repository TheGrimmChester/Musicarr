<?php

if (file_exists(dirname(__DIR__).'/var/cache/prod/App_KernelProdContainer.preload.php')) {
    require dirname(__DIR__).'/var/cache/prod/App_KernelProdContainer.preload.php';
}

// Lightweight PSR-4 autoloader for plugins under plugins/*/src
spl_autoload_register(static function (string $class): void {
    // Expect plugin namespaces to start with Musicarr\
    if (str_starts_with($class, 'Musicarr\\')) {
        $baseDir = dirname(__DIR__) . '/plugins/';
        // Convert namespace to path: Musicarr\Vendor\Foo -> plugins/vendor-foo/src/...
        $parts = explode('\\', $class);
        // Build plugin dir from first two parts if available
        $vendor = $parts[0] ?? 'Musicarr';
        $package = $parts[1] ?? null;
        if ($package === null) {
            return;
        }
        $pluginDir = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $package));
        $relativePath = implode('/', array_slice($parts, 2)) . '.php';
        $paths = [
            $baseDir . $pluginDir . '/src/' . $relativePath,
            // fallback: keep exact case
            $baseDir . $package . '/src/' . $relativePath,
        ];
        foreach ($paths as $path) {
            if (is_file($path)) {
                require $path;
                return;
            }
        }
    }
});
