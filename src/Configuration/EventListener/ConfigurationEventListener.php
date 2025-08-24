<?php

declare(strict_types=1);

namespace App\Configuration\EventListener;

use App\Configuration\Event\ConfigurationAfterDeleteEvent;
use App\Configuration\Event\ConfigurationAfterGetEvent;
use App\Configuration\Event\ConfigurationAfterSetEvent;
use App\Configuration\Event\ConfigurationBeforeDeleteEvent;
use App\Configuration\Event\ConfigurationBeforeGetEvent;
use App\Configuration\Event\ConfigurationBeforeSetEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ConfigurationEventListener implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConfigurationBeforeSetEvent::NAME => 'onConfigurationBeforeSet',
            ConfigurationAfterSetEvent::NAME => 'onConfigurationAfterSet',
            ConfigurationBeforeDeleteEvent::NAME => 'onConfigurationBeforeDelete',
            ConfigurationAfterDeleteEvent::NAME => 'onConfigurationAfterDelete',
            ConfigurationBeforeGetEvent::NAME => 'onConfigurationBeforeGet',
            ConfigurationAfterGetEvent::NAME => 'onConfigurationAfterGet',
        ];
    }

    public function onConfigurationBeforeSet(ConfigurationBeforeSetEvent $event): void
    {
        $this->logger->info('Configuration value will be set', [
            'key' => $event->getKey(),
            'value' => $event->getValue(),
            'description' => $event->getDescription(),
        ]);
    }

    public function onConfigurationAfterSet(ConfigurationAfterSetEvent $event): void
    {
        $this->logger->info('Configuration value has been set', [
            'key' => $event->getKey(),
            'value' => $event->getValue(),
            'description' => $event->getDescription(),
        ]);
    }

    public function onConfigurationBeforeDelete(ConfigurationBeforeDeleteEvent $event): void
    {
        $this->logger->info('Configuration will be deleted', [
            'key' => $event->getKey(),
            'value' => $event->getValue(),
            'description' => $event->getDescription(),
        ]);
    }

    public function onConfigurationAfterDelete(ConfigurationAfterDeleteEvent $event): void
    {
        $this->logger->info('Configuration has been deleted', [
            'key' => $event->getKey(),
            'value' => $event->getValue(),
            'description' => $event->getDescription(),
        ]);
    }

    public function onConfigurationBeforeGet(ConfigurationBeforeGetEvent $event): void
    {
        $this->logger->debug('Configuration value will be retrieved', [
            'key' => $event->getKey(),
            'value' => $event->getValue(),
            'default_value' => $event->getDefaultValue(),
        ]);
    }

    public function onConfigurationAfterGet(ConfigurationAfterGetEvent $event): void
    {
        $this->logger->debug('Configuration value has been retrieved', [
            'key' => $event->getKey(),
            'value' => $event->getValue(),
            'default_value' => $event->getDefaultValue(),
            'final_value' => $event->getFinalValue(),
        ]);
    }
}
