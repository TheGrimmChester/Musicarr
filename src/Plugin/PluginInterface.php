<?php

declare(strict_types=1);

namespace App\Plugin;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.plugin')]
interface PluginInterface
{
    // Metadata
    public static function getPluginName(): string;

    public static function getVersion(): string;

    public static function getAuthor(): string;

    public static function getDescription(): string;
}
