<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle;

use Google\Auth\ApplicationDefaultCredentials;
use Google\Auth\FetchAuthTokenInterface;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Attribute\AsInputProcessor;
use Symfony\AI\Agent\Attribute\AsOutputProcessor;
use Symfony\AI\Agent\InputProcessor\SystemPromptInputProcessor;
use Symfony\AI\Agent\InputProcessorInterface;
use Symfony\AI\Agent\OutputProcessorInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\FaultTolerantToolbox;
use Symfony\AI\Agent\Toolbox\Tool\Agent as AgentTool;
use Symfony\AI\Agent\Toolbox\ToolFactory\ChainFactory;
use Symfony\AI\Agent\Toolbox\ToolFactory\MemoryToolFactory;
use Symfony\AI\AiBundle\DependencyInjection\ProcessorCompilerPass;
use Symfony\AI\AiBundle\Exception\InvalidArgumentException;
use Symfony\AI\AiBundle\Profiler\TraceablePlatform;
use Symfony\AI\AiBundle\Profiler\TraceableToolbox;
use Symfony\AI\AiBundle\Security\Attribute\IsGrantedTool;
use Symfony\AI\Platform\Bridge\Anthropic\PlatformFactory as AnthropicPlatformFactory;
use Symfony\AI\Platform\Bridge\Azure\OpenAi\PlatformFactory as AzureOpenAiPlatformFactory;
use Symfony\AI\Platform\Bridge\Cerebras\PlatformFactory as CerebrasPlatformFactory;
use Symfony\AI\Platform\Bridge\ElevenLabs\PlatformFactory as ElevenLabsPlatformFactory;
use Symfony\AI\Platform\Bridge\Gemini\PlatformFactory as GeminiPlatformFactory;
use Symfony\AI\Platform\Bridge\LmStudio\PlatformFactory as LmStudioPlatformFactory;
use Symfony\AI\Platform\Bridge\Mistral\PlatformFactory as MistralPlatformFactory;
use Symfony\AI\Platform\Bridge\Ollama\PlatformFactory as OllamaPlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory as OpenAiPlatformFactory;
use Symfony\AI\Platform\Bridge\OpenRouter\PlatformFactory as OpenRouterPlatformFactory;
use Symfony\AI\Platform\Bridge\Perplexity\PlatformFactory as PerplexityPlatformFactory;
use Symfony\AI\Platform\Bridge\VertexAi\PlatformFactory as VertexAiPlatformFactory;
use Symfony\AI\Platform\Bridge\Voyage\PlatformFactory as VoyagePlatformFactory;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Store\Bridge\Azure\SearchStore as AzureSearchStore;
use Symfony\AI\Store\Bridge\ChromaDb\Store as ChromaDbStore;
use Symfony\AI\Store\Bridge\ClickHouse\Store as ClickHouseStore;
use Symfony\AI\Store\Bridge\Cloudflare\Store as CloudflareStore;
use Symfony\AI\Store\Bridge\Local\CacheStore;
use Symfony\AI\Store\Bridge\Local\DistanceCalculator;
use Symfony\AI\Store\Bridge\Local\DistanceStrategy;
use Symfony\AI\Store\Bridge\Local\InMemoryStore;
use Symfony\AI\Store\Bridge\Meilisearch\Store as MeilisearchStore;
use Symfony\AI\Store\Bridge\Milvus\Store as MilvusStore;
use Symfony\AI\Store\Bridge\MongoDb\Store as MongoDbStore;
use Symfony\AI\Store\Bridge\Neo4j\Store as Neo4jStore;
use Symfony\AI\Store\Bridge\Pinecone\Store as PineconeStore;
use Symfony\AI\Store\Bridge\Qdrant\Store as QdrantStore;
use Symfony\AI\Store\Bridge\SurrealDb\Store as SurrealDbStore;
use Symfony\AI\Store\Bridge\Typesense\Store as TypesenseStore;
use Symfony\AI\Store\Bridge\Weaviate\Store as WeaviateStore;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer;
use Symfony\AI\Store\IndexerInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\String\u;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AiBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new ProcessorCompilerPass());
    }

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
                    ->setArguments([new Reference('.inner')])
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
            $builder->setAlias(StoreInterface::class, reset($stores));
        }

        if ([] === $stores) {
            $builder->removeDefinition('ai.command.setup_store');
            $builder->removeDefinition('ai.command.drop_store');
        }

        foreach ($config['vectorizer'] ?? [] as $vectorizerName => $vectorizer) {
            $this->processVectorizerConfig($vectorizerName, $vectorizer, $builder);
        }

        foreach ($config['indexer'] as $indexerName => $indexer) {
            $this->processIndexerConfig($indexerName, $indexer, $builder);
        }
        if (1 === \count($config['indexer']) && isset($indexerName)) {
            $builder->setAlias(IndexerInterface::class, 'ai.indexer.'.$indexerName);
        }

        $builder->registerAttributeForAutoconfiguration(AsTool::class, static function (ChildDefinition $definition, AsTool $attribute): void {
            $definition->addTag('ai.tool', [
                'name' => $attribute->name,
                'description' => $attribute->description,
                'method' => $attribute->method,
            ]);
        });

        $builder->registerAttributeForAutoconfiguration(AsInputProcessor::class, static function (ChildDefinition $definition, AsInputProcessor $attribute): void {
            $definition->addTag('ai.agent.input_processor', [
                'agent' => $attribute->agent,
                'priority' => $attribute->priority,
            ]);
        });

        $builder->registerAttributeForAutoconfiguration(AsOutputProcessor::class, static function (ChildDefinition $definition, AsOutputProcessor $attribute): void {
            $definition->addTag('ai.agent.output_processor', [
                'agent' => $attribute->agent,
                'priority' => $attribute->priority,
            ]);
        });

        $builder->registerForAutoconfiguration(InputProcessorInterface::class)
            ->addTag('ai.agent.input_processor', ['tagged_by' => 'interface']);
        $builder->registerForAutoconfiguration(OutputProcessorInterface::class)
            ->addTag('ai.agent.output_processor', ['tagged_by' => 'interface']);

        $builder->registerForAutoconfiguration(ModelClientInterface::class)
            ->addTag('ai.platform.model_client');
        $builder->registerForAutoconfiguration(ResultConverterInterface::class)
            ->addTag('ai.platform.result_converter');

        if (!ContainerBuilder::willBeAvailable('symfony/security-core', AuthorizationCheckerInterface::class, ['symfony/ai-bundle'])) {
            $builder->removeDefinition('ai.security.is_granted_attribute_listener');
            $builder->registerAttributeForAutoconfiguration(
                IsGrantedTool::class,
                static fn () => throw new InvalidArgumentException('Using #[IsGrantedTool] attribute requires additional dependencies. Try running "composer install symfony/security-core".'),
            );
        }

        if (false === $builder->getParameter('kernel.debug')) {
            $builder->removeDefinition('ai.data_collector');
            $builder->removeDefinition('ai.traceable_toolbox');
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
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['api_key'],
                    new Reference('http_client', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('ai.platform.contract.anthropic'),
                ])
                ->addTag('ai.platform');

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('azure' === $type) {
            foreach ($platform as $name => $config) {
                $platformId = 'ai.platform.azure.'.$name;
                $definition = (new Definition(Platform::class))
                    ->setFactory(AzureOpenAiPlatformFactory::class.'::create')
                    ->setLazy(true)
                    ->addTag('proxy', ['interface' => PlatformInterface::class])
                    ->setArguments([
                        $config['base_url'],
                        $config['deployment'],
                        $config['api_version'],
                        $config['api_key'],
                        new Reference('http_client', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                        new Reference('ai.platform.contract.openai'),
                    ])
                    ->addTag('ai.platform');

                $container->setDefinition($platformId, $definition);
            }

            return;
        }

        if ('eleven_labs' === $type) {
            $platformId = 'ai.platform.eleven_labs';
            $definition = (new Definition(Platform::class))
                ->setFactory(ElevenLabsPlatformFactory::class.'::create')
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['api_key'],
                    $platform['host'],
                    new Reference('http_client', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('ai.platform.contract.default'),
                ])
                ->addTag('ai.platform');

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('gemini' === $type) {
            $platformId = 'ai.platform.gemini';
            $definition = (new Definition(Platform::class))
                ->setFactory(GeminiPlatformFactory::class.'::create')
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['api_key'],
                    new Reference('http_client', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('ai.platform.contract.google'),
                ])
                ->addTag('ai.platform');

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('vertexai' === $type && isset($platform['location'], $platform['project_id'])) {
            if (!class_exists(ApplicationDefaultCredentials::class)) {
                throw new RuntimeException('For using the Vertex AI platform, google/auth package is required. Try running "composer require google/auth".');
            }

            $credentials = (new Definition(FetchAuthTokenInterface::class))
                ->setFactory([ApplicationDefaultCredentials::class, 'getCredentials'])
                ->setArguments([
                    'https://www.googleapis.com/auth/cloud-platform',
                ])
            ;

            $credentialsObject = new Definition(\ArrayObject::class, [(new Definition('array'))->setFactory([$credentials, 'fetchAuthToken'])]);

            $httpClient = (new Definition(HttpClientInterface::class))
                ->setFactory([HttpClient::class, 'create'])
                ->setArgument(0, [
                    'auth_bearer' => (new Definition('string', ['access_token']))->setFactory([$credentialsObject, 'offsetGet']),
                ])
            ;

            $platformId = 'ai.platform.vertexai';
            $definition = (new Definition(Platform::class))
                ->setFactory([VertexAiPlatformFactory::class, 'create'])
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['location'],
                    $platform['project_id'],
                    $httpClient,
                    new Reference('ai.platform.contract.vertexai', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ])
                ->addTag('ai.platform');

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('openai' === $type) {
            $platformId = 'ai.platform.openai';
            $definition = (new Definition(Platform::class))
                ->setFactory(OpenAiPlatformFactory::class.'::create')
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['api_key'],
                    new Reference('http_client', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('ai.platform.contract.openai'),
                    $platform['region'] ?? null,
                ])
                ->addTag('ai.platform');

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('openrouter' === $type) {
            $platformId = 'ai.platform.openrouter';
            $definition = (new Definition(Platform::class))
                ->setFactory(OpenRouterPlatformFactory::class.'::create')
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['api_key'],
                    new Reference('http_client', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('ai.platform.contract.default'),
                ])
                ->addTag('ai.platform');

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('mistral' === $type) {
            $platformId = 'ai.platform.mistral';
            $definition = (new Definition(Platform::class))
                ->setFactory(MistralPlatformFactory::class.'::create')
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['api_key'],
                    new Reference('http_client', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('ai.platform.contract.default'),
                ])
                ->addTag('ai.platform');

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('lmstudio' === $type) {
            $platformId = 'ai.platform.lmstudio';
            $definition = (new Definition(Platform::class))
                ->setFactory(LmStudioPlatformFactory::class.'::create')
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['host_url'],
                    new Reference('http_client', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('ai.platform.contract.default'),
                ])
                ->addTag('ai.platform');

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('ollama' === $type) {
            $platformId = 'ai.platform.ollama';
            $definition = (new Definition(Platform::class))
                ->setFactory(MistralPlatformFactory::class.'::create')
                ->setFactory(OllamaPlatformFactory::class.'::create')
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['host_url'],
                    new Reference('http_client', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('ai.platform.contract.ollama'),
                ])
                ->addTag('ai.platform');

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('cerebras' === $type) {
            $platformId = 'ai.platform.cerebras';
            $definition = (new Definition(Platform::class))
                ->setFactory(CerebrasPlatformFactory::class.'::create')
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['api_key'],
                    new Reference('http_client', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ])
                ->addTag('ai.platform');

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('voyage' === $type) {
            $platformId = 'ai.platform.voyage';
            $definition = (new Definition(Platform::class))
                ->setFactory(VoyagePlatformFactory::class.'::create')
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['api_key'],
                    new Reference('http_client', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ])
                ->addTag('ai.platform');

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('perplexity' === $type) {
            $platformId = 'ai.platform.perplexity';
            $definition = (new Definition(Platform::class))
                ->setFactory(PerplexityPlatformFactory::class.'::create')
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['api_key'],
                    new Reference('http_client', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('ai.platform.contract.perplexity'),
                ])
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
            $modelDefinition->setArgument(0, $modelName);
        }
        if ([] !== $options) {
            $modelDefinition->setArgument(1, $options);
        }
        $modelDefinition->addTag('ai.model.language_model');
        $container->setDefinition('ai.agent.'.$name.'.model', $modelDefinition);

        // AGENT
        $agentId = 'ai.agent.'.$name;
        $agentDefinition = (new Definition(Agent::class))
            ->addTag('ai.agent', ['name' => $name])
            ->setArgument(0, new Reference($config['platform']))
            ->setArgument(1, new Reference('ai.agent.'.$name.'.model'));

        // TOOL & PROCESSOR
        if ($config['tools']['enabled']) {
            // Create specific toolbox and process if tools are explicitly defined
            if ([] !== $config['tools']['services']) {
                $memoryFactoryDefinition = new ChildDefinition('ai.tool_factory.abstract');
                $memoryFactoryDefinition->setClass(MemoryToolFactory::class);
                $container->setDefinition('ai.toolbox.'.$name.'.memory_factory', $memoryFactoryDefinition);
                $chainFactoryDefinition = new Definition(ChainFactory::class, [
                    [new Reference('ai.toolbox.'.$name.'.memory_factory'), new Reference('ai.tool_factory')],
                ]);
                $container->setDefinition('ai.toolbox.'.$name.'.chain_factory', $chainFactoryDefinition);

                $tools = [];
                foreach ($config['tools']['services'] as $tool) {
                    if (isset($tool['agent'])) {
                        $tool['name'] ??= $tool['agent'];
                        $tool['service'] = \sprintf('ai.agent.%s', $tool['agent']);
                    }
                    $reference = new Reference($tool['service']);
                    // We use the memory factory in case method, description and name are set
                    if (isset($tool['name'], $tool['description'])) {
                        if (isset($tool['agent'])) {
                            $agentWrapperDefinition = new Definition(AgentTool::class, [$reference]);
                            $container->setDefinition('ai.toolbox.'.$name.'.agent_wrapper.'.$tool['name'], $agentWrapperDefinition);
                            $reference = new Reference('ai.toolbox.'.$name.'.agent_wrapper.'.$tool['name']);
                        }
                        $memoryFactoryDefinition->addMethodCall('addTool', [$reference, $tool['name'], $tool['description'], $tool['method'] ?? '__invoke']);
                    }
                    $tools[] = $reference;
                }

                $toolboxDefinition = (new ChildDefinition('ai.toolbox.abstract'))
                    ->replaceArgument(0, $tools)
                    ->replaceArgument(1, new Reference('ai.toolbox.'.$name.'.chain_factory'));
                $container->setDefinition('ai.toolbox.'.$name, $toolboxDefinition);

                if ($config['fault_tolerant_toolbox']) {
                    $container->setDefinition('ai.fault_tolerant_toolbox.'.$name, new Definition(FaultTolerantToolbox::class))
                        ->setArguments([new Reference('.inner')])
                        ->setDecoratedService('ai.toolbox.'.$name);
                }

                if ($container->getParameter('kernel.debug')) {
                    $traceableToolboxDefinition = (new Definition('ai.traceable_toolbox.'.$name))
                        ->setClass(TraceableToolbox::class)
                        ->setArguments([new Reference('.inner')])
                        ->setDecoratedService('ai.toolbox.'.$name)
                        ->addTag('ai.traceable_toolbox');
                    $container->setDefinition('ai.traceable_toolbox.'.$name, $traceableToolboxDefinition);
                }

                $toolProcessorDefinition = (new ChildDefinition('ai.tool.agent_processor.abstract'))
                    ->replaceArgument(0, new Reference('ai.toolbox.'.$name));

                $container->setDefinition('ai.tool.agent_processor.'.$name, $toolProcessorDefinition)
                    ->addTag('ai.agent.input_processor', ['agent' => $agentId, 'priority' => -10])
                    ->addTag('ai.agent.output_processor', ['agent' => $agentId, 'priority' => -10]);
            } else {
                if ($config['fault_tolerant_toolbox'] && !$container->hasDefinition('ai.fault_tolerant_toolbox')) {
                    $container->setDefinition('ai.fault_tolerant_toolbox', new Definition(FaultTolerantToolbox::class))
                        ->setArguments([new Reference('.inner')])
                        ->setDecoratedService('ai.toolbox');
                }

                $container->getDefinition('ai.tool.agent_processor')
                    ->addTag('ai.agent.input_processor', ['agent' => $agentId, 'priority' => -10])
                    ->addTag('ai.agent.output_processor', ['agent' => $agentId, 'priority' => -10]);
            }
        }

        // STRUCTURED OUTPUT
        if ($config['structured_output']) {
            $container->getDefinition('ai.agent.structured_output_processor')
                ->addTag('ai.agent.input_processor', ['agent' => $agentId, 'priority' => -20])
                ->addTag('ai.agent.output_processor', ['agent' => $agentId, 'priority' => -20]);
        }

        // TOKEN USAGE TRACKING
        if ($config['track_token_usage'] ?? true) {
            $platformServiceId = $config['platform'];

            if ($container->hasAlias($platformServiceId)) {
                $platformServiceId = (string) $container->getAlias($platformServiceId);
            }

            if (str_starts_with($platformServiceId, 'ai.platform.')) {
                $platform = u($platformServiceId)->after('ai.platform.')->toString();

                if (str_contains($platform, 'azure')) {
                    $platform = 'azure';
                }

                if ($container->hasDefinition('ai.platform.token_usage_processor.'.$platform)) {
                    $container->getDefinition('ai.platform.token_usage_processor.'.$platform)
                        ->addTag('ai.agent.output_processor', ['agent' => $agentId, 'priority' => -30]);
                }
            }
        }

        // SYSTEM PROMPT
        if (\is_string($config['system_prompt'])) {
            $systemPromptInputProcessorDefinition = (new Definition(SystemPromptInputProcessor::class))
                ->setArguments([
                    $config['system_prompt'],
                    $config['include_tools'] ? new Reference('ai.toolbox.'.$name) : null,
                    new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
                ])
                ->addTag('ai.agent.input_processor', ['agent' => $agentId, 'priority' => -30]);

            $container->setDefinition('ai.agent.'.$name.'.system_prompt_processor', $systemPromptInputProcessorDefinition);
        }

        $agentDefinition
            ->setArgument(2, []) // placeholder until ProcessorCompilerPass process.
            ->setArgument(3, []) // placeholder until ProcessorCompilerPass process.
            ->setArgument(4, $name)
            ->setArgument(5, new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE))
        ;

        $container->setDefinition($agentId, $agentDefinition);
        $container->registerAliasForArgument($agentId, AgentInterface::class, (new Target($name.'Agent'))->getParsedName());
    }

    /**
     * @param array<string, mixed> $stores
     */
    private function processStoreConfig(string $type, array $stores, ContainerBuilder $container): void
    {
        if ('azure_search' === $type) {
            foreach ($stores as $name => $store) {
                $arguments = [
                    new Reference('http_client'),
                    $store['endpoint'],
                    $store['api_key'],
                    $store['index_name'],
                    $store['api_version'],
                ];

                if (\array_key_exists('vector_field', $store)) {
                    $arguments[5] = $store['vector_field'];
                }

                $definition = new Definition(AzureSearchStore::class);
                $definition
                    ->addTag('ai.store')
                    ->setArguments($arguments);

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$name, StoreInterface::class, (new Target($name.'Store'))->getParsedName());
            }
        }

        if ('cache' === $type) {
            foreach ($stores as $name => $store) {
                $arguments = [
                    new Reference($store['service']),
                    new Definition(DistanceCalculator::class),
                ];

                if (\array_key_exists('cache_key', $store) && null !== $store['cache_key']) {
                    $arguments[2] = $store['cache_key'];
                }

                if (\array_key_exists('strategy', $store) && null !== $store['strategy']) {
                    if (!$container->hasDefinition('ai.store.distance_calculator.'.$name)) {
                        $distanceCalculatorDefinition = new Definition(DistanceCalculator::class);
                        $distanceCalculatorDefinition->setArgument(0, DistanceStrategy::from($store['strategy']));

                        $container->setDefinition('ai.store.distance_calculator.'.$name, $distanceCalculatorDefinition);
                    }

                    $arguments[1] = new Reference('ai.store.distance_calculator.'.$name);
                }

                $definition = new Definition(CacheStore::class);
                $definition
                    ->addTag('ai.store')
                    ->setArguments($arguments);

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$name, StoreInterface::class, (new Target($name.'Store'))->getParsedName());
            }
        }

        if ('chroma_db' === $type) {
            foreach ($stores as $name => $store) {
                $definition = new Definition(ChromaDbStore::class);
                $definition
                    ->setArguments([
                        new Reference($store['client']),
                        $store['collection'],
                    ])
                    ->addTag('ai.store');

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$name, StoreInterface::class, (new Target($name.'Store'))->getParsedName());
            }
        }

        if ('clickhouse' === $type) {
            foreach ($stores as $name => $store) {
                if (isset($store['http_client'])) {
                    $httpClient = new Reference($store['http_client']);
                } else {
                    $httpClient = new Definition(HttpClientInterface::class);
                    $httpClient
                        ->setFactory([HttpClient::class, 'createForBaseUri'])
                        ->setArguments([$store['dsn']])
                    ;
                }

                $definition = new Definition(ClickHouseStore::class);
                $definition
                    ->setArguments([
                        $httpClient,
                        $store['database'],
                        $store['table'],
                    ])
                    ->addTag('ai.store')
                ;

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$name, StoreInterface::class, (new Target($name.'Store'))->getParsedName());
            }
        }

        if ('cloudflare' === $type) {
            foreach ($stores as $name => $store) {
                $arguments = [
                    new Reference('http_client'),
                    $store['account_id'],
                    $store['api_key'],
                    $store['index_name'],
                ];

                if (\array_key_exists('dimensions', $store)) {
                    $arguments[4] = $store['dimensions'];
                }

                if (\array_key_exists('metric', $store)) {
                    $arguments[5] = $store['metric'];
                }

                if (\array_key_exists('endpoint', $store)) {
                    $arguments[6] = $store['endpoint'];
                }

                $definition = new Definition(CloudflareStore::class);
                $definition
                    ->addTag('ai.store')
                    ->setArguments($arguments);

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
            }
        }

        if ('meilisearch' === $type) {
            foreach ($stores as $name => $store) {
                $arguments = [
                    new Reference('http_client'),
                    $store['endpoint'],
                    $store['api_key'],
                    $store['index_name'],
                ];

                if (\array_key_exists('embedder', $store)) {
                    $arguments[4] = $store['embedder'];
                }

                if (\array_key_exists('vector_field', $store)) {
                    $arguments[5] = $store['vector_field'];
                }

                if (\array_key_exists('dimensions', $store)) {
                    $arguments[6] = $store['dimensions'];
                }

                $definition = new Definition(MeilisearchStore::class);
                $definition
                    ->addTag('ai.store')
                    ->setArguments($arguments);

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$name, StoreInterface::class, (new Target($name.'Store'))->getParsedName());
            }
        }

        if ('memory' === $type) {
            foreach ($stores as $name => $store) {
                $arguments = [];

                if (\array_key_exists('strategy', $store) && null !== $store['strategy']) {
                    if (!$container->hasDefinition('ai.store.distance_calculator.'.$name)) {
                        $distanceCalculatorDefinition = new Definition(DistanceCalculator::class);
                        $distanceCalculatorDefinition->setArgument(0, DistanceStrategy::from($store['strategy']));

                        $container->setDefinition('ai.store.distance_calculator.'.$name, $distanceCalculatorDefinition);
                    }

                    $arguments[0] = new Reference('ai.store.distance_calculator.'.$name);
                }

                $definition = new Definition(InMemoryStore::class);
                $definition
                    ->addTag('ai.store')
                    ->setArguments($arguments);

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$name, StoreInterface::class, (new Target($name.'Store'))->getParsedName());
            }
        }

        if ('milvus' === $type) {
            foreach ($stores as $name => $store) {
                $arguments = [
                    new Reference('http_client'),
                    $store['endpoint'],
                    $store['api_key'],
                    $store['database'],
                    $store['collection'],
                ];

                if (\array_key_exists('vector_field', $store)) {
                    $arguments[5] = $store['vector_field'];
                }

                if (\array_key_exists('dimensions', $store)) {
                    $arguments[6] = $store['dimensions'];
                }

                if (\array_key_exists('metric_type', $store)) {
                    $arguments[7] = $store['metric_type'];
                }

                $definition = new Definition(MilvusStore::class);
                $definition
                    ->addTag('ai.store')
                    ->setArguments($arguments);

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$name, StoreInterface::class, (new Target($name.'Store'))->getParsedName());
            }
        }

        if ('mongodb' === $type) {
            foreach ($stores as $name => $store) {
                $arguments = [
                    new Reference($store['client']),
                    $store['database'],
                    $store['collection'],
                    $store['index_name'],
                ];

                if (\array_key_exists('vector_field', $store)) {
                    $arguments[4] = $store['vector_field'];
                }

                if (\array_key_exists('bulk_write', $store)) {
                    $arguments[5] = $store['bulk_write'];
                }

                $definition = new Definition(MongoDbStore::class);
                $definition
                    ->addTag('ai.store')
                    ->setArguments($arguments);

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$name, StoreInterface::class, (new Target($name.'Store'))->getParsedName());
            }
        }

        if ('neo4j' === $type) {
            foreach ($stores as $name => $store) {
                $arguments = [
                    new Reference('http_client'),
                    $store['endpoint'],
                    $store['username'],
                    $store['password'],
                    $store['database'],
                    $store['vector_index_name'],
                    $store['node_name'],
                ];

                if (\array_key_exists('vector_field', $store)) {
                    $arguments[7] = $store['vector_field'];
                }

                if (\array_key_exists('dimensions', $store)) {
                    $arguments[8] = $store['dimensions'];
                }

                if (\array_key_exists('distance', $store)) {
                    $arguments[9] = $store['distance'];
                }

                if (\array_key_exists('quantization', $store)) {
                    $arguments[10] = $store['quantization'];
                }

                $definition = new Definition(Neo4jStore::class);
                $definition
                    ->addTag('ai.store')
                    ->setArguments($arguments);

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$name, StoreInterface::class, (new Target($name.'Store'))->getParsedName());
            }
        }

        if ('pinecone' === $type) {
            foreach ($stores as $name => $store) {
                $arguments = [
                    new Reference($store['client']),
                    $store['namespace'],
                ];

                if (\array_key_exists('filter', $store)) {
                    $arguments[2] = $store['filter'];
                }

                if (\array_key_exists('top_k', $store)) {
                    $arguments[3] = $store['top_k'];
                }

                $definition = new Definition(PineconeStore::class);
                $definition
                    ->addTag('ai.store')
                    ->setArguments($arguments);

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$name, StoreInterface::class, (new Target($name.'Store'))->getParsedName());
            }
        }

        if ('qdrant' === $type) {
            foreach ($stores as $name => $store) {
                $arguments = [
                    new Reference('http_client'),
                    $store['endpoint'],
                    $store['api_key'],
                    $store['collection_name'],
                ];

                if (\array_key_exists('dimensions', $store)) {
                    $arguments[4] = $store['dimensions'];
                }

                if (\array_key_exists('distance', $store)) {
                    $arguments[5] = $store['distance'];
                }

                $definition = new Definition(QdrantStore::class);
                $definition
                    ->addTag('ai.store')
                    ->setArguments($arguments);

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$name, StoreInterface::class, (new Target($name.'Store'))->getParsedName());
            }
        }

        if ('surreal_db' === $type) {
            foreach ($stores as $name => $store) {
                $arguments = [
                    new Reference('http_client'),
                    $store['endpoint'],
                    $store['username'],
                    $store['password'],
                    $store['namespace'],
                    $store['database'],
                ];

                if (\array_key_exists('table', $store)) {
                    $arguments[6] = $store['table'];
                }

                if (\array_key_exists('vector_field', $store)) {
                    $arguments[7] = $store['vector_field'];
                }

                if (\array_key_exists('strategy', $store)) {
                    $arguments[8] = $store['strategy'];
                }

                if (\array_key_exists('dimensions', $store)) {
                    $arguments[9] = $store['dimensions'];
                }

                if (\array_key_exists('namespaced_user', $store)) {
                    $arguments[10] = $store['namespaced_user'];
                }

                $definition = new Definition(SurrealDbStore::class);
                $definition
                    ->addTag('ai.store')
                    ->setArguments($arguments);

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$name, StoreInterface::class, (new Target($name.'Store'))->getParsedName());
            }
        }

        if ('typesense' === $type) {
            foreach ($stores as $name => $store) {
                $arguments = [
                    new Reference('http_client'),
                    $store['endpoint'],
                    $store['api_key'],
                    $store['collection'],
                ];

                if (\array_key_exists('vector_field', $store)) {
                    $arguments[4] = $store['vector_field'];
                }

                if (\array_key_exists('dimensions', $store)) {
                    $arguments[5] = $store['dimensions'];
                }

                $definition = new Definition(TypesenseStore::class);
                $definition
                    ->addTag('ai.store')
                    ->setArguments($arguments);

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$name, StoreInterface::class, (new Target($name.'Store'))->getParsedName());
            }
        }

        if ('weaviate' === $type) {
            foreach ($stores as $name => $store) {
                $arguments = [
                    new Reference('http_client'),
                    $store['endpoint'],
                    $store['api_key'],
                    $store['collection'],
                ];

                $definition = new Definition(WeaviateStore::class);
                $definition
                    ->addTag('ai.store')
                    ->setArguments($arguments);

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function processVectorizerConfig(string $name, array $config, ContainerBuilder $container): void
    {
        ['class' => $modelClass, 'name' => $modelName, 'options' => $options] = $config['model'];

        if (!is_a($modelClass, Model::class, true)) {
            throw new InvalidArgumentException(\sprintf('"%s" class is not extending Symfony\AI\Platform\Model.', $modelClass));
        }

        $modelDefinition = (new Definition((string) $modelClass));
        if (null !== $modelName) {
            $modelDefinition->setArgument(0, $modelName);
        }
        if ([] !== $options) {
            $modelDefinition->setArgument(1, $options);
        }

        $modelDefinition->addTag('ai.model.embeddings_model');
        $container->setDefinition('ai.vectorizer.'.$name.'.model', $modelDefinition);

        $vectorizerDefinition = new Definition(Vectorizer::class, [
            new Reference($config['platform']),
            new Reference('ai.vectorizer.'.$name.'.model'),
            new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
        ]);
        $vectorizerDefinition->addTag('ai.vectorizer', ['name' => $name]);
        $container->setDefinition('ai.vectorizer.'.$name, $vectorizerDefinition);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function processIndexerConfig(int|string $name, array $config, ContainerBuilder $container): void
    {
        $transformers = [];
        foreach ($config['transformers'] ?? [] as $transformer) {
            $transformers[] = new Reference($transformer);
        }

        $definition = new Definition(Indexer::class, [
            new Reference($config['loader']),
            new Reference($config['vectorizer']),
            new Reference($config['store']),
            $config['source'],
            $transformers,
            new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
        ]);
        $definition->addTag('ai.indexer', ['name' => $name]);

        $container->setDefinition('ai.indexer.'.$name, $definition);
    }
}
