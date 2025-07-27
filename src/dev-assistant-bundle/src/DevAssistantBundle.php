<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\DevAssistantBundle;

use Symfony\AI\DevAssistantBundle\Analyzer\HybridCodeQualityAnalyzer;
use Symfony\AI\DevAssistantBundle\Command\TestProvidersCommand;
use Symfony\AI\DevAssistantBundle\Contract\AnalyzerInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * AI Development Assistant Bundle for intelligent code analysis.
 *
 * This bundle provides AI-powered code analysis with graceful fallbacks,
 * supporting multiple AI providers and comprehensive static analysis integration.
 *
 * @author Aria Vahidi <aria.vahidi2020@gmail.com>
 */
final class DevAssistantBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->import('../config/options.php');
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        // Register analyzers for autoconfiguration
        $builder->registerForAutoconfiguration(AnalyzerInterface::class)
            ->addTag('dev_assistant.analyzer');

        // Register hybrid analyzer
        $builder->registerForAutoconfiguration(HybridCodeQualityAnalyzer::class)
            ->addTag('dev_assistant.analyzer');

        // Register commands
        $builder->registerForAutoconfiguration(TestProvidersCommand::class)
            ->addTag('console.command');

        // Configure bundle parameters
        $builder->setParameter('dev_assistant.enabled', $config['enabled'] ?? true);
        $builder->setParameter('dev_assistant.cache_enabled', $config['cache']['enabled'] ?? true);
        $builder->setParameter('dev_assistant.cache_ttl', $config['cache']['ttl'] ?? 3600);

        if (isset($config['ai']['providers'])) {
            $builder->setParameter('dev_assistant.ai_providers', $config['ai']['providers']);
        }
    }
}
