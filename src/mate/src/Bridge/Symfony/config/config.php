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
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return function (ContainerConfigurator $configurator) {
    // Parameters
    $configurator->parameters()
        ->set('ai_mate_symfony.cache_dir', '%mate.root_dir%/var/cache');

    $services = $configurator->services();

    // Container introspection services (always available)
    $services->set(ContainerProvider::class);

    $services->set(ServiceTool::class)
        ->args([
            '%ai_mate_symfony.cache_dir%',
            service(ContainerProvider::class),
        ]);

    // Profiler services (optional - only if profiler classes are available)
    if (class_exists(Symfony\Component\HttpKernel\Profiler\Profile::class)) {
        $configurator->parameters()
            ->set('ai_mate_symfony.profiler_dir', '%mate.root_dir%/var/cache/dev/profiler');

        $services->set(Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\CollectorRegistry::class)
            ->args([tagged_iterator('ai_mate.profiler_collector_formatter')]);

        $services->set(Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\ProfilerDataProvider::class)
            ->args([
                '%ai_mate_symfony.profiler_dir%',
                service(Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\CollectorRegistry::class),
            ]);

        // Built-in collector formatters
        $services->set(Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\RequestCollectorFormatter::class)
            ->tag('ai_mate.profiler_collector_formatter');

        $services->set(Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\ExceptionCollectorFormatter::class)
            ->tag('ai_mate.profiler_collector_formatter');

        // MCP Capabilities
        $services->set(Symfony\AI\Mate\Bridge\Symfony\Capability\ProfilerTool::class)
            ->args([service(Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\ProfilerDataProvider::class)]);

        $services->set(Symfony\AI\Mate\Bridge\Symfony\Capability\ProfilerResourceTemplate::class)
            ->args([service(Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\ProfilerDataProvider::class)]);
    }
};
