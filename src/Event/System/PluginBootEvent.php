<?php

declare(strict_types=1);

namespace App\Event\System;

use App\Entity\Plugin;
use Symfony\Contracts\EventDispatcher\Event;

class PluginBootEvent extends Event
{
    public function __construct(
        private readonly Plugin $plugin
    ) {
    }

    public function getPlugin(): Plugin
    {
        return $this->plugin;
    }
}
