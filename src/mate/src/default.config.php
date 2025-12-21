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
        ->set('mate.debug_log_file', '%env(default:dev.log:MATE_DEBUG_LOG_FILE)%')
        ->set('mate.debug_file_enabled', '%env(bool:default:false:MATE_DEBUG_FILE)%')
        ->set('mate.debug_enabled', '%env(bool:default:false:MATE_DEBUG)%')
    ;

    $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()

        ->set(LoggerInterface::class, Logger::class)
            ->arg('$logFile', '%mate.debug_log_file%')
            ->arg('$fileLogEnabled', '%mate.debug_file_enabled%')
            ->arg('$debugEnabled', '%mate.debug_enabled%')
            ->alias(Logger::class, LoggerInterface::class)
    ;
};
