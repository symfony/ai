<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Mate\Bridge\Profiler\Capability\ProfilerResourceTemplate;
use Symfony\AI\Mate\Bridge\Profiler\Capability\ProfilerTool;
use Symfony\AI\Mate\Bridge\Profiler\Service\CollectorRegistry;
use Symfony\AI\Mate\Bridge\Profiler\Service\Formatter\ExceptionCollectorFormatter;
use Symfony\AI\Mate\Bridge\Profiler\Service\Formatter\RequestCollectorFormatter;
use Symfony\AI\Mate\Bridge\Profiler\Service\ProfilerDataProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Profiler\FileProfilerStorage;
use Symfony\Component\HttpKernel\Profiler\Profile;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return function (ContainerConfigurator $configurator) {
    // Parameters - defaults to Symfony's standard dev environment profiler directory
    $configurator->parameters()
        ->set('ai_mate_profiler.profiler_dir', '%mate.root_dir%/var/cache/dev/profiler');

    $services = $configurator->services();

    // Core services
    $services->set(CollectorRegistry::class)
        ->args([tagged_iterator('ai_mate.profiler_collector_formatter')]);

    $services->set(ProfilerDataProvider::class)
        ->args([
            '%ai_mate_profiler.profiler_dir%',
            service(CollectorRegistry::class),
        ]);

    // Built-in collector formatters (core only)
    $services->set(RequestCollectorFormatter::class)
        ->tag('ai_mate.profiler_collector_formatter');

    $services->set(ExceptionCollectorFormatter::class)
        ->tag('ai_mate.profiler_collector_formatter');

    // MCP Capabilities
    $services->set(ProfilerTool::class)
        ->args([service(ProfilerDataProvider::class)]);

    $services->set(ProfilerResourceTemplate::class)
        ->args([service(ProfilerDataProvider::class)]);
};
