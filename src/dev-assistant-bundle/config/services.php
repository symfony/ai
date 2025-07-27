<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Psr\Log\LoggerInterface;
use Symfony\AI\DevAssistantBundle\Analyzer\HybridCodeQualityAnalyzer;
use Symfony\AI\DevAssistantBundle\Command\TestProvidersCommand;
use Symfony\AI\DevAssistantBundle\Service\AIProviderTester;
use Symfony\AI\ToolBox\ToolboxRunner;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()

        // Core AI services
        ->set(ToolboxRunner::class)
            ->public()

        // Provider testing service
        ->set(AIProviderTester::class)
            ->args([
                service(ToolboxRunner::class),
                service(LoggerInterface::class),
            ])

        // Hybrid analyzer with AI and static analysis
        ->set(HybridCodeQualityAnalyzer::class)
            ->args([
                service(ToolboxRunner::class),
                service(LoggerInterface::class),
            ])
            ->tag('dev_assistant.analyzer')

        // Commands
        ->set(TestProvidersCommand::class)
            ->args([
                service(AIProviderTester::class),
            ])
            ->tag('console.command')
    ;
};
