<?php

declare(strict_types=1);

namespace App\Plugin;

use Symfony\Component\HttpKernel\Bundle\Bundle;

abstract class AbstractPluginBundle extends Bundle
{
    use PluginTrait;
}
