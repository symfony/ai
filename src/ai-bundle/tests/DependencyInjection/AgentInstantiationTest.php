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

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Agent\Agent;
use Symfony\AI\AiBundle\AiBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Serializer;

/**
 * The agent is wired with named constructor arguments on purpose, so growing the Agent constructor cannot silently
 * shift a value onto the wrong parameter. These tests compile the container and build the agent, which is what
 * catches such a mismatch - inspecting the definition's arguments would not.
 */
final class AgentInstantiationTest extends TestCase
{
    #[TestDox('A configured agent can actually be instantiated from the compiled container')]
    public function testTheAgentCanBeInstantiated()
    {
        $container = $this->compile([
            'ai' => [
                'platform' => ['openai' => ['api_key' => 'sk-test']],
                'agent' => [
                    'my_agent' => [
                        'model' => 'gpt-4o-mini',
                        'prompt' => ['text' => 'You are a helpful assistant.'],
                    ],
                ],
            ],
        ]);

        $agent = $container->get('ai.agent.my_agent');

        $this->assertInstanceOf(Agent::class, $agent);
        $this->assertSame('my_agent', $agent->getName());
        $this->assertSame('gpt-4o-mini', $agent->getModel());
    }

    #[TestDox('An agent with tools, memory and every tool option can be instantiated')]
    public function testTheFullyConfiguredAgentCanBeInstantiated()
    {
        $container = $this->compile([
            'ai' => [
                'platform' => ['openai' => ['api_key' => 'sk-test']],
                'agent' => [
                    'my_agent' => [
                        'model' => 'gpt-4o-mini',
                        'prompt' => ['text' => 'You are a helpful assistant.', 'include_tools' => true],
                        'tools' => true,
                        'memory' => 'The user likes brief answers.',
                        'max_tool_calls' => 25,
                        'exclude_tool_messages' => true,
                        'include_sources' => true,
                    ],
                ],
            ],
        ]);

        $agent = $container->get('ai.agent.my_agent');

        $this->assertInstanceOf(Agent::class, $agent);
        $this->assertSame('my_agent', $agent->getName());
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function compile(array $configuration): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', 'public');
        $container->setDefinition('http_client', new Definition(NativeHttpClient::class));
        $container->setDefinition('serializer', new Definition(Serializer::class));
        $container->setDefinition('logger', new Definition(NullLogger::class));
        $container->setDefinition('event_dispatcher', new Definition(EventDispatcher::class));
        $container->setDefinition('property_info', new Definition(PropertyInfoExtractor::class));
        $container->setDefinition('property_info.reflection_extractor', new Definition(ReflectionExtractor::class));
        $container->setDefinition('serializer.mapping.class_metadata_factory', new Definition(ClassMetadataFactory::class, [new Definition(AttributeLoader::class)]));

        $bundle = new AiBundle();
        $bundle->getContainerExtension()->load($configuration, $container);
        $bundle->build($container);

        $container->getDefinition('ai.agent.my_agent')->setPublic(true);
        $container->compile();

        return $container;
    }
}
