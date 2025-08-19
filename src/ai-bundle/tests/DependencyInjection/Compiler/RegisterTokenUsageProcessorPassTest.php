<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\AiBundle\DependencyInjection\Compiler\RegisterTokenUsageProcessorPass;
use Symfony\AI\Platform\Result\Metadata\TokenUsage\AsTokenUsageProcessor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
#[CoversClass(RegisterTokenUsageProcessorPass::class)]
#[UsesClass(ContainerBuilder::class)]
#[UsesClass(Definition::class)]
class RegisterTokenUsageProcessorPassTest extends TestCase
{
    public function testProcessRegistersTokenUsageProcessors(): void
    {
        // Arrange
        $container = new ContainerBuilder();

        $processorDefinition = new Definition(MockOpenAiTokenProcessor::class);
        $container->setDefinition('mock.token.processor', $processorDefinition);
        $container->getDefinition('mock.token.processor')->addTag('ai.agent.output_processor');

        $pass = new RegisterTokenUsageProcessorPass();

        // Act
        $pass->process($container);

        // Assert
        $this->assertTrue($container->hasAlias('ai.platform.token_usage_processor.openai'));
        $this->assertEquals('mock.token.processor', (string) $container->getAlias('ai.platform.token_usage_processor.openai'));
    }

    public function testProcessIgnoresProcessorsWithoutAttribute(): void
    {
        // Arrange
        $container = new ContainerBuilder();

        $processorDefinition = new Definition(MockProcessorWithoutAttribute::class);
        $container->setDefinition('mock.processor.no.attribute', $processorDefinition);
        $container->getDefinition('mock.processor.no.attribute')->addTag('ai.agent.output_processor');

        $pass = new RegisterTokenUsageProcessorPass();

        // Act
        $pass->process($container);

        // Assert
        $this->assertFalse($container->hasAlias('ai.platform.token_usage_processor.openai'));
        $this->assertFalse($container->hasAlias('ai.platform.token_usage_processor.mistral'));
    }

    public function testProcessHandlesMultiplePlatforms(): void
    {
        // Arrange
        $container = new ContainerBuilder();

        $openaiProcessor = new Definition(MockOpenAiTokenProcessor::class);
        $container->setDefinition('openai.processor', $openaiProcessor);
        $container->getDefinition('openai.processor')->addTag('ai.agent.output_processor');

        $mistralProcessor = new Definition(MockMistralTokenProcessor::class);
        $container->setDefinition('mistral.processor', $mistralProcessor);
        $container->getDefinition('mistral.processor')->addTag('ai.agent.output_processor');

        $pass = new RegisterTokenUsageProcessorPass();

        // Act
        $pass->process($container);

        // Assert
        $this->assertTrue($container->hasAlias('ai.platform.token_usage_processor.openai'));
        $this->assertTrue($container->hasAlias('ai.platform.token_usage_processor.mistral'));
        $this->assertEquals('openai.processor', (string) $container->getAlias('ai.platform.token_usage_processor.openai'));
        $this->assertEquals('mistral.processor', (string) $container->getAlias('ai.platform.token_usage_processor.mistral'));
    }
}

#[AsTokenUsageProcessor('openai')]
class MockOpenAiTokenProcessor
{
}

#[AsTokenUsageProcessor('mistral')]
class MockMistralTokenProcessor
{
}

class MockProcessorWithoutAttribute
{
}

