<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Psr\Log\LoggerInterface;
use Symfony\AI\Mate\Service\Logger;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->parameters()
        ->set('mate.root_dir', '%env(MATE_ROOT_DIR)%')
        ->set('mate.cache_dir', sys_get_temp_dir().'/mate')
        ->set('mate.env_file', null)
        ->set('mate.disabled_features', [])
    ;

    $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()

        ->set(LoggerInterface::class, Logger::class)
        ->alias(Logger::class, LoggerInterface::class)
    ;
};
