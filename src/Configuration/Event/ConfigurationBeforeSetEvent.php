<?php

declare(strict_types=1);

namespace App\Configuration\Event;

use App\Entity\Configuration;

class ConfigurationBeforeSetEvent extends ConfigurationEvent
{
    public const NAME = 'app.configuration.before_set';

    public function __construct(Configuration $configuration, string $key, mixed $value, ?string $description = null)
    {
        parent::__construct($configuration, $key, $value, $description);
    }
}
