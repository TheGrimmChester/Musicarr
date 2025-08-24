<?php

declare(strict_types=1);

namespace App\Configuration\Event;

use App\Entity\Configuration;

class ConfigurationBeforeGetEvent extends ConfigurationEvent
{
    public const NAME = 'app.configuration.before_get';

    protected mixed $defaultValue;

    public function __construct(Configuration $configuration, string $key, mixed $value, mixed $defaultValue, ?string $description = null)
    {
        parent::__construct($configuration, $key, $value, $description);
        $this->defaultValue = $defaultValue;
    }

    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }
}
