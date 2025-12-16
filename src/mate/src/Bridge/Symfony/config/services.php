<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Mate\Bridge\Symfony\Capability\ServiceTool;
use Symfony\AI\Mate\Bridge\Symfony\Service\ContainerProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return function (ContainerConfigurator $configurator) {
    $configurator->parameters()
        ->set('ai_mate_symfony.cache_dir', '%mate.root_dir%/var/cache');

    $configurator->services()
        ->set(ContainerProvider::class)

        ->set(ServiceTool::class)
            ->args([
                '%ai_mate_symfony.cache_dir%',
                service(ContainerProvider::class),
            ]);
};
