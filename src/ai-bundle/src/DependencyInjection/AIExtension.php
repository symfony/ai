<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AIBundle\DependencyInjection;

use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\InputProcessor\SystemPromptInputProcessor;
use Symfony\AI\Agent\InputProcessorInterface;
use Symfony\AI\Agent\OutputProcessorInterface;
use Symfony\AI\Agent\StructuredOutput\AgentProcessor as StructureOutputProcessor;
use Symfony\AI\Agent\Toolbox\AgentProcessor as ToolProcessor;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\FaultTolerantToolbox;
use Symfony\AI\Agent\Toolbox\Tool\Agent as AgentTool;
use Symfony\AI\Agent\Toolbox\ToolFactory\ChainFactory;
use Symfony\AI\Agent\Toolbox\ToolFactory\MemoryToolFactory;
use Symfony\AI\Agent\Toolbox\ToolFactory\ReflectionToolFactory;
use Symfony\AI\AIBundle\Profiler\DataCollector;
use Symfony\AI\AIBundle\Profiler\TraceablePlatform;
use Symfony\AI\AIBundle\Profiler\TraceableToolbox;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Anthropic\PlatformFactory as AnthropicPlatformFactory;
use Symfony\AI\Platform\Bridge\Azure\OpenAI\PlatformFactory as AzureOpenAIPlatformFactory;
use Symfony\AI\Platform\Bridge\Google\Gemini;
use Symfony\AI\Platform\Bridge\Google\PlatformFactory as GooglePlatformFactory;
use Symfony\AI\Platform\Bridge\Meta\Llama;
use Symfony\AI\Platform\Bridge\OpenAI\Embeddings;
use Symfony\AI\Platform\Bridge\OpenAI\GPT;
use Symfony\AI\Platform\Bridge\OpenAI\PlatformFactory as OpenAIPlatformFactory;
use Symfony\AI\Platform\Bridge\Voyage\Voyage;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\ResponseConverterInterface;
use Symfony\AI\Store\Bridge\Azure\SearchStore as AzureSearchStore;
use Symfony\AI\Store\Bridge\ChromaDB\Store as ChromaDBStore;
use Symfony\AI\Store\Bridge\MongoDB\Store as MongoDBStore;
use Symfony\AI\Store\Bridge\Pinecone\Store as PineconeStore;
use Symfony\AI\Store\Embedder;
use Symfony\AI\Store\StoreInterface;
use Symfony\AI\Store\VectorStoreInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

use function Symfony\Component\String\u;

