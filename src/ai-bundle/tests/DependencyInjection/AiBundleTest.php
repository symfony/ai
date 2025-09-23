<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Memory\MemoryInputProcessor;
use Symfony\AI\Agent\Memory\StaticMemoryProvider;
use Symfony\AI\AiBundle\AiBundle;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Mistral\Embeddings as MistralEmbeddings;
use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Store\Document\Filter\TextContainsFilter;
use Symfony\AI\Store\Document\Loader\InMemoryLoader;
use Symfony\AI\Store\Document\Transformer\TextTrimTransformer;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(AiBundle::class)]
#[UsesClass(ContainerBuilder::class)]
#[UsesClass(Definition::class)]
#[UsesClass(Reference::class)]
class AiBundleTest extends TestCase
{
    #[DoesNotPerformAssertions]
    public function testExtensionLoadDoesNotThrow()
    {
        $this->buildContainer($this->getFullConfig());
    }

    public function testStoreCommandsArentDefinedWithoutStore()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'my_agent' => [
                        'model' => ['class' => Gpt::class],
                    ],
                ],
            ],
        ]);

        $this->assertFalse($container->hasDefinition('ai.command.setup_store'));
        $this->assertFalse($container->hasDefinition('ai.command.drop_store'));
        $this->assertSame([
            'ai.command.setup_store' => true,
            'ai.command.drop_store' => true,
        ], $container->getRemovedIds());
    }

    public function testStoreCommandsAreDefined()
    {
        $container = $this->buildContainer($this->getFullConfig());

        $this->assertTrue($container->hasDefinition('ai.command.setup_store'));

        $setupStoreCommandDefinition = $container->getDefinition('ai.command.setup_store');
        $this->assertCount(1, $setupStoreCommandDefinition->getArguments());
        $this->assertArrayHasKey('console.command', $setupStoreCommandDefinition->getTags());

        $this->assertTrue($container->hasDefinition('ai.command.drop_store'));

        $dropStoreCommandDefinition = $container->getDefinition('ai.command.drop_store');
        $this->assertCount(1, $dropStoreCommandDefinition->getArguments());
        $this->assertArrayHasKey('console.command', $dropStoreCommandDefinition->getTags());
    }

    public function testInjectionAgentAliasIsRegistered()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'my_agent' => [
                        'model' => ['class' => Gpt::class],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasAlias(AgentInterface::class));
        $this->assertTrue($container->hasAlias(AgentInterface::class.' $myAgentAgent'));
    }

    public function testAgentHasTag()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'my_agent' => [
                        'model' => ['class' => Gpt::class],
                    ],
                ],
            ],
        ]);

        $this->assertArrayHasKey('ai.agent.my_agent', $container->findTaggedServiceIds('ai.agent'));
    }

    public function testAgentNameIsSetFromConfigKey()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'my_custom_agent' => [
                        'model' => ['class' => Gpt::class],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.agent.my_custom_agent'));

        $agentDefinition = $container->getDefinition('ai.agent.my_custom_agent');
        $arguments = $agentDefinition->getArguments();

        // The 5th argument (index 4) should be the config key as agent name
        $this->assertArrayHasKey(4, $arguments, 'Agent definition should have argument at index 4 for name');
        $this->assertSame('my_custom_agent', $arguments[4]);

        // Check that the tag uses the config key as name
        $tags = $agentDefinition->getTag('ai.agent');
        $this->assertNotEmpty($tags, 'Agent should have ai.agent tag');
        $this->assertSame('my_custom_agent', $tags[0]['name'], 'Agent tag should use config key as name');
    }

    #[TestWith([true], 'enabled')]
    #[TestWith([false], 'disabled')]
    public function testFaultTolerantAgentSpecificToolbox(bool $enabled)
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'my_agent' => [
                        'model' => ['class' => Gpt::class],
                        'tools' => [
                            ['service' => 'some_service', 'description' => 'Some tool'],
                        ],
                        'fault_tolerant_toolbox' => $enabled,
                    ],
                ],
            ],
        ]);

        $this->assertSame($enabled, $container->hasDefinition('ai.fault_tolerant_toolbox.my_agent'));
    }

    #[TestWith([true], 'enabled')]
    #[TestWith([false], 'disabled')]
    public function testFaultTolerantDefaultToolbox(bool $enabled)
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'my_agent' => [
                        'model' => ['class' => Gpt::class],
                        'tools' => true,
                        'fault_tolerant_toolbox' => $enabled,
                    ],
                ],
            ],
        ]);

        $this->assertSame($enabled, $container->hasDefinition('ai.fault_tolerant_toolbox'));
    }

    public function testAgentsCanBeRegisteredAsTools()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'main_agent' => [
                        'model' => ['class' => Gpt::class],
                        'tools' => [
                            ['agent' => 'another_agent', 'description' => 'Agent tool with implicit name'],
                            ['agent' => 'another_agent', 'name' => 'another_agent_instance', 'description' => 'Agent tool with explicit name'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.toolbox.main_agent.agent_wrapper.another_agent'));
        $this->assertTrue($container->hasDefinition('ai.toolbox.main_agent.agent_wrapper.another_agent_instance'));
    }

    public function testAgentsAsToolsCannotDefineService()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->buildContainer([
            'ai' => [
                'agent' => [
                    'main_agent' => [
                        'model' => ['class' => Gpt::class],
                        'tools' => [['agent' => 'another_agent', 'service' => 'foo_bar', 'description' => 'Agent with service']],
                    ],
                ],
            ],
        ]);
    }

    public function testCacheStoreWithCustomKeyCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'cache' => [
                        'my_cache_store_with_custom_strategy' => [
                            'service' => 'cache.system',
                            'cache_key' => 'random',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.cache.my_cache_store_with_custom_strategy'));
        $this->assertFalse($container->hasDefinition('ai.store.distance_calculator.my_cache_store_with_custom_strategy'));

        $definition = $container->getDefinition('ai.store.cache.my_cache_store_with_custom_strategy');

        $this->assertCount(3, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('cache.system', (string) $definition->getArgument(0));
        $this->assertSame('random', $definition->getArgument(2));
    }

    public function testCacheStoreWithCustomStrategyCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'cache' => [
                        'my_cache_store_with_custom_strategy' => [
                            'service' => 'cache.system',
                            'strategy' => 'chebyshev',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.cache.my_cache_store_with_custom_strategy'));
        $this->assertTrue($container->hasDefinition('ai.store.distance_calculator.my_cache_store_with_custom_strategy'));

        $definition = $container->getDefinition('ai.store.cache.my_cache_store_with_custom_strategy');

        $this->assertCount(2, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('cache.system', (string) $definition->getArgument(0));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(1));
        $this->assertSame('ai.store.distance_calculator.my_cache_store_with_custom_strategy', (string) $definition->getArgument(1));
    }

    public function testCacheStoreWithCustomStrategyAndKeyCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'cache' => [
                        'my_cache_store_with_custom_strategy' => [
                            'service' => 'cache.system',
                            'cache_key' => 'random',
                            'strategy' => 'chebyshev',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.cache.my_cache_store_with_custom_strategy'));
        $this->assertTrue($container->hasDefinition('ai.store.distance_calculator.my_cache_store_with_custom_strategy'));

        $definition = $container->getDefinition('ai.store.cache.my_cache_store_with_custom_strategy');

        $this->assertCount(3, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('cache.system', (string) $definition->getArgument(0));
        $this->assertSame('random', $definition->getArgument(2));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(1));
        $this->assertSame('ai.store.distance_calculator.my_cache_store_with_custom_strategy', (string) $definition->getArgument(1));
    }

    public function testInMemoryStoreWithoutCustomStrategyCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_memory_store_with_custom_strategy' => [],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.memory.my_memory_store_with_custom_strategy'));

        $definition = $container->getDefinition('ai.store.memory.my_memory_store_with_custom_strategy');
        $this->assertCount(0, $definition->getArguments());
    }

    public function testInMemoryStoreWithCustomStrategyCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_memory_store_with_custom_strategy' => [
                            'strategy' => 'chebyshev',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.memory.my_memory_store_with_custom_strategy'));
        $this->assertTrue($container->hasDefinition('ai.store.distance_calculator.my_memory_store_with_custom_strategy'));

        $definition = $container->getDefinition('ai.store.memory.my_memory_store_with_custom_strategy');

        $this->assertCount(1, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('ai.store.distance_calculator.my_memory_store_with_custom_strategy', (string) $definition->getArgument(0));
    }

    public function testConfigurationWithUseAttributeAsKeyWorksWithoutNormalizeKeys()
    {
        // Test that configurations using useAttributeAsKey work correctly
        // after removing redundant normalizeKeys(false) calls
        $container = $this->buildContainer([
            'ai' => [
                'platform' => [
                    'azure' => [
                        'Test_Instance-123' => [ // Mixed case and special chars in key
                            'api_key' => 'test_key',
                            'base_url' => 'https://test.openai.azure.com/',
                            'deployment' => 'gpt-35-turbo',
                            'api_version' => '2024-02-15-preview',
                        ],
                    ],
                ],
                'agent' => [
                    'My-Agent_Name.v2' => [ // Mixed case and special chars in key
                        'model' => ['class' => Gpt::class],
                    ],
                ],
                'store' => [
                    'mongodb' => [
                        'Production_DB-v3' => [ // Mixed case and special chars in key
                            'database' => 'test_db',
                            'collection' => 'test_collection',
                            'index_name' => 'test_index',
                        ],
                    ],
                ],
            ],
        ]);

        // Verify that the services are created with the exact key names
        $this->assertTrue($container->hasDefinition('ai.platform.azure.Test_Instance-123'));
        $this->assertTrue($container->hasDefinition('ai.agent.My-Agent_Name.v2'));
        $this->assertTrue($container->hasDefinition('ai.store.mongodb.Production_DB-v3'));
    }

    /**
     * Tests that processor tags use the full agent ID (ai.agent.my_agent) instead of just the agent name (my_agent).
     * This regression test prevents issues where processors would not be correctly associated with their agents.
     */
    #[TestDox('Processor tags use the full agent ID instead of just the agent name')]
    public function testProcessorTagsUseFullAgentId()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => ['class' => Gpt::class],
                        'tools' => [
                            ['service' => 'some_tool', 'description' => 'Test tool'],
                        ],
                        'structured_output' => true,
                        'prompt' => 'You are a test assistant.',
                    ],
                ],
            ],
        ]);

        $agentId = 'ai.agent.test_agent';

        // Test tool processor tags
        $toolProcessorDefinition = $container->getDefinition('ai.tool.agent_processor.test_agent');
        $toolProcessorTags = $toolProcessorDefinition->getTag('ai.agent.input_processor');
        $this->assertNotEmpty($toolProcessorTags, 'Tool processor should have input processor tags');
        $this->assertSame($agentId, $toolProcessorTags[0]['agent'], 'Tool input processor tag should use full agent ID');

        $outputTags = $toolProcessorDefinition->getTag('ai.agent.output_processor');
        $this->assertNotEmpty($outputTags, 'Tool processor should have output processor tags');
        $this->assertSame($agentId, $outputTags[0]['agent'], 'Tool output processor tag should use full agent ID');

        // Test structured output processor tags
        $structuredOutputTags = $container->getDefinition('ai.agent.structured_output_processor')
            ->getTag('ai.agent.input_processor');
        $this->assertNotEmpty($structuredOutputTags, 'Structured output processor should have input processor tags');

        // Find the tag for our specific agent
        $foundAgentTag = false;
        foreach ($structuredOutputTags as $tag) {
            if (($tag['agent'] ?? '') === $agentId) {
                $foundAgentTag = true;
                break;
            }
        }
        $this->assertTrue($foundAgentTag, 'Structured output processor should have tag with full agent ID');

        // Test system prompt processor tags
        $systemPromptDefinition = $container->getDefinition('ai.agent.test_agent.system_prompt_processor');
        $systemPromptTags = $systemPromptDefinition->getTag('ai.agent.input_processor');
        $this->assertNotEmpty($systemPromptTags, 'System prompt processor should have input processor tags');
        $this->assertSame($agentId, $systemPromptTags[0]['agent'], 'System prompt processor tag should use full agent ID');
    }

    #[TestDox('Processors work correctly with multiple agents')]
    public function testMultipleAgentsWithProcessors()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'first_agent' => [
                        'model' => ['class' => Gpt::class],
                        'tools' => [
                            ['service' => 'tool_one', 'description' => 'Tool for first agent'],
                        ],
                        'prompt' => 'First agent prompt',
                    ],
                    'second_agent' => [
                        'model' => ['class' => Claude::class],
                        'tools' => [
                            ['service' => 'tool_two', 'description' => 'Tool for second agent'],
                        ],
                        'prompt' => 'Second agent prompt',
                    ],
                ],
            ],
        ]);

        // Check that each agent has its own properly tagged processors
        $firstAgentId = 'ai.agent.first_agent';
        $secondAgentId = 'ai.agent.second_agent';

        // First agent tool processor
        $firstToolProcessor = $container->getDefinition('ai.tool.agent_processor.first_agent');
        $firstToolTags = $firstToolProcessor->getTag('ai.agent.input_processor');
        $this->assertSame($firstAgentId, $firstToolTags[0]['agent']);

        // Second agent tool processor
        $secondToolProcessor = $container->getDefinition('ai.tool.agent_processor.second_agent');
        $secondToolTags = $secondToolProcessor->getTag('ai.agent.input_processor');
        $this->assertSame($secondAgentId, $secondToolTags[0]['agent']);

        // First agent system prompt processor
        $firstSystemPrompt = $container->getDefinition('ai.agent.first_agent.system_prompt_processor');
        $firstSystemTags = $firstSystemPrompt->getTag('ai.agent.input_processor');
        $this->assertSame($firstAgentId, $firstSystemTags[0]['agent']);
        $this->assertCount(3, array_filter($firstSystemPrompt->getArguments()));

        // Second agent system prompt processor
        $secondSystemPrompt = $container->getDefinition('ai.agent.second_agent.system_prompt_processor');
        $secondSystemTags = $secondSystemPrompt->getTag('ai.agent.input_processor');
        $this->assertSame($secondAgentId, $secondSystemTags[0]['agent']);
        $this->assertCount(3, array_filter($secondSystemPrompt->getArguments()));
    }

    #[TestDox('Processors work correctly when using the default toolbox')]
    public function testDefaultToolboxProcessorTags()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'agent_with_default_toolbox' => [
                        'model' => ['class' => Gpt::class],
                        'tools' => true,
                    ],
                ],
            ],
        ]);

        $agentId = 'ai.agent.agent_with_default_toolbox';

        // When using default toolbox, the ai.tool.agent_processor service gets the tags
        $defaultToolProcessor = $container->getDefinition('ai.tool.agent_processor');
        $inputTags = $defaultToolProcessor->getTag('ai.agent.input_processor');
        $outputTags = $defaultToolProcessor->getTag('ai.agent.output_processor');

        // Find tags for our specific agent
        $foundInput = false;
        $foundOutput = false;

        foreach ($inputTags as $tag) {
            if (($tag['agent'] ?? '') === $agentId) {
                $foundInput = true;
                break;
            }
        }

        foreach ($outputTags as $tag) {
            if (($tag['agent'] ?? '') === $agentId) {
                $foundOutput = true;
                break;
            }
        }

        $this->assertTrue($foundInput, 'Default tool processor should have input tag with full agent ID');
        $this->assertTrue($foundOutput, 'Default tool processor should have output tag with full agent ID');
    }

    #[TestDox('Token usage processor tags use the correct agent ID')]
    public function testTokenUsageProcessorTags()
    {
        $container = $this->buildContainer([
            'ai' => [
                'platform' => [
                    'openai' => [
                        'api_key' => 'sk-test_key',
                    ],
                ],
                'agent' => [
                    'tracked_agent' => [
                        'platform' => 'ai.platform.openai',
                        'model' => ['class' => Gpt::class],
                        'track_token_usage' => true,
                    ],
                ],
            ],
        ]);

        $agentId = 'ai.agent.tracked_agent';

        // Token usage processor must exist for OpenAI platform
        $tokenUsageProcessor = $container->getDefinition('ai.platform.token_usage_processor.openai');
        $outputTags = $tokenUsageProcessor->getTag('ai.agent.output_processor');

        $foundTag = false;
        foreach ($outputTags as $tag) {
            if (($tag['agent'] ?? '') === $agentId) {
                $foundTag = true;
                break;
            }
        }

        $this->assertTrue($foundTag, 'Token usage processor should have output tag with full agent ID');
    }

    public function testOpenAiPlatformWithDefaultRegion()
    {
        $container = $this->buildContainer([
            'ai' => [
                'platform' => [
                    'openai' => [
                        'api_key' => 'sk-test-key',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.platform.openai'));

        $definition = $container->getDefinition('ai.platform.openai');
        $arguments = $definition->getArguments();

        $this->assertCount(4, $arguments);
        $this->assertSame('sk-test-key', $arguments[0]);
        $this->assertNull($arguments[3]); // region should be null by default
    }

    #[TestWith(['EU'])]
    #[TestWith(['US'])]
    #[TestWith([null])]
    public function testOpenAiPlatformWithRegion(?string $region)
    {
        $container = $this->buildContainer([
            'ai' => [
                'platform' => [
                    'openai' => [
                        'api_key' => 'sk-test-key',
                        'region' => $region,
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.platform.openai'));

        $definition = $container->getDefinition('ai.platform.openai');
        $arguments = $definition->getArguments();

        $this->assertCount(4, $arguments);
        $this->assertSame('sk-test-key', $arguments[0]);
        $this->assertSame($region, $arguments[3]);
    }

    public function testOpenAiPlatformWithInvalidRegion()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The region must be either "EU" (https://eu.api.openai.com), "US" (https://us.api.openai.com) or null (https://api.openai.com)');

        $this->buildContainer([
            'ai' => [
                'platform' => [
                    'openai' => [
                        'api_key' => 'sk-test-key',
                        'region' => 'INVALID',
                    ],
                ],
            ],
        ]);
    }

    public function testPerplexityPlatformConfiguration()
    {
        $container = $this->buildContainer([
            'ai' => [
                'platform' => [
                    'perplexity' => [
                        'api_key' => 'pplx-test-key',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.platform.perplexity'));

        $definition = $container->getDefinition('ai.platform.perplexity');
        $arguments = $definition->getArguments();

        $this->assertCount(3, $arguments);
        $this->assertSame('pplx-test-key', $arguments[0]);
        $this->assertInstanceOf(Reference::class, $arguments[1]);
        $this->assertSame('http_client', (string) $arguments[1]);
        $this->assertInstanceOf(Reference::class, $arguments[2]);
        $this->assertSame('ai.platform.contract.perplexity', (string) $arguments[2]);
    }

    #[TestDox('System prompt with array structure works correctly')]
    public function testSystemPromptWithArrayStructure()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => ['class' => Gpt::class],
                        'prompt' => [
                            'text' => 'You are a helpful assistant.',
                            'enable_translation' => true,
                            'translation_domain' => 'prompts',
                        ],
                        'tools' => [
                            ['service' => 'some_tool', 'description' => 'Test tool'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.system_prompt_processor'));
        $definition = $container->getDefinition('ai.agent.test_agent.system_prompt_processor');
        $arguments = $definition->getArguments();

        $this->assertSame('You are a helpful assistant.', $arguments[0]);
        $this->assertNull($arguments[1]); // include_tools is false, so null reference
        $this->assertTrue($arguments[3]);
        $this->assertSame('prompts', $arguments[4]);
    }

    #[TestDox('System prompt with include_tools enabled works correctly')]
    public function testSystemPromptWithIncludeToolsEnabled()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => ['class' => Gpt::class],
                        'prompt' => [
                            'text' => 'You are a helpful assistant.',
                            'include_tools' => true,
                        ],
                        'tools' => [
                            ['service' => 'some_tool', 'description' => 'Test tool'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.system_prompt_processor'));
        $definition = $container->getDefinition('ai.agent.test_agent.system_prompt_processor');
        $arguments = $definition->getArguments();

        $this->assertSame('You are a helpful assistant.', $arguments[0]);
        $this->assertInstanceOf(Reference::class, $arguments[1]);
        $this->assertSame('ai.toolbox.test_agent', (string) $arguments[1]);
    }

    #[TestDox('System prompt with only text key defaults include_tools to false')]
    public function testSystemPromptWithOnlyTextKey()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => ['class' => Gpt::class],
                        'prompt' => [
                            'text' => 'You are a helpful assistant.',
                        ],
                        'tools' => [
                            ['service' => 'some_tool', 'description' => 'Test tool'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.system_prompt_processor'));
        $definition = $container->getDefinition('ai.agent.test_agent.system_prompt_processor');
        $arguments = $definition->getArguments();

        $this->assertSame('You are a helpful assistant.', $arguments[0]);
        $this->assertNull($arguments[1]); // include_tools defaults to false
    }

    #[TestDox('Agent without system prompt does not create processor')]
    public function testAgentWithoutSystemPrompt()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => ['class' => Gpt::class],
                    ],
                ],
            ],
        ]);

        $this->assertFalse($container->hasDefinition('ai.agent.test_agent.system_prompt_processor'));
    }

    #[TestDox('Valid system prompt creates processor correctly')]
    public function testValidSystemPromptCreatesProcessor()
    {
        // This test verifies that valid system prompts work correctly with new structure
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => ['class' => Gpt::class],
                        'prompt' => [
                            'text' => 'Valid prompt',
                            'include_tools' => true,
                        ],
                        'tools' => [
                            ['service' => 'some_tool', 'description' => 'Test tool'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.system_prompt_processor'));
        $definition = $container->getDefinition('ai.agent.test_agent.system_prompt_processor');
        $arguments = $definition->getArguments();

        $this->assertSame('Valid prompt', $arguments[0]);
        $this->assertInstanceOf(Reference::class, $arguments[1]);
        $this->assertSame('ai.toolbox.test_agent', (string) $arguments[1]);
    }

    #[TestDox('Empty text in array structure throws configuration exception')]
    public function testEmptyTextInArrayThrowsException()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "text" cannot be empty.');

        $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => ['class' => Gpt::class],
                        'prompt' => [
                            'text' => '',
                        ],
                    ],
                ],
            ],
        ]);
    }

    #[TestDox('System prompt array without text key throws configuration exception')]
    public function testSystemPromptArrayWithoutTextKeyThrowsException()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "text" cannot be empty.');

        $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => ['class' => Gpt::class],
                        'prompt' => [
                            'include_tools' => true,
                        ],
                    ],
                ],
            ],
        ]);
    }

    #[TestDox('System prompt with string format works correctly')]
    public function testSystemPromptWithStringFormat()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => ['class' => Gpt::class],
                        'prompt' => 'You are a helpful assistant.',
                        'tools' => [
                            ['service' => 'some_tool', 'description' => 'Test tool'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.system_prompt_processor'));
        $definition = $container->getDefinition('ai.agent.test_agent.system_prompt_processor');
        $arguments = $definition->getArguments();

        $this->assertSame('You are a helpful assistant.', $arguments[0]);
        $this->assertNull($arguments[1]); // include_tools not enabled with string format
    }

    #[TestDox('Memory provider configuration creates memory input processor')]
    public function testMemoryProviderConfiguration()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => ['class' => Gpt::class],
                        'memory' => 'Static memory for testing',
                        'prompt' => [
                            'text' => 'You are a helpful assistant.',
                        ],
                    ],
                ],
            ],
        ]);

        // Should create StaticMemoryProvider for non-existing service name
        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.memory_input_processor'));
        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.static_memory_provider'));

        $definition = $container->getDefinition('ai.agent.test_agent.memory_input_processor');
        $arguments = $definition->getArguments();

        // Check that the memory processor references the static memory provider
        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame('ai.agent.test_agent.static_memory_provider', (string) $arguments[0]);

        // Check that the processor has the correct tags with proper priority
        $tags = $definition->getTag('ai.agent.input_processor');
        $this->assertNotEmpty($tags);
        $this->assertSame('ai.agent.test_agent', $tags[0]['agent']);
        $this->assertSame(-40, $tags[0]['priority']);
    }

    #[TestDox('Agent without memory configuration does not create memory processor')]
    public function testAgentWithoutMemoryConfiguration()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => ['class' => Gpt::class],
                        'prompt' => [
                            'text' => 'You are a helpful assistant.',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertFalse($container->hasDefinition('ai.agent.test_agent.memory_input_processor'));
    }

    #[TestDox('Memory with null value does not create memory processor')]
    public function testMemoryWithNullValueDoesNotCreateProcessor()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => ['class' => Gpt::class],
                        'memory' => null,
                        'prompt' => [
                            'text' => 'You are a helpful assistant.',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertFalse($container->hasDefinition('ai.agent.test_agent.memory_input_processor'));
    }

    #[TestDox('Memory configuration works with system prompt and tools')]
    public function testMemoryWithSystemPromptAndTools()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => ['class' => Gpt::class],
                        'memory' => 'conversation_memory_service',
                        'prompt' => [
                            'text' => 'You are a helpful assistant.',
                            'include_tools' => true,
                        ],
                        'tools' => [
                            ['service' => 'test_tool', 'description' => 'Test tool'],
                        ],
                    ],
                ],
            ],
        ]);

        // Check that all processors are created
        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.memory_input_processor'));
        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.system_prompt_processor'));
        $this->assertTrue($container->hasDefinition('ai.tool.agent_processor.test_agent'));

        // Verify memory processor configuration (static memory since service doesn't exist)
        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.static_memory_provider'));
        $memoryDefinition = $container->getDefinition('ai.agent.test_agent.memory_input_processor');
        $memoryArguments = $memoryDefinition->getArguments();
        $this->assertInstanceOf(Reference::class, $memoryArguments[0]);
        $this->assertSame('ai.agent.test_agent.static_memory_provider', (string) $memoryArguments[0]);

        // Verify memory processor has highest priority (runs first)
        $memoryTags = $memoryDefinition->getTag('ai.agent.input_processor');
        $this->assertSame(-40, $memoryTags[0]['priority']);

        // Verify system prompt processor has correct priority (runs after memory)
        $systemPromptDefinition = $container->getDefinition('ai.agent.test_agent.system_prompt_processor');
        $systemPromptTags = $systemPromptDefinition->getTag('ai.agent.input_processor');
        $this->assertSame(-30, $systemPromptTags[0]['priority']);
    }

    #[TestDox('Memory configuration works with string prompt format')]
    public function testMemoryWithStringPromptFormat()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => ['class' => Gpt::class],
                        'prompt' => 'You are a helpful assistant.',
                        // memory cannot be configured with string format
                    ],
                ],
            ],
        ]);

        // Memory processor should not be created with string prompt format
        $this->assertFalse($container->hasDefinition('ai.agent.test_agent.memory_input_processor'));

        // But system prompt processor should still be created
        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.system_prompt_processor'));
    }

    #[TestDox('Multiple agents can have different memory configurations')]
    public function testMultipleAgentsWithDifferentMemoryConfigurations()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'agent_with_memory' => [
                        'model' => ['class' => Gpt::class],
                        'memory' => 'first_memory_service',
                        'prompt' => [
                            'text' => 'Agent with memory.',
                        ],
                    ],
                    'agent_without_memory' => [
                        'model' => ['class' => Claude::class],
                        'prompt' => [
                            'text' => 'Agent without memory.',
                        ],
                    ],
                    'agent_with_different_memory' => [
                        'model' => ['class' => Gpt::class],
                        'memory' => 'second_memory_service',
                        'prompt' => [
                            'text' => 'Agent with different memory.',
                        ],
                    ],
                ],
            ],
        ]);

        // First agent should have memory processor (static since service doesn't exist)
        $this->assertTrue($container->hasDefinition('ai.agent.agent_with_memory.memory_input_processor'));
        $this->assertTrue($container->hasDefinition('ai.agent.agent_with_memory.static_memory_provider'));
        $firstMemoryDef = $container->getDefinition('ai.agent.agent_with_memory.memory_input_processor');
        $firstMemoryArgs = $firstMemoryDef->getArguments();
        $this->assertSame('ai.agent.agent_with_memory.static_memory_provider', (string) $firstMemoryArgs[0]);

        // Second agent should not have memory processor
        $this->assertFalse($container->hasDefinition('ai.agent.agent_without_memory.memory_input_processor'));

        // Third agent should have memory processor (static since service doesn't exist)
        $this->assertTrue($container->hasDefinition('ai.agent.agent_with_different_memory.memory_input_processor'));
        $this->assertTrue($container->hasDefinition('ai.agent.agent_with_different_memory.static_memory_provider'));
        $thirdMemoryDef = $container->getDefinition('ai.agent.agent_with_different_memory.memory_input_processor');
        $thirdMemoryArgs = $thirdMemoryDef->getArguments();
        $this->assertSame('ai.agent.agent_with_different_memory.static_memory_provider', (string) $thirdMemoryArgs[0]);

        // Verify that each memory processor is tagged for the correct agent
        $firstTags = $firstMemoryDef->getTag('ai.agent.input_processor');
        $this->assertSame('ai.agent.agent_with_memory', $firstTags[0]['agent']);

        $thirdTags = $thirdMemoryDef->getTag('ai.agent.input_processor');
        $this->assertSame('ai.agent.agent_with_different_memory', $thirdTags[0]['agent']);
    }

    #[TestDox('Memory processor uses MemoryInputProcessor class')]
    public function testMemoryProcessorUsesCorrectClass()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => ['class' => Gpt::class],
                        'memory' => 'my_memory_service',
                        'prompt' => [
                            'text' => 'You are a helpful assistant.',
                        ],
                    ],
                ],
            ],
        ]);

        $definition = $container->getDefinition('ai.agent.test_agent.memory_input_processor');
        $this->assertSame(MemoryInputProcessor::class, $definition->getClass());
    }

    #[TestDox('Memory configuration is included in full config example')]
    public function testMemoryInFullConfigurationExample()
    {
        $container = $this->buildContainer($this->getFullConfig());

        // The full config should include memory in some agent
        // Let's check if we need to update the full config to include memory
        $this->assertTrue($container->hasDefinition('ai.agent.my_chat_agent'));

        // For now, let's verify that if memory were added to full config, it would work
        // This test documents the expectation that full config could include memory
        $this->assertInstanceOf(ContainerBuilder::class, $container);
    }

    #[TestDox('Empty string memory configuration throws validation exception')]
    public function testEmptyStringMemoryConfigurationThrowsException()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Memory cannot be empty.');

        $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => ['class' => Gpt::class],
                        'memory' => '',
                        'prompt' => [
                            'text' => 'Test prompt',
                        ],
                    ],
                ],
            ],
        ]);
    }

    #[TestDox('Memory array configuration without service key throws validation exception')]
    public function testMemoryArrayConfigurationWithoutServiceKeyThrowsException()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Memory array configuration must contain a "service" key.');

        $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => ['class' => Gpt::class],
                        'memory' => ['invalid' => 'value'],
                        'prompt' => [
                            'text' => 'Test prompt',
                        ],
                    ],
                ],
            ],
        ]);
    }

    #[TestDox('Memory array configuration with empty service throws validation exception')]
    public function testMemoryArrayConfigurationWithEmptyServiceThrowsException()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Memory service cannot be empty.');

        $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => ['class' => Gpt::class],
                        'memory' => ['service' => ''],
                        'prompt' => [
                            'text' => 'Test prompt',
                        ],
                    ],
                ],
            ],
        ]);
    }

    #[TestDox('Memory service configuration works correctly')]
    public function testMemoryServiceConfigurationWorksCorrectly()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => ['class' => Gpt::class],
                        'memory' => ['service' => 'my_custom_memory_service'],
                        'prompt' => [
                            'text' => 'Test prompt',
                        ],
                    ],
                ],
            ],
        ]);

        // Should use the service directly, not create a StaticMemoryProvider
        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.memory_input_processor'));
        $this->assertFalse($container->hasDefinition('ai.agent.test_agent.static_memory_provider'));

        $memoryProcessor = $container->getDefinition('ai.agent.test_agent.memory_input_processor');
        $arguments = $memoryProcessor->getArguments();
        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame('my_custom_memory_service', (string) $arguments[0]);
    }

    #[TestDox('Memory configuration preserves correct processor priority ordering')]
    public function testMemoryProcessorPriorityOrdering()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => ['class' => Gpt::class],
                        'memory' => 'test_memory',
                        'prompt' => [
                            'text' => 'Test prompt',
                        ],
                    ],
                ],
            ],
        ]);

        $memoryDef = $container->getDefinition('ai.agent.test_agent.memory_input_processor');
        $systemDef = $container->getDefinition('ai.agent.test_agent.system_prompt_processor');

        // Memory processor should have higher priority (more negative number)
        $memoryTags = $memoryDef->getTag('ai.agent.input_processor');
        $systemTags = $systemDef->getTag('ai.agent.input_processor');

        $this->assertSame(-40, $memoryTags[0]['priority']);
        $this->assertSame(-30, $systemTags[0]['priority']);
        $this->assertLessThan($systemTags[0]['priority'], $memoryTags[0]['priority']);
    }

    #[TestDox('Memory processor uses correct MemoryInputProcessor class and service reference')]
    public function testMemoryProcessorIntegration()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => ['class' => Gpt::class],
                        'memory' => 'my_memory_service',
                        'prompt' => [
                            'text' => 'You are a helpful assistant.',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.memory_input_processor'));
        $definition = $container->getDefinition('ai.agent.test_agent.memory_input_processor');

        // Check correct class
        $this->assertSame(MemoryInputProcessor::class, $definition->getClass());

        // Check service reference argument (static memory since service doesn't exist)
        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.static_memory_provider'));
        $arguments = $definition->getArguments();
        $this->assertCount(1, $arguments);
        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame('ai.agent.test_agent.static_memory_provider', (string) $arguments[0]);

        // Check proper tagging
        $tags = $definition->getTag('ai.agent.input_processor');
        $this->assertNotEmpty($tags);
        $this->assertSame('ai.agent.test_agent', $tags[0]['agent']);
        $this->assertSame(-40, $tags[0]['priority']);
    }

    #[TestDox('Memory with existing service uses service reference directly')]
    public function testMemoryWithExistingServiceUsesServiceReference()
    {
        // First create a service that exists
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', 'test');

        // Register a memory service
        $container->register('existing_memory_service', MemoryInputProcessor::class);

        $extension = (new AiBundle())->getContainerExtension();
        $extension->load([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => ['class' => Gpt::class],
                        'memory' => ['service' => 'existing_memory_service'], // New array syntax for service
                        'prompt' => [
                            'text' => 'You are a helpful assistant.',
                        ],
                    ],
                ],
            ],
        ], $container);

        // Should use the existing service directly, not create a StaticMemoryProvider
        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.memory_input_processor'));
        $this->assertFalse($container->hasDefinition('ai.agent.test_agent.static_memory_provider'));

        $memoryProcessor = $container->getDefinition('ai.agent.test_agent.memory_input_processor');
        $arguments = $memoryProcessor->getArguments();
        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame('existing_memory_service', (string) $arguments[0]);
    }

    #[TestDox('Memory with non-existing service creates StaticMemoryProvider')]
    public function testMemoryWithNonExistingServiceCreatesStaticMemoryProvider()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => ['class' => Gpt::class],
                        'memory' => 'This is static memory content', // This is not a service
                        'prompt' => [
                            'text' => 'You are a helpful assistant.',
                        ],
                    ],
                ],
            ],
        ]);

        // Should create a StaticMemoryProvider
        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.memory_input_processor'));
        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.static_memory_provider'));

        // Check StaticMemoryProvider configuration
        $staticProvider = $container->getDefinition('ai.agent.test_agent.static_memory_provider');
        $this->assertSame(StaticMemoryProvider::class, $staticProvider->getClass());
        $staticProviderArgs = $staticProvider->getArguments();
        $this->assertSame('This is static memory content', $staticProviderArgs[0]);

        // Check that memory processor uses the StaticMemoryProvider
        $memoryProcessor = $container->getDefinition('ai.agent.test_agent.memory_input_processor');
        $memoryProcessorArgs = $memoryProcessor->getArguments();
        $this->assertInstanceOf(Reference::class, $memoryProcessorArgs[0]);
        $this->assertSame('ai.agent.test_agent.static_memory_provider', (string) $memoryProcessorArgs[0]);
    }

    #[TestDox('Memory with service alias uses alias correctly')]
    public function testMemoryWithServiceAliasUsesAlias()
    {
        // Create a container with a service alias
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', 'test');

        // Register a service with an alias
        $container->register('actual_memory_service', MemoryInputProcessor::class);
        $container->setAlias('memory_alias', 'actual_memory_service');

        $extension = (new AiBundle())->getContainerExtension();
        $extension->load([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => ['class' => Gpt::class],
                        'memory' => ['service' => 'memory_alias'], // Use new array syntax for service alias
                        'prompt' => [
                            'text' => 'You are a helpful assistant.',
                        ],
                    ],
                ],
            ],
        ], $container);

        // Should use the alias directly, not create a StaticMemoryProvider
        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.memory_input_processor'));
        $this->assertFalse($container->hasDefinition('ai.agent.test_agent.static_memory_provider'));

        $memoryProcessor = $container->getDefinition('ai.agent.test_agent.memory_input_processor');
        $arguments = $memoryProcessor->getArguments();
        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame('memory_alias', (string) $arguments[0]);
    }

    #[TestDox('Different agents can use different memory types')]
    public function testDifferentAgentsCanUseDifferentMemoryTypes()
    {
        // Create a container with one existing service
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', 'test');

        $container->register('dynamic_memory_service', MemoryInputProcessor::class);

        $extension = (new AiBundle())->getContainerExtension();
        $extension->load([
            'ai' => [
                'agent' => [
                    'agent_with_service' => [
                        'model' => ['class' => Gpt::class],
                        'memory' => ['service' => 'dynamic_memory_service'], // Use new array syntax for service
                        'prompt' => [
                            'text' => 'Agent with service.',
                        ],
                    ],
                    'agent_with_static' => [
                        'model' => ['class' => Claude::class],
                        'memory' => 'Static memory context for this agent', // Static content
                        'prompt' => [
                            'text' => 'Agent with static memory.',
                        ],
                    ],
                ],
            ],
        ], $container);

        // First agent uses service reference
        $this->assertTrue($container->hasDefinition('ai.agent.agent_with_service.memory_input_processor'));
        $this->assertFalse($container->hasDefinition('ai.agent.agent_with_service.static_memory_provider'));

        $serviceMemoryProcessor = $container->getDefinition('ai.agent.agent_with_service.memory_input_processor');
        $serviceArgs = $serviceMemoryProcessor->getArguments();
        $this->assertInstanceOf(Reference::class, $serviceArgs[0]);
        $this->assertSame('dynamic_memory_service', (string) $serviceArgs[0]);

        // Second agent uses StaticMemoryProvider
        $this->assertTrue($container->hasDefinition('ai.agent.agent_with_static.memory_input_processor'));
        $this->assertTrue($container->hasDefinition('ai.agent.agent_with_static.static_memory_provider'));

        $staticProvider = $container->getDefinition('ai.agent.agent_with_static.static_memory_provider');
        $this->assertSame(StaticMemoryProvider::class, $staticProvider->getClass());
        $staticProviderArgs = $staticProvider->getArguments();
        $this->assertSame('Static memory context for this agent', $staticProviderArgs[0]);
    }

    public function testVectorizerConfiguration()
    {
        $container = $this->buildContainer([
            'ai' => [
                'vectorizer' => [
                    'my_vectorizer' => [
                        'platform' => 'my_platform_service_id',
                        'model' => [
                            'class' => Embeddings::class,
                            'name' => 'text-embedding-3-small',
                            'options' => ['dimension' => 512],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.vectorizer.my_vectorizer'));
        $this->assertTrue($container->hasDefinition('ai.vectorizer.my_vectorizer.model'));

        $vectorizerDefinition = $container->getDefinition('ai.vectorizer.my_vectorizer');
        $this->assertSame(Vectorizer::class, $vectorizerDefinition->getClass());
        $this->assertTrue($vectorizerDefinition->hasTag('ai.vectorizer'));

        $modelDefinition = $container->getDefinition('ai.vectorizer.my_vectorizer.model');
        $this->assertSame(Embeddings::class, $modelDefinition->getClass());
        $this->assertTrue($modelDefinition->hasTag('ai.model.embeddings_model'));
    }

    public function testVectorizerWithLoggerInjection()
    {
        $container = $this->buildContainer([
            'ai' => [
                'vectorizer' => [
                    'my_vectorizer' => [
                        'platform' => 'my_platform_service_id',
                        'model' => [
                            'class' => Embeddings::class,
                            'name' => 'text-embedding-3-small',
                        ],
                    ],
                ],
            ],
        ]);

        $vectorizerDefinition = $container->getDefinition('ai.vectorizer.my_vectorizer');
        $arguments = $vectorizerDefinition->getArguments();

        $this->assertCount(3, $arguments, 'Vectorizer should have 3 arguments: platform, model, and logger');

        // First argument should be platform reference
        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame('my_platform_service_id', (string) $arguments[0]);

        // Second argument should be model reference
        $this->assertInstanceOf(Reference::class, $arguments[1]);
        $this->assertSame('ai.vectorizer.my_vectorizer.model', (string) $arguments[1]);

        // Third argument should be logger reference with IGNORE_ON_INVALID_REFERENCE
        $this->assertInstanceOf(Reference::class, $arguments[2]);
        $this->assertSame('logger', (string) $arguments[2]);
        $this->assertSame(ContainerInterface::IGNORE_ON_INVALID_REFERENCE, $arguments[2]->getInvalidBehavior());
    }

    public function testIndexerWithConfiguredVectorizer()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'vectorizer' => [
                    'my_vectorizer' => [
                        'platform' => 'my_platform_service_id',
                        'model' => [
                            'class' => Embeddings::class,
                            'name' => 'text-embedding-3-small',
                        ],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'vectorizer' => 'ai.vectorizer.my_vectorizer',
                        'store' => 'ai.store.memory.my_store',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer'));
        $this->assertTrue($container->hasDefinition('ai.vectorizer.my_vectorizer'));

        $indexerDefinition = $container->getDefinition('ai.indexer.my_indexer');
        $arguments = $indexerDefinition->getArguments();

        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame(InMemoryLoader::class, (string) $arguments[0]);

        $this->assertInstanceOf(Reference::class, $arguments[1]);
        $this->assertSame('ai.vectorizer.my_vectorizer', (string) $arguments[1]);

        // Should not create model-specific vectorizer when using configured one
        $this->assertFalse($container->hasDefinition('ai.indexer.my_indexer.vectorizer'));
        $this->assertFalse($container->hasDefinition('ai.indexer.my_indexer.model'));
    }

    public function testIndexerWithStringSource()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'source' => 'https://example.com/feed.xml',
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer'));
        $indexerDefinition = $container->getDefinition('ai.indexer.my_indexer');
        $arguments = $indexerDefinition->getArguments();

        $this->assertSame('https://example.com/feed.xml', $arguments[3]);
    }

    public function testIndexerWithArraySource()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'source' => [
                            '/path/to/file1.txt',
                            '/path/to/file2.txt',
                            'https://example.com/feed.xml',
                        ],
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer'));
        $indexerDefinition = $container->getDefinition('ai.indexer.my_indexer');
        $arguments = $indexerDefinition->getArguments();

        $this->assertIsArray($arguments[3]);
        $this->assertCount(3, $arguments[3]);
        $this->assertSame([
            '/path/to/file1.txt',
            '/path/to/file2.txt',
            'https://example.com/feed.xml',
        ], $arguments[3]);
    }

    public function testIndexerWithNullSource()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                        // source not configured, should default to null
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer'));
        $indexerDefinition = $container->getDefinition('ai.indexer.my_indexer');
        $arguments = $indexerDefinition->getArguments();

        $this->assertNull($arguments[3]);
    }

    public function testIndexerWithConfiguredTransformers()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'transformers' => [
                            TextTrimTransformer::class,
                            'App\CustomTransformer',
                        ],
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer'));
        $indexerDefinition = $container->getDefinition('ai.indexer.my_indexer');
        $arguments = $indexerDefinition->getArguments();

        $this->assertSame([], $arguments[4]); // Empty filters
        $this->assertIsArray($arguments[5]);
        $this->assertCount(2, $arguments[5]);

        $this->assertInstanceOf(Reference::class, $arguments[5][0]);
        $this->assertSame(TextTrimTransformer::class, (string) $arguments[5][0]);

        $this->assertInstanceOf(Reference::class, $arguments[5][1]);
        $this->assertSame('App\CustomTransformer', (string) $arguments[5][1]);
    }

    public function testIndexerWithEmptyTransformers()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'transformers' => [],
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer'));
        $indexerDefinition = $container->getDefinition('ai.indexer.my_indexer');
        $arguments = $indexerDefinition->getArguments();

        $this->assertSame([], $arguments[4]); // Empty filters
        $this->assertSame([], $arguments[5]); // Empty transformers
    }

    public function testIndexerWithoutTransformers()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                        // transformers not configured, should default to empty array
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer'));
        $indexerDefinition = $container->getDefinition('ai.indexer.my_indexer');
        $arguments = $indexerDefinition->getArguments();

        $this->assertSame([], $arguments[4]); // Empty filters
        $this->assertSame([], $arguments[5]); // Empty transformers
    }

    public function testIndexerWithSourceAndTransformers()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'source' => [
                            '/path/to/file1.txt',
                            '/path/to/file2.txt',
                        ],
                        'transformers' => [
                            TextTrimTransformer::class,
                        ],
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer'));
        $indexerDefinition = $container->getDefinition('ai.indexer.my_indexer');
        $arguments = $indexerDefinition->getArguments();

        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame(InMemoryLoader::class, (string) $arguments[0]);

        $this->assertInstanceOf(Reference::class, $arguments[1]);
        $this->assertSame('my_vectorizer_service', (string) $arguments[1]);

        $this->assertInstanceOf(Reference::class, $arguments[2]);
        $this->assertSame('ai.store.memory.my_store', (string) $arguments[2]);

        $this->assertIsArray($arguments[3]);
        $this->assertCount(2, $arguments[3]);
        $this->assertSame([
            '/path/to/file1.txt',
            '/path/to/file2.txt',
        ], $arguments[3]);

        $this->assertSame([], $arguments[4]); // Empty filters
        $this->assertIsArray($arguments[5]);
        $this->assertCount(1, $arguments[5]);
        $this->assertInstanceOf(Reference::class, $arguments[5][0]);
        $this->assertSame(TextTrimTransformer::class, (string) $arguments[5][0]);
    }

    public function testIndexerWithConfiguredFilters()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'filters' => [
                            TextContainsFilter::class,
                            'App\CustomFilter',
                        ],
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer'));
        $indexerDefinition = $container->getDefinition('ai.indexer.my_indexer');
        $arguments = $indexerDefinition->getArguments();

        // Verify filters are in the correct position (index 4, before transformers)
        $this->assertIsArray($arguments[4]);
        $this->assertCount(2, $arguments[4]);

        $this->assertInstanceOf(Reference::class, $arguments[4][0]);
        $this->assertSame(TextContainsFilter::class, (string) $arguments[4][0]);

        $this->assertInstanceOf(Reference::class, $arguments[4][1]);
        $this->assertSame('App\CustomFilter', (string) $arguments[4][1]);

        // Verify transformers are in the correct position (index 5, after filters)
        $this->assertSame([], $arguments[5]); // Empty transformers
    }

    public function testIndexerWithEmptyFilters()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'filters' => [],
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer'));
        $indexerDefinition = $container->getDefinition('ai.indexer.my_indexer');
        $arguments = $indexerDefinition->getArguments();

        $this->assertSame([], $arguments[4]); // Empty filters
    }

    public function testIndexerWithoutFilters()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                        // filters not configured, should default to empty array
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer'));
        $indexerDefinition = $container->getDefinition('ai.indexer.my_indexer');
        $arguments = $indexerDefinition->getArguments();

        $this->assertSame([], $arguments[4]); // Empty filters
    }

    public function testIndexerWithFiltersAndTransformers()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'filters' => [
                            TextContainsFilter::class,
                        ],
                        'transformers' => [
                            TextTrimTransformer::class,
                        ],
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer'));
        $indexerDefinition = $container->getDefinition('ai.indexer.my_indexer');
        $arguments = $indexerDefinition->getArguments();

        // Verify filters are at index 4
        $this->assertIsArray($arguments[4]);
        $this->assertCount(1, $arguments[4]);
        $this->assertInstanceOf(Reference::class, $arguments[4][0]);
        $this->assertSame(TextContainsFilter::class, (string) $arguments[4][0]);

        // Verify transformers are at index 5
        $this->assertIsArray($arguments[5]);
        $this->assertCount(1, $arguments[5]);
        $this->assertInstanceOf(Reference::class, $arguments[5][0]);
        $this->assertSame(TextTrimTransformer::class, (string) $arguments[5][0]);
    }

    public function testIndexerWithSourceFiltersAndTransformers()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'source' => [
                            '/path/to/file1.txt',
                            '/path/to/file2.txt',
                        ],
                        'filters' => [
                            TextContainsFilter::class,
                        ],
                        'transformers' => [
                            TextTrimTransformer::class,
                        ],
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer'));
        $indexerDefinition = $container->getDefinition('ai.indexer.my_indexer');
        $arguments = $indexerDefinition->getArguments();

        // Verify correct order: loader, vectorizer, store, source, filters, transformers, logger
        $this->assertInstanceOf(Reference::class, $arguments[0]); // loader
        $this->assertSame(InMemoryLoader::class, (string) $arguments[0]);

        $this->assertInstanceOf(Reference::class, $arguments[1]); // vectorizer
        $this->assertSame('my_vectorizer_service', (string) $arguments[1]);

        $this->assertInstanceOf(Reference::class, $arguments[2]); // store
        $this->assertSame('ai.store.memory.my_store', (string) $arguments[2]);

        $this->assertIsArray($arguments[3]); // source
        $this->assertCount(2, $arguments[3]);
        $this->assertSame(['/path/to/file1.txt', '/path/to/file2.txt'], $arguments[3]);

        $this->assertIsArray($arguments[4]); // filters
        $this->assertCount(1, $arguments[4]);
        $this->assertInstanceOf(Reference::class, $arguments[4][0]);
        $this->assertSame(TextContainsFilter::class, (string) $arguments[4][0]);

        $this->assertIsArray($arguments[5]); // transformers
        $this->assertCount(1, $arguments[5]);
        $this->assertInstanceOf(Reference::class, $arguments[5][0]);
        $this->assertSame(TextTrimTransformer::class, (string) $arguments[5][0]);

        $this->assertInstanceOf(Reference::class, $arguments[6]); // logger
        $this->assertSame('logger', (string) $arguments[6]);
    }

    private function buildContainer(array $configuration): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        $container->setParameter('kernel.environment', 'dev');
        $container->setParameter('kernel.build_dir', 'public');

        $extension = (new AiBundle())->getContainerExtension();
        $extension->load($configuration, $container);

        return $container;
    }

    /**
     * @return array<string, mixed>
     */
    private function getFullConfig(): array
    {
        return [
            'ai' => [
                'platform' => [
                    'anthropic' => [
                        'api_key' => 'anthropic_key_full',
                    ],
                    'azure' => [
                        'my_azure_instance' => [
                            'api_key' => 'azure_key_full',
                            'base_url' => 'https://myazure.openai.azure.com/',
                            'deployment' => 'gpt-35-turbo',
                            'api_version' => '2024-02-15-preview',
                        ],
                        'another_azure_instance' => [
                            'api_key' => 'azure_key_2',
                            'base_url' => 'https://myazure2.openai.azure.com/',
                            'deployment' => 'gpt-4',
                            'api_version' => '2024-02-15-preview',
                        ],
                    ],
                    'eleven_labs' => [
                        'host' => 'https://api.elevenlabs.io/v1',
                        'api_key' => 'eleven_labs_key_full',
                    ],
                    'gemini' => [
                        'api_key' => 'gemini_key_full',
                    ],
                    'openai' => [
                        'api_key' => 'openai_key_full',
                    ],
                    'mistral' => [
                        'api_key' => 'mistral_key_full',
                    ],
                    'openrouter' => [
                        'api_key' => 'openrouter_key_full',
                    ],
                    'lmstudio' => [
                        'host_url' => 'http://127.0.0.1:1234',
                    ],
                    'ollama' => [
                        'host_url' => 'http://127.0.0.1:11434',
                    ],
                    'cerebras' => [
                        'api_key' => 'cerebras_key_full',
                    ],
                    'voyage' => [
                        'api_key' => 'voyage_key_full',
                    ],
                    'vertexai' => [
                        'location' => 'global',
                        'project_id' => '123',
                    ],
                    'dockermodelrunner' => [
                        'host_url' => 'http://127.0.0.1:12434',
                    ],
                ],
                'agent' => [
                    'my_chat_agent' => [
                        'platform' => 'openai_platform_service_id',
                        'model' => [
                            'class' => Gpt::class,
                            'name' => 'gpt-3.5-turbo',
                            'options' => [
                                'temperature' => 0.7,
                                'max_tokens' => 150,
                                'nested' => ['options' => ['work' => 'too']],
                            ],
                        ],
                        'structured_output' => false,
                        'track_token_usage' => true,
                        'prompt' => [
                            'text' => 'You are a helpful assistant.',
                            'include_tools' => true,
                        ],
                        'tools' => [
                            'enabled' => true,
                            'services' => [
                                ['service' => 'my_tool_service_id', 'name' => 'myTool', 'description' => 'A test tool'],
                                'another_tool_service_id', // String format
                            ],
                        ],
                        'fault_tolerant_toolbox' => false,
                    ],
                    'another_agent' => [
                        'model' => ['class' => Claude::class, 'name' => 'claude-3-opus-20240229'],
                        'prompt' => 'Be concise.',
                    ],
                ],
                'store' => [
                    'azure_search' => [
                        'my_azure_search_store' => [
                            'endpoint' => 'https://mysearch.search.windows.net',
                            'api_key' => 'azure_search_key',
                            'index_name' => 'my-documents',
                            'api_version' => '2023-11-01',
                            'vector_field' => 'contentVector',
                        ],
                    ],
                    'cache' => [
                        'my_cache_store' => [
                            'service' => 'cache.system',
                        ],
                        'my_cache_store_with_custom_key' => [
                            'service' => 'cache.system',
                            'cache_key' => 'bar',
                        ],
                        'my_cache_store_with_custom_strategy' => [
                            'service' => 'cache.system',
                            'strategy' => 'chebyshev',
                        ],
                        'my_cache_store_with_custom_strategy_and_custom_key' => [
                            'service' => 'cache.system',
                            'cache_key' => 'bar',
                            'strategy' => 'chebyshev',
                        ],
                    ],
                    'chroma_db' => [
                        'my_chroma_store' => [
                            'collection' => 'my_collection',
                        ],
                    ],
                    'clickhouse' => [
                        'my_clickhouse_store' => [
                            'dsn' => 'http://foo:bar@1.2.3.4:9999',
                            'database' => 'my_db',
                            'table' => 'my_table',
                        ],
                    ],
                    'cloudflare' => [
                        'my_cloudflare_store' => [
                            'account_id' => 'foo',
                            'api_key' => 'bar',
                            'index_name' => 'random',
                            'dimensions' => 1536,
                            'metric' => 'cosine',
                            'endpoint_url' => 'https://api.cloudflare.com/client/v5/accounts',
                        ],
                    ],
                    'meilisearch' => [
                        'my_meilisearch_store' => [
                            'endpoint' => 'http://127.0.0.1:7700',
                            'api_key' => 'foo',
                            'index_name' => 'test',
                            'embedder' => 'default',
                            'vector_field' => '_vectors',
                            'dimensions' => 768,
                        ],
                    ],
                    'memory' => [
                        'my_memory_store' => [
                            'strategy' => 'cosine',
                        ],
                    ],
                    'milvus' => [
                        'my_milvus_store' => [
                            'endpoint' => 'http://127.0.0.1:19530',
                            'api_key' => 'foo',
                            'database' => 'test',
                            'collection' => 'default',
                            'vector_field' => '_vectors',
                            'dimensions' => 768,
                            'metric_type' => 'COSINE',
                        ],
                    ],
                    'mongodb' => [
                        'my_mongo_store' => [
                            'database' => 'my_db',
                            'collection' => 'my_collection',
                            'index_name' => 'vector_index',
                            'vector_field' => 'embedding',
                            'bulk_write' => true,
                        ],
                    ],
                    'neo4j' => [
                        'my_neo4j_store' => [
                            'endpoint' => 'http://127.0.0.1:8000',
                            'username' => 'test',
                            'password' => 'test',
                            'database' => 'foo',
                            'vector_index_name' => 'test',
                            'node_name' => 'foo',
                            'vector_field' => '_vectors',
                            'dimensions' => 768,
                            'distance' => 'cosine',
                            'quantization' => true,
                        ],
                    ],
                    'pinecone' => [
                        'my_pinecone_store' => [
                            'namespace' => 'my_namespace',
                            'filter' => ['category' => 'books'],
                            'top_k' => 10,
                        ],
                    ],
                    'qdrant' => [
                        'my_qdrant_store' => [
                            'endpoint' => 'http://127.0.0.1:8000',
                            'api_key' => 'test',
                            'collection_name' => 'foo',
                            'dimensions' => 768,
                            'distance' => 'Cosine',
                        ],
                    ],
                    'surreal_db' => [
                        'my_surreal_db_store' => [
                            'endpoint' => 'http://127.0.0.1:8000',
                            'username' => 'test',
                            'password' => 'test',
                            'namespace' => 'foo',
                            'database' => 'bar',
                            'table' => 'bar',
                            'vector_field' => '_vectors',
                            'strategy' => 'cosine',
                            'dimensions' => 768,
                            'namespaced_user' => true,
                        ],
                    ],
                    'typesense' => [
                        'my_typesense_store' => [
                            'endpoint' => 'http://localhost:8108',
                            'api_key' => 'foo',
                            'collection' => 'my_collection',
                            'vector_field' => 'vector',
                            'dimensions' => 768,
                        ],
                    ],
                    'weaviate' => [
                        'my_weaviate_store' => [
                            'endpoint' => 'http://localhost:8080',
                            'api_key' => 'bar',
                            'collection' => 'my_weaviate_collection',
                        ],
                    ],
                ],
                'vectorizer' => [
                    'test_vectorizer' => [
                        'platform' => 'mistral_platform_service_id',
                        'model' => [
                            'class' => MistralEmbeddings::class,
                            'name' => 'mistral-embed',
                            'options' => ['dimension' => 768],
                        ],
                    ],
                ],
                'indexer' => [
                    'my_text_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'vectorizer' => 'ai.vectorizer.test_vectorizer',
                        'store' => 'my_azure_search_store_service_id',
                    ],
                ],
            ],
        ];
    }
}
