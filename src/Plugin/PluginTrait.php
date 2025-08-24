<?php

declare(strict_types=1);

namespace App\Plugin;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

trait PluginTrait
{
    protected ?EntityManagerInterface $entityManager = null;
    protected ?LoggerInterface $logger = null;
    protected ?EventDispatcherInterface $eventDispatcher = null;

    public function setEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->entityManager = $entityManager;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    protected function addEventListener(string $eventName, callable $listener, int $priority = 0): void
    {
        if ($this->eventDispatcher) {
            $this->eventDispatcher->addListener($eventName, $listener, $priority);
        }
    }

    protected function addEventSubscriber($subscriber): void
    {
        if ($this->eventDispatcher) {
            $this->eventDispatcher->addSubscriber($subscriber);
        }
    }

    protected function getSettings(): array
    {
        // This would need to be implemented based on how you want to store plugin settings
        return [];
    }

    protected function saveSettings(array $settings): void
    {
        // This would need to be implemented based on how you want to store plugin settings
        if ($this->logger) {
            $this->logger->info('Settings saved', $settings);
        }
    }
}
