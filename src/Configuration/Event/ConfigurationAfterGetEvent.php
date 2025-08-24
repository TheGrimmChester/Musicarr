<?php

declare(strict_types=1);

namespace App\Configuration\Event;

use App\Entity\Configuration;

class ConfigurationAfterGetEvent extends ConfigurationEvent
{
    public const NAME = 'app.configuration.after_get';

    protected mixed $defaultValue;
    protected mixed $finalValue;

    public function __construct(Configuration $configuration, string $key, mixed $value, mixed $defaultValue, mixed $finalValue, ?string $description = null)
    {
        parent::__construct($configuration, $key, $value, $description);
        $this->defaultValue = $defaultValue;
        $this->finalValue = $finalValue;
    }

    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    public function getFinalValue(): mixed
    {
        return $this->finalValue;
    }
}
