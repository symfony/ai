<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AIBundle;

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
use Symfony\AI\AIBundle\Exception\InvalidArgumentException;
use Symfony\AI\AIBundle\Profiler\DataCollector;
use Symfony\AI\AIBundle\Profiler\TraceablePlatform;
use Symfony\AI\AIBundle\Profiler\TraceableToolbox;
use Symfony\AI\AIBundle\Security\Attribute\IsGrantedTool;
use Symfony\AI\Platform\Bridge\Anthropic\PlatformFactory as AnthropicPlatformFactory;
use Symfony\AI\Platform\Bridge\Azure\OpenAI\PlatformFactory as AzureOpenAIPlatformFactory;
use Symfony\AI\Platform\Bridge\Google\PlatformFactory as GooglePlatformFactory;
use Symfony\AI\Platform\Bridge\Mistral\PlatformFactory as MistralPlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAI\PlatformFactory as OpenAIPlatformFactory;
use Symfony\AI\Platform\Bridge\OpenRouter\PlatformFactory as OpenRouterPlatformFactory;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\ResponseConverterInterface;
use Symfony\AI\Store\Bridge\Azure\SearchStore as AzureSearchStore;
use Symfony\AI\Store\Bridge\ChromaDB\Store as ChromaDBStore;
use Symfony\AI\Store\Bridge\MongoDB\Store as MongoDBStore;
use Symfony\AI\Store\Bridge\Pinecone\Store as PineconeStore;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer;
use Symfony\AI\Store\StoreInterface;
use Symfony\AI\Store\VectorStoreInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

