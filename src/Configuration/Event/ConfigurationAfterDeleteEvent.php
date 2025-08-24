<?php

declare(strict_types=1);

namespace App\Configuration\Event;

use App\Entity\Configuration;

class ConfigurationAfterDeleteEvent extends ConfigurationEvent
{
    public const NAME = 'app.configuration.after_delete';

    public function __construct(Configuration $configuration, string $key, mixed $value, ?string $description = null)
    {
        parent::__construct($configuration, $key, $value, $description);
    }
}
