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
use Symfony\AI\Agent\Toolbox\ToolFactory\AbstractToolFactory;
use Symfony\AI\Agent\Toolbox\ToolFactory\ReflectionToolFactory;
use Symfony\AI\Agent\Toolbox\ToolResultConverter;
use Symfony\AI\AiBundle\Profiler\DataCollector;
use Symfony\AI\AiBundle\Profiler\TraceableToolbox;
use Symfony\AI\AiBundle\Security\EventListener\IsGrantedToolAttributeListener;
use Symfony\AI\Platform\Bridge\Anthropic\Contract\AnthropicContract;
use Symfony\AI\Platform\Bridge\Gemini\Contract\GeminiContract;
use Symfony\AI\Platform\Bridge\Ollama\Contract\OllamaContract;
use Symfony\AI\Platform\Bridge\OpenAi\Contract\OpenAiContract;
use Symfony\AI\Platform\Bridge\VertexAi\Contract\GeminiContract as VertexAiGeminiContract;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Contract\JsonSchema\DescriptionParser;
use Symfony\AI\Platform\Contract\JsonSchema\Factory as SchemaFactory;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('ai.platform.contract.default', Contract::class)
            ->factory([Contract::class, 'create'])
        ->set('ai.platform.contract.openai', Contract::class)
            ->factory([OpenAiContract::class, 'create'])
        ->set('ai.platform.contract.anthropic', Contract::class)
            ->factory([AnthropicContract::class, 'create'])
        ->set('ai.platform.contract.google', Contract::class)
            ->factory([GeminiContract::class, 'create'])
        ->set('ai.platform.contract.vertexai.gemini', Contract::class)
            ->factory([VertexAiGeminiContract::class, 'create'])
        ->set('ai.platform.contract.ollama', Contract::class)
            ->factory([OllamaContract::class, 'create'])
        // structured output
        ->set('ai.agent.response_format_factory', ResponseFormatFactory::class)
            ->args([
                service('ai.platform.json_schema_factory'),
            ])
        ->set('ai.platform.json_schema.description_parser', DescriptionParser::class)
        ->set('ai.platform.json_schema_factory', SchemaFactory::class)
            ->args([
                service('ai.platform.json_schema.description_parser'),
                service('type_info.resolver')->nullOnInvalid(),
            ])
        ->alias(ResponseFormatFactoryInterface::class, 'ai.agent.response_format_factory')
        ->set('ai.agent.structured_output_processor', StructureOutputProcessor::class)
            ->args([
                service('ai.agent.response_format_factory'),
                service('serializer'),
            ])
            ->tag('ai.agent.input_processor')
            ->tag('ai.agent.output_processor')

        // tools
        ->set('ai.toolbox.abstract', Toolbox::class)
            ->abstract()
            ->args([
                abstract_arg('Collection of tools'),
                service('ai.tool_factory'),
                service('ai.tool_call_argument_resolver'),
                service('logger')->ignoreOnInvalid(),
                service('event_dispatcher')->nullOnInvalid(),
            ])
        ->set('ai.toolbox', Toolbox::class)
            ->parent('ai.toolbox.abstract')
            ->arg('index_0', tagged_iterator('ai.tool'))
        ->alias(ToolboxInterface::class, 'ai.toolbox')
        ->set('ai.tool_factory.abstract', AbstractToolFactory::class)
            ->abstract()
            ->args([
                service('ai.platform.json_schema_factory'),
            ])
        ->set('ai.tool_factory', ReflectionToolFactory::class)
            ->parent('ai.tool_factory.abstract')
        ->set('ai.tool_result_converter', ToolResultConverter::class)
            ->args([
                service('serializer'),
            ])
        ->set('ai.tool_call_argument_resolver', ToolCallArgumentResolver::class)
            ->args([
                service('serializer'),
                service('type_info.resolver')->nullOnInvalid(),
            ])
        ->set('ai.tool.agent_processor.abstract', ToolProcessor::class)
            ->abstract()
            ->args([
                abstract_arg('Toolbox'),
                service('ai.tool_result_converter'),
                service('event_dispatcher')->nullOnInvalid(),
                false,
            ])
        ->set('ai.tool.agent_processor', ToolProcessor::class)
            ->parent('ai.tool.agent_processor.abstract')
            ->tag('ai.agent.input_processor')
            ->tag('ai.agent.output_processor')
            ->arg('index_0', service('ai.toolbox'))
        ->set('ai.security.is_granted_attribute_listener', IsGrantedToolAttributeListener::class)
            ->args([
                service('security.authorization_checker'),
                service('expression_language')->nullOnInvalid(),
            ])
            ->tag('kernel.event_listener')

        // profiler
        ->set('ai.data_collector', DataCollector::class)
            ->args([
                tagged_iterator('ai.traceable_platform'),
                service('ai.toolbox'),
                tagged_iterator('ai.traceable_toolbox'),
            ])
            ->tag('data_collector')
        ->set('ai.traceable_toolbox', TraceableToolbox::class)
            ->decorate('ai.toolbox', priority: -1)
            ->args([
                service('.inner'),
            ])
            ->tag('ai.traceable_toolbox')
    ;
};