use function Symfony\Component\String\u;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AIBundle extends AbstractBundle
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

        foreach ($config['platform'] ?? [] as $type => $platform) {
            $this->processPlatformConfig($type, $platform, $builder);
        }
        $platforms = array_keys($builder->findTaggedServiceIds('ai.platform'));
        if (1 === \count($platforms)) {
            $builder->setAlias(PlatformInterface::class, reset($platforms));
        }
        if ($builder->getParameter('kernel.debug')) {
            foreach ($platforms as $platform) {
                $traceablePlatformDefinition = (new Definition(TraceablePlatform::class))
                    ->setDecoratedService($platform)
                    ->setAutowired(true)
                    ->addTag('ai.traceable_platform');
                $suffix = u($platform)->afterLast('.')->toString();
                $builder->setDefinition('ai.traceable_platform.'.$suffix, $traceablePlatformDefinition);
            }
        }

        foreach ($config['agent'] as $agentName => $agent) {
            $this->processAgentConfig($agentName, $agent, $builder);
        }
        if (1 === \count($config['agent']) && isset($agentName)) {
            $builder->setAlias(AgentInterface::class, 'ai.agent.'.$agentName);
        }

        foreach ($config['store'] ?? [] as $type => $store) {
            $this->processStoreConfig($type, $store, $builder);
        }
        $stores = array_keys($builder->findTaggedServiceIds('ai.store'));
        if (1 === \count($stores)) {
            $builder->setAlias(VectorStoreInterface::class, reset($stores));
            $builder->setAlias(StoreInterface::class, reset($stores));
        }

        foreach ($config['indexer'] as $indexerName => $indexer) {
            $this->processIndexerConfig($indexerName, $indexer, $builder);
        }
        if (1 === \count($config['indexer']) && isset($indexerName)) {
            $builder->setAlias(Indexer::class, 'ai.indexer.'.$indexerName);
        }

        $builder->registerAttributeForAutoconfiguration(AsTool::class, static function (ChildDefinition $definition, AsTool $attribute): void {
            $definition->addTag('ai.tool', [
                'name' => $attribute->name,
                'description' => $attribute->description,
                'method' => $attribute->method,
            ]);
        });

        $builder->registerForAutoconfiguration(InputProcessorInterface::class)
            ->addTag('ai.agent.input_processor');
        $builder->registerForAutoconfiguration(OutputProcessorInterface::class)
            ->addTag('ai.agent.output_processor');
        $builder->registerForAutoconfiguration(ModelClientInterface::class)
            ->addTag('ai.platform.model_client');
        $builder->registerForAutoconfiguration(ResponseConverterInterface::class)
            ->addTag('ai.platform.response_converter');

        if (!ContainerBuilder::willBeAvailable('symfony/security-core', AuthorizationCheckerInterface::class, ['symfony/ai-bundle'])) {
            $builder->removeDefinition('ai.security.is_granted_attribute_listener');
            $builder->registerAttributeForAutoconfiguration(
                IsGrantedTool::class,
                static fn () => throw new InvalidArgumentException('Using #[IsGrantedTool] attribute requires additional dependencies. Try running "composer install symfony/security-core".'),
            );
        }

        if (false === $builder->getParameter('kernel.debug')) {
            $builder->removeDefinition(DataCollector::class);
            $builder->removeDefinition(TraceableToolbox::class);
        }
    }

    /**
     * @param array<string, mixed> $platform
     */
    private function processPlatformConfig(string $type, array $platform, ContainerBuilder $container): void
    {
        if ('anthropic' === $type) {
            $platformId = 'ai.platform.anthropic';
            $definition = (new Definition(Platform::class))
                ->setFactory(AnthropicPlatformFactory::class.'::create')
                ->setAutowired(true)
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    '$apiKey' => $platform['api_key'],
                ])
                ->addTag('ai.platform');

            if (isset($platform['version'])) {
                $definition->replaceArgument('$version', $platform['version']);
            }

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('azure' === $type) {
            foreach ($platform as $name => $config) {
                $platformId = 'ai.platform.azure.'.$name;
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
                    ->addTag('ai.platform');

                $container->setDefinition($platformId, $definition);
            }

            return;
        }

        if ('google' === $type) {
            $platformId = 'ai.platform.google';
            $definition = (new Definition(Platform::class))
                ->setFactory(GooglePlatformFactory::class.'::create')
                ->setAutowired(true)
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments(['$apiKey' => $platform['api_key']])
                ->addTag('ai.platform');

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('openai' === $type) {
            $platformId = 'ai.platform.openai';
            $definition = (new Definition(Platform::class))
                ->setFactory(OpenAIPlatformFactory::class.'::create')
                ->setAutowired(true)
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments(['$apiKey' => $platform['api_key']])
                ->addTag('ai.platform');

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('openrouter' === $type) {
            $platformId = 'ai.platform.openrouter';
            $definition = (new Definition(Platform::class))
                ->setFactory(OpenRouterPlatformFactory::class.'::create')
                ->setAutowired(true)
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments(['$apiKey' => $platform['api_key']])
                ->addTag('ai.platform');

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('mistral' === $type) {
            $platformId = 'ai.platform.mistral';
            $definition = (new Definition(Platform::class))
                ->setFactory(MistralPlatformFactory::class.'::create')
                ->setAutowired(true)
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments(['$apiKey' => $platform['api_key']])
                ->addTag('ai.platform');

            $container->setDefinition($platformId, $definition);

            return;
        }

        throw new InvalidArgumentException(\sprintf('Platform "%s" is not supported for configuration via bundle at this point.', $type));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function processAgentConfig(string $name, array $config, ContainerBuilder $container): void
    {
        // MODEL
        ['class' => $modelClass, 'name' => $modelName, 'options' => $options] = $config['model'];

        if (!is_a($modelClass, Model::class, true)) {
            throw new InvalidArgumentException(\sprintf('"%s" class is not extending Symfony\AI\Platform\Model.', $modelClass));
        }

        $modelDefinition = new Definition($modelClass);
        if (null !== $modelName) {
            $modelDefinition->setArgument('$name', $modelName);
        }
        if ([] !== $options) {
            $modelDefinition->setArgument('$options', $options);
        }
        $modelDefinition->addTag('ai.model.language_model');
        $container->setDefinition('ai.agent.'.$name.'.model', $modelDefinition);

        // AGENT
        $agentDefinition = (new Definition(Agent::class))
            ->setAutowired(true)
            ->setArgument('$platform', new Reference($config['platform']))
            ->setArgument('$model', new Reference('ai.agent.'.$name.'.model'));

        $inputProcessors = [];
        $outputProcessors = [];

        // TOOL & PROCESSOR
        if ($config['tools']['enabled']) {
            // Create specific toolbox and process if tools are explicitly defined
            if ([] !== $config['tools']['services']) {
                $memoryFactoryDefinition = new Definition(MemoryToolFactory::class);
                $container->setDefinition('ai.toolbox.'.$name.'.memory_factory', $memoryFactoryDefinition);
                $chainFactoryDefinition = new Definition(ChainFactory::class, [
                    '$factories' => [new Reference('ai.toolbox.'.$name.'.memory_factory'), new Reference(ReflectionToolFactory::class)],
                ]);
                $container->setDefinition('ai.toolbox.'.$name.'.chain_factory', $chainFactoryDefinition);

                $tools = [];
                foreach ($config['tools']['services'] as $tool) {
                    $reference = new Reference($tool['service']);
                    // We use the memory factory in case method, description and name are set
                    if (isset($tool['name'], $tool['description'])) {
                        if ($tool['is_agent']) {
                            $agentWrapperDefinition = new Definition(AgentTool::class, ['$agent' => $reference]);
                            $container->setDefinition('ai.toolbox.'.$name.'.agent_wrapper.'.$tool['name'], $agentWrapperDefinition);
                            $reference = new Reference('ai.toolbox.'.$name.'.agent_wrapper.'.$tool['name']);
                        }
                        $memoryFactoryDefinition->addMethodCall('addTool', [$reference, $tool['name'], $tool['description'], $tool['method'] ?? '__invoke']);
                    }
                    $tools[] = $reference;
                }

                $toolboxDefinition = (new ChildDefinition('ai.toolbox.abstract'))
                    ->replaceArgument('$toolFactory', new Reference('ai.toolbox.'.$name.'.chain_factory'))
                    ->replaceArgument('$tools', $tools);
                $container->setDefinition('ai.toolbox.'.$name, $toolboxDefinition);

                if ($config['fault_tolerant_toolbox']) {
                    $faultTolerantToolboxDefinition = (new Definition('ai.fault_tolerant_toolbox.'.$name))
                        ->setClass(FaultTolerantToolbox::class)
                        ->setAutowired(true)
                        ->setDecoratedService('ai.toolbox.'.$name);
                    $container->setDefinition('ai.fault_tolerant_toolbox.'.$name, $faultTolerantToolboxDefinition);
                }

                if ($container->getParameter('kernel.debug')) {
                    $traceableToolboxDefinition = (new Definition('ai.traceable_toolbox.'.$name))
                        ->setClass(TraceableToolbox::class)
                        ->setAutowired(true)
                        ->setDecoratedService('ai.toolbox.'.$name)
                        ->addTag('ai.traceable_toolbox');
                    $container->setDefinition('ai.traceable_toolbox.'.$name, $traceableToolboxDefinition);
                }

                $toolProcessorDefinition = (new ChildDefinition('ai.tool.agent_processor.abstract'))
                    ->replaceArgument('$toolbox', new Reference('ai.toolbox.'.$name));
                $container->setDefinition('ai.tool.agent_processor.'.$name, $toolProcessorDefinition);

                $inputProcessors[] = new Reference('ai.tool.agent_processor.'.$name);
                $outputProcessors[] = new Reference('ai.tool.agent_processor.'.$name);
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
                    '$toolbox' => $config['include_tools'] ? new Reference('ai.toolbox.'.$name) : null,
                ]);

            $inputProcessors[] = $systemPromptInputProcessorDefinition;
        }

        $agentDefinition
            ->setArgument('$inputProcessors', $inputProcessors)
            ->setArgument('$outputProcessors', $outputProcessors);

        $container->setDefinition('ai.agent.'.$name, $agentDefinition);
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
                    ->addTag('ai.store')
                    ->setArguments($arguments);

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
            }
        }

        if ('chroma_db' === $type) {
            foreach ($stores as $name => $store) {
                $definition = new Definition(ChromaDBStore::class);
                $definition
                    ->setAutowired(true)
                    ->setArgument('$collectionName', $store['collection'])
                    ->addTag('ai.store');

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
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
                    ->addTag('ai.store')
                    ->setArguments($arguments);

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
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
                    ->addTag('ai.store')
                    ->setArguments($arguments);

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function processIndexerConfig(int|string $name, array $config, ContainerBuilder $container): void
    {
        ['class' => $modelClass, 'name' => $modelName, 'options' => $options] = $config['model'];

        if (!is_a($modelClass, Model::class, true)) {
            throw new InvalidArgumentException(\sprintf('"%s" class is not extending Symfony\AI\Platform\Model.', $modelClass));
        }

        $modelDefinition = (new Definition((string) $modelClass));
        if (null !== $modelName) {
            $modelDefinition->setArgument('$name', $modelName);
        }
        if ([] !== $options) {
            $modelDefinition->setArgument('$options', $options);
        }

        $modelDefinition->addTag('ai.model.embeddings_model');
        $container->setDefinition('ai.indexer.'.$name.'.model', $modelDefinition);

        $vectorizerDefinition = new Definition(Vectorizer::class, [
            '$platform' => new Reference($config['platform']),
            '$model' => new Reference('ai.indexer.'.$name.'.model'),
        ]);
        $container->setDefinition('ai.indexer.'.$name.'.vectorizer', $vectorizerDefinition);

        $definition = new Definition(Indexer::class, [
            '$vectorizer' => new Reference('ai.indexer.'.$name.'.vectorizer'),
            '$store' => new Reference($config['store']),
        ]);

        $container->setDefinition('ai.indexer.'.$name, $definition);
    }
}
