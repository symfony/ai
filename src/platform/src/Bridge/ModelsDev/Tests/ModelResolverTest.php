<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ModelsDev\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\ModelsDev\ModelResolver;
use Symfony\AI\Platform\Bridge\ModelsDev\ProviderRegistry;
use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class ModelResolverTest extends TestCase
{
    public function testExplicitProviderColonModel()
    {
        $resolver = new ModelResolver();
        $result = $resolver->resolve('anthropic::claude-opus-4-5');

        $this->assertSame('anthropic', $result['provider']);
        $this->assertSame('claude-opus-4-5', $result['model_id']);
    }

    public function testExplicitProviderColonModelWithSlashInModelId()
    {
        // OpenRouter uses slash-separated namespaced model IDs
        $resolver = new ModelResolver();
        $result = $resolver->resolve('openrouter::meta-llama/llama-3.3-70b-instruct');

        $this->assertSame('openrouter', $result['provider']);
        $this->assertSame('meta-llama/llama-3.3-70b-instruct', $result['model_id']);
    }

    public function testExplicitFormPreservesEverythingAfterFirstColon()
    {
        // Only the first colon is the delimiter; subsequent colons belong to the model ID
        $resolver = new ModelResolver();
        $result = $resolver->resolve('groq::llama-3.3-70b:free');

        $this->assertSame('groq', $result['provider']);
        $this->assertSame('llama-3.3-70b:free', $result['model_id']);
    }

    public function testExplicitFormWithUnknownProviderIsAllowed()
    {
        // Unknown providers are fine in explicit form — validation happens later
        $resolver = new ModelResolver();
        $result = $resolver->resolve('my-local-server::custom-model');

        $this->assertSame('my-local-server', $result['provider']);
        $this->assertSame('custom-model', $result['model_id']);
    }

    public function testExplicitFormThrowsOnEmptyProvider()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('provider part is empty');

        $resolver = new ModelResolver();
        $resolver->resolve('::claude-opus-4-5');
    }

    public function testExplicitFormThrowsOnEmptyModelId()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('model ID part is empty');

        $resolver = new ModelResolver();
        $resolver->resolve('anthropic::');
    }

    public function testProviderOnlyReturnsFirstModel()
    {
        $resolver = new ModelResolver();
        $result = $resolver->resolve('anthropic');

        $this->assertSame('anthropic', $result['provider']);
        $this->assertNotEmpty($result['model_id']);
    }

    public function testProviderOnlyReturnsFirstModelForOpenAI()
    {
        $resolver = new ModelResolver();
        $result = $resolver->resolve('openai');

        $this->assertSame('openai', $result['provider']);
        $this->assertNotEmpty($result['model_id']);
    }

    public function testProviderOnlyReturnsFirstModelForGoogle()
    {
        $resolver = new ModelResolver();
        $result = $resolver->resolve('google');

        $this->assertSame('google', $result['provider']);
        $this->assertNotEmpty($result['model_id']);
    }

    public function testProviderOnlyReturnsFirstModelForGroq()
    {
        $resolver = new ModelResolver();
        $result = $resolver->resolve('groq');

        $this->assertSame('groq', $result['provider']);
        $this->assertNotEmpty($result['model_id']);
    }

    public function testProviderOnlyReturnsFirstModelForDeepSeek()
    {
        $resolver = new ModelResolver();
        $result = $resolver->resolve('deepseek');

        $this->assertSame('deepseek', $result['provider']);
        $this->assertNotEmpty($result['model_id']);
    }

    public function testBareModelIdInfersAnthropicProvider()
    {
        $resolver = new ModelResolver();
        $result = $resolver->resolve('claude-opus-4-5');

        $this->assertSame('anthropic', $result['provider']);
        $this->assertSame('claude-opus-4-5', $result['model_id']);
    }

    public function testBareModelIdInfersOpenAIProvider()
    {
        $resolver = new ModelResolver();
        $result = $resolver->resolve('gpt-4o');

        $this->assertSame('openai', $result['provider']);
        $this->assertSame('gpt-4o', $result['model_id']);
    }

    public function testBareModelIdInfersGoogleProvider()
    {
        $resolver = new ModelResolver();
        $result = $resolver->resolve('gemini-2.5-pro');

        $this->assertSame('google', $result['provider']);
        $this->assertSame('gemini-2.5-pro', $result['model_id']);
    }

    public function testBareModelIdInfersMistralProvider()
    {
        $resolver = new ModelResolver();
        $result = $resolver->resolve('mistral-large-latest');

        $this->assertSame('mistral', $result['provider']);
        $this->assertSame('mistral-large-latest', $result['model_id']);
    }

    public function testBareModelIdInfersXAIProvider()
    {
        $resolver = new ModelResolver();
        $result = $resolver->resolve('grok-4');

        $this->assertSame('xai', $result['provider']);
        $this->assertSame('grok-4', $result['model_id']);
    }

    public function testBareModelIdInfersDeepSeekProvider()
    {
        $resolver = new ModelResolver();
        $result = $resolver->resolve('deepseek-chat');

        $this->assertSame('deepseek', $result['provider']);
        $this->assertSame('deepseek-chat', $result['model_id']);
    }

    public function testBareModelIdThrowsForCompletelyUnknownModel()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot determine provider for model');
        $this->expectExceptionMessage('"completely-unknown-model-xyz-404"');

        $resolver = new ModelResolver();
        $resolver->resolve('completely-unknown-model-xyz-404');
    }

    public function testErrorMessageSuggestsExplicitFormat()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Use "provider::model" format');

        $resolver = new ModelResolver();
        $resolver->resolve('nonexistent-model-abc');
    }

    public function testCustomDataPathIsUsed()
    {
        $fixturePath = $this->createFixtureFile([
            'my-provider' => [
                'id' => 'my-provider',
                'name' => 'My Provider',
                'api' => 'https://api.my-provider.example.com/v1',
                'models' => [
                    'my-model-v1' => [
                        'id' => 'my-model-v1',
                        'name' => 'My Model v1',
                        'family' => 'my-model',
                        'attachment' => false,
                        'reasoning' => false,
                        'tool_call' => true,
                        'temperature' => true,
                        'modalities' => ['input' => ['text'], 'output' => ['text']],
                        'cost' => ['input' => 1.0, 'output' => 2.0],
                        'limit' => ['context' => 8192, 'output' => 4096],
                    ],
                    'my-model-v2' => [
                        'id' => 'my-model-v2',
                        'name' => 'My Model v2',
                        'family' => 'my-model',
                        'attachment' => false,
                        'reasoning' => false,
                        'tool_call' => true,
                        'temperature' => true,
                        'modalities' => ['input' => ['text'], 'output' => ['text']],
                        'cost' => ['input' => 1.5, 'output' => 3.0],
                        'limit' => ['context' => 16384, 'output' => 8192],
                    ],
                ],
            ],
        ]);

        try {
            $resolver = new ModelResolver(dataPath: $fixturePath);

            // Provider-only: should return first model
            $result = $resolver->resolve('my-provider');
            $this->assertSame('my-provider', $result['provider']);
            $this->assertSame('my-model-v1', $result['model_id']);

            // Bare model ID: should find the provider
            $result = $resolver->resolve('my-model-v2');
            $this->assertSame('my-provider', $result['provider']);
            $this->assertSame('my-model-v2', $result['model_id']);

            // Explicit form: always works
            $result = $resolver->resolve('my-provider::my-model-v1');
            $this->assertSame('my-provider', $result['provider']);
            $this->assertSame('my-model-v1', $result['model_id']);
        } finally {
            unlink($fixturePath);
        }
    }

    public function testAcceptsExternalProviderRegistry()
    {
        // The resolver should accept an externally constructed registry
        // (e.g. with a custom data path) without creating its own.
        $fixturePath = $this->createFixtureFile([
            'external-provider' => [
                'id' => 'external-provider',
                'name' => 'External',
                'api' => 'https://api.external.example.com/v1',
                'models' => [
                    'ext-model-1' => [
                        'id' => 'ext-model-1',
                        'name' => 'Ext Model 1',
                        'family' => 'ext',
                        'attachment' => false,
                        'reasoning' => false,
                        'tool_call' => false,
                        'temperature' => true,
                        'modalities' => ['input' => ['text'], 'output' => ['text']],
                        'cost' => ['input' => 0.5, 'output' => 1.0],
                        'limit' => ['context' => 4096, 'output' => 2048],
                    ],
                ],
            ],
        ]);

        try {
            $registry = new ProviderRegistry($fixturePath);
            $resolver = new ModelResolver($registry, $fixturePath);

            $result = $resolver->resolve('external-provider');
            $this->assertSame('external-provider', $result['provider']);
            $this->assertSame('ext-model-1', $result['model_id']);
        } finally {
            unlink($fixturePath);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createFixtureFile(array $data): string
    {
        $path = sys_get_temp_dir().'/model-resolver-test-'.uniqid().'.json';
        file_put_contents($path, json_encode($data, \JSON_THROW_ON_ERROR));

        return $path;
    }
}
