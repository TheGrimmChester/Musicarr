<?php

declare(strict_types=1);

namespace App\Configuration\EventListener;

use App\Configuration\Domain\ConfigurationDomainRegistry;
use App\Configuration\Event\ConfigurationBeforeGetEvent;
use App\Configuration\Event\ConfigurationBeforeSetEvent;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\CacheInterface;

class ConfigurationValidationListener implements EventSubscriberInterface
{
    private array $validationRules = [];

    public function __construct(
        private LoggerInterface $logger,
        private CacheInterface $cache,
        private ConfigurationDomainRegistry $domainRegistry
    ) {
        // Load validation rules from all domains
        $this->validationRules = $this->domainRegistry->getAllValidationRules();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConfigurationBeforeSetEvent::NAME => 'onConfigurationBeforeSet',
            ConfigurationBeforeGetEvent::NAME => 'onConfigurationBeforeGet',
        ];
    }

    public function onConfigurationBeforeSet(ConfigurationBeforeSetEvent $event): void
    {
        $key = $event->getKey();
        $value = $event->getValue();

        if (isset($this->validationRules[$key])) {
            $this->validateConfiguration($key, $value, $this->validationRules[$key]);
        }

        // Clear cache for this configuration key
        $this->cache->delete('config_' . $key);

        $this->logger->info('Configuration validation completed', [
            'key' => $key,
            'value' => $value,
        ]);
    }

    public function onConfigurationBeforeGet(ConfigurationBeforeGetEvent $event): void
    {
        $key = $event->getKey();

        // Try to get from cache first
        $cachedValue = $this->cache->get('config_' . $key, function () {
            return null; // Return null to indicate cache miss
        });

        if (null !== $cachedValue) {
            $this->logger->debug('Configuration value retrieved from cache', [
                'key' => $key,
                'cached_value' => $cachedValue,
            ]);
        }
    }

    private function validateConfiguration(string $key, mixed $value, array $rules): void
    {
        // Type validation
        if (isset($rules['type'])) {
            $this->validateType($key, $value, $rules['type']);
        }

        // Range validation
        if (isset($rules['min']) && is_numeric($value)) {
            if ($value < $rules['min']) {
                throw new InvalidArgumentException("Configuration value for '{$key}' must be at least {$rules['min']}, got {$value}");
            }
        }

        if (isset($rules['max']) && is_numeric($value)) {
            if ($value > $rules['max']) {
                throw new InvalidArgumentException("Configuration value for '{$key}' must be at most {$rules['max']}, got {$value}");
            }
        }

        // Required validation
        if (isset($rules['required']) && $rules['required'] && empty($value)) {
            throw new InvalidArgumentException("Configuration value for '{$key}' is required and cannot be empty");
        }
    }

    private function validateType(string $key, mixed $value, string $expectedType): void
    {
        $actualType = \gettype($value);

        if ('boolean' === $expectedType && 'boolean' !== $actualType) {
            throw new InvalidArgumentException("Configuration value for '{$key}' must be boolean, got {$actualType}");
        }

        if ('integer' === $expectedType && 'integer' !== $actualType) {
            throw new InvalidArgumentException("Configuration value for '{$key}' must be integer, got {$actualType}");
        }

        if ('float' === $expectedType && 'double' !== $actualType) {
            throw new InvalidArgumentException("Configuration value for '{$key}' must be float, got {$actualType}");
        }

        if ('string' === $expectedType && 'string' !== $actualType) {
            throw new InvalidArgumentException("Configuration value for '{$key}' must be string, got {$actualType}");
        }
    }
}
