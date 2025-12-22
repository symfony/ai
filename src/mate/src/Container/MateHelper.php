<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Container;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Helper methods for configuring AI Mate in config.php.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class MateHelper
{
    /**
     * Disable specific MCP features from one or more extensions.
     *
     * This function allows you to disable specific tools, resources, prompts, or
     * resource templates from MCP extensions at a granular level. It is useful for
     * disabling features that are known to cause issues or are not needed in your
     * project.
     *
     * Call this method only once. The second call will override the first one.
     *
     * Example usage in mate/config.php:
     * ```php
     * use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
     * use Symfony\AI\Mate\Container\MateHelper;
     *
     * return static function (ContainerConfigurator $container): void {
     *   MateHelper::disableFeatures($container, [
     *     'vendor/extension' => ['badTool', 'semiBadTool']
     *     'nyholm/example' => ['clock']
     *   ]);
     *
     *   $container->parameters()
     *    ->set('mate.cache_dir', sys_get_temp_dir().'/mate')
     *   // ...
     * }
     * ```
     *
     * @param array<string, list<string>> $extensions
     */
    public static function disableFeatures(ContainerConfigurator $container, array $extensions): void
    {
        $data = [];
        foreach ($extensions as $extension => $features) {
            foreach ($features as $feature) {
                $data[$extension][$feature] = ['enabled' => false];
            }
        }

        $container->parameters()->set('mate.disabled_features', $data);
    }
}
