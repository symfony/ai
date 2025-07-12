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

use Symfony\AI\Agent\StructuredOutput\AgentProcessor as StructureOutputProcessor;
use Symfony\AI\Agent\StructuredOutput\ResponseFormatFactory;
use Symfony\AI\Agent\StructuredOutput\ResponseFormatFactoryInterface;
use Symfony\AI\Agent\Toolbox\AgentProcessor as ToolProcessor;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolCallArgumentResolver;
use Symfony\AI\Agent\Toolbox\ToolFactory\ReflectionToolFactory;
use Symfony\AI\Agent\Toolbox\ToolFactoryInterface;
use Symfony\AI\Agent\Toolbox\ToolResultConverter;
use Symfony\AI\AIBundle\Profiler\DataCollector;
use Symfony\AI\AIBundle\Profiler\TraceableToolbox;
use Symfony\AI\Platform\Contract\JsonSchema\DescriptionParser;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;

return static function (ContainerConfigurator $container): void {
    $container->services()

        // structured output
        ->set('symfony_ai.structured_output.description_parser', DescriptionParser::class)
        ->set('symfony_ai.structured_output.schema_factory', Factory::class)
            ->args([
                service('symfony_ai.structured_output.description_parser'),
                service('type_info.resolver')->nullOnInvalid(),
            ])
        ->set('symfony_ai.structured_output.response_format_factory', ResponseFormatFactory::class)
            ->args([
                service('symfony_ai.structured_output.schema_factory'),
            ])
            ->alias(ResponseFormatFactoryInterface::class, 'symfony_ai.structured_output.response_format_factory')
        ->set('symfony_ai.structured_output.structured_output_processor', StructureOutputProcessor::class)
            ->tag('symfony_ai.agent.input_processor')
            ->tag('symfony_ai.agent.output_processor')

        // tools
        ->set('symfony_ai.toolbox.result_converter', ToolResultConverter::class)
            ->args([
                service('serializer')->ignoreOnInvalid(),
            ])
        ->set('symfony_ai.toolbox.argument_resolver', ToolCallArgumentResolver::class)
            ->args([
                service('serializer')->ignoreOnInvalid(),
            ])
        ->set('symfony_ai.toolbox.abstract', Toolbox::class)
            ->abstract()
            ->args([
                '$toolFactory' => service(ToolFactoryInterface::class),
                '$tools' => abstract_arg('Collection of tools'),
                '$argumentResolver' => service('symfony_ai.toolbox.argument_resolver'),
                '$logger' => service('logger')->ignoreOnInvalid(),
                '$eventDispatcher' => service('event_dispatcher')->nullOnInvalid(),
            ])
        ->set('symfony_ai.toolbox.default', Toolbox::class)
            ->parent('symfony_ai.toolbox.abstract')
            ->args([
                '$tools' => tagged_iterator('symfony_ai.tool'),
            ])
            ->alias(ToolboxInterface::class, 'symfony_ai.toolbox.default')
        ->set('symfony_ai.tool.reflection_tool_factory', ReflectionToolFactory::class)
            ->alias(ToolFactoryInterface::class, 'symfony_ai.tool.reflection_tool_factory')
        ->set('symfony_ai.tool.agent_processor.abstract', ToolProcessor::class)
            ->abstract()
            ->args([
                '$toolbox' => abstract_arg('Toolbox'),
                '$resultConverter' => service('symfony_ai.toolbox.result_converter'),
                '$eventDispatcher' => service('event_dispatcher')->nullOnInvalid(),
            ])
        ->set('symfony_ai.toolbox.agent_processor', ToolProcessor::class)
            ->parent('symfony_ai.tool.agent_processor.abstract')
            ->tag('symfony_ai.agent.input_processor')
            ->tag('symfony_ai.agent.output_processor')
            ->args([
                '$toolbox' => service(ToolboxInterface::class),
            ])

        // profiler
        ->set('debug.symfony_ai.data_collector', DataCollector::class)
            ->args([
                tagged_iterator('symfony_ai.traceable_platform'),
                service('symfony_ai.toolbox.default'),
                tagged_iterator('symfony_ai.traceable_toolbox'),
            ])
            ->tag('data_collector')
        ->set('debug.symfony_ai.toolbox.default', TraceableToolbox::class)
            ->decorate('symfony_ai.toolbox.default')
            ->args([
                service('.inner'),
            ])
            ->tag('symfony_ai.traceable_toolbox')
    ;
};