final class AIExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/Resources/config'));
        $loader->load('services.php');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        foreach ($config['platform'] ?? [] as $type => $platform) {
            $this->processPlatformConfig($type, $platform, $container);
        }
        $platforms = array_keys($container->findTaggedServiceIds('symfony_ai.platform'));
        if (1 === \count($platforms)) {
            $container->setAlias(PlatformInterface::class, reset($platforms));
        }
        if ($container->getParameter('kernel.debug')) {
            foreach ($platforms as $platform) {
                $traceablePlatformDefinition = (new Definition(TraceablePlatform::class))
                    ->setDecoratedService($platform)
                    ->setAutowired(true)
                    ->addTag('symfony_ai.traceable_platform');
                $suffix = u($platform)->afterLast('.')->toString();
                $container->setDefinition('symfony_ai.traceable_platform.'.$suffix, $traceablePlatformDefinition);
            }
        }

        foreach ($config['agent'] as $agentName => $agent) {
            $this->processAgentConfig($agentName, $agent, $container);
        }
        if (1 === \count($config['agent']) && isset($agentName)) {
            $container->setAlias(AgentInterface::class, 'symfony_ai.agent.'.$agentName);
        }

        foreach ($config['store'] ?? [] as $type => $store) {
            $this->processStoreConfig($type, $store, $container);
        }
        $stores = array_keys($container->findTaggedServiceIds('symfony_ai.store'));
        if (1 === \count($stores)) {
            $container->setAlias(VectorStoreInterface::class, reset($stores));
            $container->setAlias(StoreInterface::class, reset($stores));
        }

        foreach ($config['embedder'] as $embedderName => $embedder) {
            $this->processEmbedderConfig($embedderName, $embedder, $container);
        }
        if (1 === \count($config['embedder']) && isset($embedderName)) {
            $container->setAlias(Embedder::class, 'symfony_ai.embedder.'.$embedderName);
        }

        $container->registerAttributeForAutoconfiguration(AsTool::class, static function (ChildDefinition $definition, AsTool $attribute): void {
            $definition->addTag('symfony_ai.tool', [
                'name' => $attribute->name,
                'description' => $attribute->description,
                'method' => $attribute->method,
            ]);
        });

        $container->registerForAutoconfiguration(InputProcessorInterface::class)
            ->addTag('symfony_ai.agent.input_processor');
        $container->registerForAutoconfiguration(OutputProcessorInterface::class)
            ->addTag('symfony_ai.agent.output_processor');
        $container->registerForAutoconfiguration(ModelClientInterface::class)
            ->addTag('symfony_ai.platform.model_client');
        $container->registerForAutoconfiguration(ResponseConverterInterface::class)
            ->addTag('symfony_ai.platform.response_converter');

        if (false === $container->getParameter('kernel.debug')) {
            $container->removeDefinition(DataCollector::class);
            $container->removeDefinition(TraceableToolbox::class);
        }
    }

    /**
     * @param array<string, mixed> $platform
     */
    private function processPlatformConfig(string $type, array $platform, ContainerBuilder $container): void
    {
        if ('anthropic' === $type) {
            $platformId = 'symfony_ai.platform.anthropic';
            $definition = (new Definition(Platform::class))
                ->setFactory(AnthropicPlatformFactory::class.'::create')
                ->setAutowired(true)
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    '$apiKey' => $platform['api_key'],
                ])
                ->addTag('symfony_ai.platform');

            if (isset($platform['version'])) {
                $definition->replaceArgument('$version', $platform['version']);
            }

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('azure' === $type) {
            foreach ($platform as $name => $config) {
                $platformId = 'symfony_ai.platform.azure.'.$name;
                $definition = (new Definition(Platform::class))
                    ->setFactory(AzureOpenAIPlatformFactory::class.'::create')
                    ->setAutowired(true)
                    ->setLazy(true)
                    ->addTag('proxy', ['interface' => PlatformInterface::class])
                    ->setArguments([
                        '$baseUrl' => $config['base_url'],
                        '$deployment' => $config['deployment'],
                        '$apiVersion' => $config['api_version'],
                        '$apiKey' => $config['api_key'],
                    ])
                    ->addTag('symfony_ai.platform');

                $container->setDefinition($platformId, $definition);
            }

            return;
        }

        if ('google' === $type) {
            $platformId = 'symfony_ai.platform.google';
            $definition = (new Definition(Platform::class))
                ->setFactory(GooglePlatformFactory::class.'::create')
                ->setAutowired(true)
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments(['$apiKey' => $platform['api_key']])
                ->addTag('symfony_ai.platform');

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('openai' === $type) {
            $platformId = 'symfony_ai.platform.openai';
            $definition = (new Definition(Platform::class))
                ->setFactory(OpenAIPlatformFactory::class.'::create')
                ->setAutowired(true)
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments(['$apiKey' => $platform['api_key']])
                ->addTag('symfony_ai.platform');

            $container->setDefinition($platformId, $definition);

            return;
        }

        throw new \InvalidArgumentException(\sprintf('Platform "%s" is not supported for configuration via bundle at this point.', $type));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function processAgentConfig(string $name, array $config, ContainerBuilder $container): void
    {
        // MODEL
        ['name' => $modelName, 'version' => $version, 'options' => $options] = $config['model'];

        $modelClass = match (strtolower((string) $modelName)) {
            'gpt' => GPT::class,
            'claude' => Claude::class,
            'llama' => Llama::class,
            'gemini' => Gemini::class,
            default => throw new \InvalidArgumentException(\sprintf('Model "%s" is not supported.', $modelName)),
        };
        $modelDefinition = new Definition($modelClass);
        if (null !== $version) {
            $modelDefinition->setArgument('$name', $version);
        }
        if (0 !== \count($options)) {
            $modelDefinition->setArgument('$options', $options);
        }
        $modelDefinition->addTag('symfony_ai.model.language_model');
        $container->setDefinition('symfony_ai.agent.'.$name.'.model', $modelDefinition);

        // AGENT
        $agentDefinition = (new Definition(Agent::class))
            ->setAutowired(true)
            ->setArgument('$platform', new Reference($config['platform']))
            ->setArgument('$model', new Reference('symfony_ai.agent.'.$name.'.model'));

        $inputProcessors = [];
        $outputProcessors = [];

        // TOOL & PROCESSOR
        if ($config['tools']['enabled']) {
            // Create specific toolbox and process if tools are explicitly defined
            if (0 !== \count($config['tools']['services'])) {
                $memoryFactoryDefinition = new Definition(MemoryToolFactory::class);
                $container->setDefinition('symfony_ai.toolbox.'.$name.'.memory_factory', $memoryFactoryDefinition);
                $agentFactoryDefinition = new Definition(ChainFactory::class, [
                    '$factories' => [new Reference('symfony_ai.toolbox.'.$name.'.memory_factory'), new Reference(ReflectionToolFactory::class)],
                ]);
                $container->setDefinition('symfony_ai.toolbox.'.$name.'.agent_factory', $agentFactoryDefinition);

                $tools = [];
                foreach ($config['tools']['services'] as $tool) {
                    $reference = new Reference($tool['service']);
                    // We use the memory factory in case method, description and name are set
                    if (isset($tool['name'], $tool['description'])) {
                        if ($tool['is_agent']) {
                            $agentWrapperDefinition = new Definition(AgentTool::class, ['$agent' => $reference]);
                            $container->setDefinition('symfony_ai.toolbox.'.$name.'.agent_wrapper.'.$tool['name'], $agentWrapperDefinition);
                            $reference = new Reference('symfony_ai.toolbox.'.$name.'.agent_wrapper.'.$tool['name']);
                        }
                        $memoryFactoryDefinition->addMethodCall('addTool', [$reference, $tool['name'], $tool['description'], $tool['method'] ?? '__invoke']);
                    }
                    $tools[] = $reference;
                }

                $toolboxDefinition = (new ChildDefinition('symfony_ai.toolbox.abstract'))
                    ->replaceArgument('$toolFactory', new Reference('symfony_ai.toolbox.'.$name.'.agent_factory'))
                    ->replaceArgument('$tools', $tools);
                $container->setDefinition('symfony_ai.toolbox.'.$name, $toolboxDefinition);

                if ($config['fault_tolerant_toolbox']) {
                    $faultTolerantToolboxDefinition = (new Definition('symfony_ai.fault_tolerant_toolbox.'.$name))
                        ->setClass(FaultTolerantToolbox::class)
                        ->setAutowired(true)
                        ->setDecoratedService('symfony_ai.toolbox.'.$name);
                    $container->setDefinition('symfony_ai.fault_tolerant_toolbox.'.$name, $faultTolerantToolboxDefinition);
                }

                if ($container->getParameter('kernel.debug')) {
                    $traceableToolboxDefinition = (new Definition('symfony_ai.traceable_toolbox.'.$name))
                        ->setClass(TraceableToolbox::class)
                        ->setAutowired(true)
                        ->setDecoratedService('symfony_ai.toolbox.'.$name)
                        ->addTag('symfony_ai.traceable_toolbox');
                    $container->setDefinition('symfony_ai.traceable_toolbox.'.$name, $traceableToolboxDefinition);
                }

                $toolProcessorDefinition = (new ChildDefinition('symfony_ai.tool.agent_processor.abstract'))
                    ->replaceArgument('$toolbox', new Reference('symfony_ai.toolbox.'.$name));
                $container->setDefinition('symfony_ai.tool.agent_processor.'.$name, $toolProcessorDefinition);

                $inputProcessors[] = new Reference('symfony_ai.tool.agent_processor.'.$name);
                $outputProcessors[] = new Reference('symfony_ai.tool.agent_processor.'.$name);
            } else {
                $inputProcessors[] = new Reference(ToolProcessor::class);
                $outputProcessors[] = new Reference(ToolProcessor::class);
            }
        }

        // STRUCTURED OUTPUT
        if ($config['structured_output']) {
            $inputProcessors[] = new Reference(StructureOutputProcessor::class);
            $outputProcessors[] = new Reference(StructureOutputProcessor::class);
        }

        // SYSTEM PROMPT
        if (\is_string($config['system_prompt'])) {
            $systemPromptInputProcessorDefinition = new Definition(SystemPromptInputProcessor::class);
            $systemPromptInputProcessorDefinition
                ->setAutowired(true)
                ->setArguments([
                    '$systemPrompt' => $config['system_prompt'],
                    '$toolbox' => $config['include_tools'] ? new Reference('symfony_ai.toolbox.'.$name) : null,
                ]);

            $inputProcessors[] = $systemPromptInputProcessorDefinition;
        }

        $agentDefinition
            ->setArgument('$inputProcessors', $inputProcessors)
            ->setArgument('$outputProcessors', $outputProcessors);

        $container->setDefinition('symfony_ai.agent.'.$name, $agentDefinition);
    }

    /**
     * @param array<string, mixed> $stores
     */
    private function processStoreConfig(string $type, array $stores, ContainerBuilder $container): void
    {
        if ('azure_search' === $type) {
            foreach ($stores as $name => $store) {
                $arguments = [
                    '$endpointUrl' => $store['endpoint'],
                    '$apiKey' => $store['api_key'],
                    '$indexName' => $store['index_name'],
                    '$apiVersion' => $store['api_version'],
                ];

                if (\array_key_exists('vector_field', $store)) {
                    $arguments['$vectorFieldName'] = $store['vector_field'];
                }

                $definition = new Definition(AzureSearchStore::class);
                $definition
                    ->setAutowired(true)
                    ->addTag('symfony_ai.store')
                    ->setArguments($arguments);

                $container->setDefinition('symfony_ai.store.'.$type.'.'.$name, $definition);
            }
        }

        if ('chroma_db' === $type) {
            foreach ($stores as $name => $store) {
                $definition = new Definition(ChromaDBStore::class);
                $definition
                    ->setAutowired(true)
                    ->setArgument('$collectionName', $store['collection'])
                    ->addTag('symfony_ai.store');

                $container->setDefinition('symfony_ai.store.'.$type.'.'.$name, $definition);
            }
        }

        if ('mongodb' === $type) {
            foreach ($stores as $name => $store) {
                $arguments = [
                    '$databaseName' => $store['database'],
                    '$collectionName' => $store['collection'],
                    '$indexName' => $store['index_name'],
                ];

                if (\array_key_exists('vector_field', $store)) {
                    $arguments['$vectorFieldName'] = $store['vector_field'];
                }

                if (\array_key_exists('bulk_write', $store)) {
                    $arguments['$bulkWrite'] = $store['bulk_write'];
                }

                $definition = new Definition(MongoDBStore::class);
                $definition
                    ->setAutowired(true)
                    ->addTag('symfony_ai.store')
                    ->setArguments($arguments);

                $container->setDefinition('symfony_ai.store.'.$type.'.'.$name, $definition);
            }
        }

        if ('pinecone' === $type) {
            foreach ($stores as $name => $store) {
                $arguments = [
                    '$namespace' => $store['namespace'],
                ];

                if (\array_key_exists('filter', $store)) {
                    $arguments['$filter'] = $store['filter'];
                }

                if (\array_key_exists('top_k', $store)) {
                    $arguments['$topK'] = $store['top_k'];
                }

                $definition = new Definition(PineconeStore::class);
                $definition
                    ->setAutowired(true)
                    ->addTag('symfony_ai.store')
                    ->setArguments($arguments);

                $container->setDefinition('symfony_ai.store.'.$type.'.'.$name, $definition);
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function processEmbedderConfig(int|string $name, array $config, ContainerBuilder $container): void
    {
        ['name' => $modelName, 'version' => $version, 'options' => $options] = $config['model'];

        $modelClass = match (strtolower((string) $modelName)) {
            'embeddings' => Embeddings::class,
            'voyage' => Voyage::class,
            default => throw new \InvalidArgumentException(\sprintf('Model "%s" is not supported.', $modelName)),
        };
        $modelDefinition = (new Definition($modelClass));
        if (null !== $version) {
            $modelDefinition->setArgument('$name', $version);
        }
        if (0 !== \count($options)) {
            $modelDefinition->setArgument('$options', $options);
        }
        $modelDefinition->addTag('symfony_ai.model.embeddings_model');
        $container->setDefinition('symfony_ai.embedder.'.$name.'.model', $modelDefinition);

        $definition = new Definition(Embedder::class, [
            '$model' => new Reference('symfony_ai.embedder.'.$name.'.model'),
            '$platform' => new Reference($config['platform']),
            '$store' => new Reference($config['store']),
        ]);

        $container->setDefinition('symfony_ai.embedder.'.$name, $definition);
    }
}
