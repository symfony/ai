<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Psr\Container\ContainerInterface as PsrContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\AI\Mate\Agent\AgentInstructionsAggregator;
use Symfony\AI\Mate\Agent\AgentInstructionsMaterializer;
use Symfony\AI\Mate\Command\ClearCacheCommand;
use Symfony\AI\Mate\Command\DebugCapabilitiesCommand;
use Symfony\AI\Mate\Command\DebugExtensionsCommand;
use Symfony\AI\Mate\Command\DiscoverCommand;
use Symfony\AI\Mate\Command\InitCommand;
use Symfony\AI\Mate\Command\ResourcesReadCommand;
use Symfony\AI\Mate\Command\SkillsInstallCommand;
use Symfony\AI\Mate\Command\ToolsCallCommand;
use Symfony\AI\Mate\Command\ToolsInspectCommand;
use Symfony\AI\Mate\Command\ToolsListCommand;
use Symfony\AI\Mate\Discovery\CapabilityCollector;
use Symfony\AI\Mate\Discovery\CapabilityRegistry;
use Symfony\AI\Mate\Discovery\ComposerExtensionDiscovery;
use Symfony\AI\Mate\Discovery\DocBlockParser;
use Symfony\AI\Mate\Discovery\ReflectionDiscoverer;
use Symfony\AI\Mate\Discovery\SchemaGenerator;
use Symfony\AI\Mate\Invocation\ArgumentCaster;
use Symfony\AI\Mate\Invocation\ResourceReader;
use Symfony\AI\Mate\Invocation\ToolInvoker;
use Symfony\AI\Mate\Service\ExtensionConfigSynchronizer;
use Symfony\AI\Mate\Service\Logger;
use Symfony\AI\Mate\Service\SkillsInstaller;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;

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
        ->set('mate.skills_dir', '.agents/skills')
        ->set('mate.skill_mirrors', ['claude' => '.claude/skills'])
        ->set('mate.debug_log_file', $debugLogFile)
        ->set('mate.debug_file_enabled', $debugFileEnabled)
        ->set('mate.debug_enabled', $debugEnabled)
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
            ->bind('$skillsDir', '%mate.skills_dir%')
            ->bind('$skillMirrors', '%mate.skill_mirrors%')

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

        // Container services for handlers that need to be resolved at runtime
        ->alias(ContainerInterface::class, 'service_container')
        ->alias(PsrContainerInterface::class, 'service_container')

        ->set(ComposerExtensionDiscovery::class)

        // Native discovery pipeline (attribute + reflection based)
        ->set(DocBlockParser::class)
        ->set(SchemaGenerator::class)
        ->set(ReflectionDiscoverer::class)
        ->set(CapabilityRegistry::class)
        ->set(CapabilityCollector::class)

        // Tool/resource invocation
        ->set(ArgumentCaster::class)
        ->set(ToolInvoker::class)
        ->set(ResourceReader::class)

        ->set(AgentInstructionsAggregator::class)
        ->set(AgentInstructionsMaterializer::class)
        ->set(ExtensionConfigSynchronizer::class)

        ->set(Filesystem::class)
        ->set(SkillsInstaller::class)

        // Register all commands
        ->set(InitCommand::class)
            ->public()

        ->set(DiscoverCommand::class)
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

        ->set(ResourcesReadCommand::class)
            ->public()

        ->set(SkillsInstallCommand::class)
            ->public()
    ;
};
