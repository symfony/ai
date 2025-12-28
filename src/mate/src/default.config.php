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
    $debugLogFile = $_SERVER['MATE_DEBUG_LOG_FILE'] ?? 'dev.log';
    $debugFileEnabled = isset($_SERVER['MATE_DEBUG_FILE'])
        ? filter_var($_SERVER['MATE_DEBUG_FILE'], \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE)
        : false;
    $debugEnabled = isset($_SERVER['MATE_DEBUG'])
        ? filter_var($_SERVER['MATE_DEBUG'], \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE)
        : false;

    $container->parameters()
        ->set('mate.cache_dir', sys_get_temp_dir().'/mate')
        ->set('mate.env_file', null)
        ->set('mate.disabled_features', [])
        ->set('mate.debug_log_file', $debugLogFile)
        ->set('mate.debug_file_enabled', $debugFileEnabled)
        ->set('mate.debug_enabled', $debugEnabled)
        ->set('mate.mcp_protocol_version', '2025-03-26')
    ;

    $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()

        ->set('_build.logger', Logger::class)
            ->private() // To be removed when we compile
            ->arg('$logFile', $debugLogFile)
            ->arg('$fileLogEnabled', $debugFileEnabled)
            ->arg('$debugEnabled', $debugEnabled)

        ->set(LoggerInterface::class, Logger::class)
            ->public()
            ->arg('$logFile', '%mate.root_dir%/%mate.debug_log_file%')
            ->arg('$fileLogEnabled', '%mate.debug_file_enabled%')
            ->arg('$debugEnabled', '%mate.debug_enabled%')
            ->alias(Logger::class, LoggerInterface::class)
    ;
};
