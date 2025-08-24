<?php

declare(strict_types=1);

namespace App\Plugin;

use App\Plugin\DependencyInjection\PluginCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PluginBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new PluginCompilerPass());
    }
}
