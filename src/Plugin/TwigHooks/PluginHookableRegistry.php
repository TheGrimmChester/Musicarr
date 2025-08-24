<?php

declare(strict_types=1);

namespace App\Plugin\TwigHooks;

use Sylius\TwigHooks\Hookable\AbstractHookable;

class PluginHookableRegistry
{
    private array $hookables = [];

    public function __construct()
    {
        $this->registerPluginHookables();
    }

    private function registerPluginHookables(): void
    {
        // This will be populated by plugins during their build process
        // Plugins can add their hookables to this registry
    }

    public function addHookable(AbstractHookable $hookable): void
    {
        $this->hookables[] = $hookable;
    }

    public function getHookables(): iterable
    {
        return $this->hookables;
    }
}
