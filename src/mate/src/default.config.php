<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Mcp\Capability\Discovery\Discoverer;
use Mcp\Capability\Discovery\DiscovererInterface;
use Psr\Log\LoggerInterface;
use Symfony\AI\Mate\Agent\AgentInstructionsAggregator;
use Symfony\AI\Mate\Command\ClearCacheCommand;
use Symfony\AI\Mate\Command\DebugCapabilitiesCommand;
use Symfony\AI\Mate\Command\DebugExtensionsCommand;
use Symfony\AI\Mate\Command\DiscoverCommand;
use Symfony\AI\Mate\Command\InitCommand;
use Symfony\AI\Mate\Command\ServeCommand;
use Symfony\AI\Mate\Command\StopCommand;
use Symfony\AI\Mate\Command\ToolsCallCommand;
use Symfony\AI\Mate\Command\ToolsInspectCommand;
use Symfony\AI\Mate\Command\ToolsListCommand;
use Symfony\AI\Mate\Discovery\CapabilityCollector;
use Symfony\AI\Mate\Discovery\ComposerExtensionDiscovery;
use Symfony\AI\Mate\Discovery\FilteredDiscoveryLoader;
use Symfony\AI\Mate\Service\Logger;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
            // Named argument binding for common parameters
            ->bind('$rootDir', '%mate.root_dir%')
            ->bind('$cacheDir', '%mate.cache_dir%')
            ->bind('$extensions', '%mate.extensions%')
            ->bind('$disabledFeatures', '%mate.disabled_features%')
            ->bind('$enabledExtensions', '%mate.enabled_extensions%')
            ->bind('$mcpProtocolVersion', '%mate.mcp_protocol_version%')

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

        // Container service for commands that need it
        ->alias(ContainerInterface::class, 'service_container')

        // Register discovery services
        ->set(Discoverer::class)
            ->alias(DiscovererInterface::class, Discoverer::class)

        ->set(ComposerExtensionDiscovery::class)

        ->set(FilteredDiscoveryLoader::class)

        ->set(CapabilityCollector::class)

        ->set(AgentInstructionsAggregator::class)

        // Register all commands
        ->set(InitCommand::class)
            ->public()

        ->set(ServeCommand::class)
            ->public()

        ->set(DiscoverCommand::class)
            ->public()

        ->set(StopCommand::class)
            ->public()

        ->set(DebugCapabilitiesCommand::class)
            ->public()

        ->set(DebugExtensionsCommand::class)
            ->public()

        ->set(ClearCacheCommand::class)
            ->public()

        ->set(ToolsListCommand::class)
            ->public()

        ->set(ToolsInspectCommand::class)
            ->public()

        ->set(ToolsCallCommand::class)
            ->public()
    ;
};
