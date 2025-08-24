<?php

declare(strict_types=1);

namespace App\Configuration\Event;

use App\Entity\Configuration;
use Symfony\Contracts\EventDispatcher\Event;

abstract class ConfigurationEvent extends Event
{
    protected Configuration $configuration;
    protected string $key;
    protected mixed $value;
    protected ?string $description;

    public function __construct(Configuration $configuration, string $key, mixed $value, ?string $description = null)
    {
        $this->configuration = $configuration;
        $this->key = $key;
        $this->value = $value;
        $this->description = $description;
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
}
