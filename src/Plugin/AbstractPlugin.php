<?php

declare(strict_types=1);

namespace App\Plugin;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class AbstractPlugin implements PluginInterface
{
    protected LoggerInterface $logger;
    protected EventDispatcherInterface $eventDispatcher;

    public function __construct(
        LoggerInterface $logger,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Add an event listener to the plugin. This ensures the listener is only active
     * when the plugin is enabled.
     */
    protected function addEventListener(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->eventDispatcher->addListener($eventName, $listener, $priority);
    }

    /**
     * Add an event subscriber to the plugin.
     */
    protected function addEventSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->eventDispatcher->addSubscriber($subscriber);
    }

    /**
     * Check if the plugin is installed.
     */
    public function isInstalled(): bool
    {
        $bundlesFile = __DIR__ . '/../../config/bundles_enabled.json';
        if (!file_exists($bundlesFile)) {
            return false;
        }

        $enabledBundles = json_decode(file_get_contents($bundlesFile), true) ?? [];
        $bundleClass = static::class;

        return isset($enabledBundles[$bundleClass]) && $enabledBundles[$bundleClass];
    }

    /**
     * Check if the plugin is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->isInstalled();
    }
}
