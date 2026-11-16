<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\HuggingFace\Classification;
use Symfony\AI\Platform\Bridge\HuggingFace\Completions;
use Symfony\AI\Platform\Bridge\HuggingFace\Embeddings;
use Symfony\AI\Platform\Bridge\HuggingFace\ModelMapper;
use Symfony\AI\Platform\Bridge\HuggingFace\Reranker;
use Symfony\AI\Platform\Bridge\HuggingFace\SpeechRecognition;
use Symfony\AI\Platform\Bridge\HuggingFace\TextToImage;
use Symfony\AI\Platform\Capability;

/**
 * @phpstan-import-type HubModel from ModelMapper
 */
final class ModelMapperTest extends TestCase
{
    public function testMapsChatModelWithFeaturesOfAllLiveProviders()
    {
        $definition = ModelMapper::map(self::chatModel());

        $this->assertSame(Completions::class, $definition['class'] ?? null);
        $this->assertSame([
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::TOOL_CALLING,
            Capability::OUTPUT_STRUCTURED,
        ], $definition['capabilities'] ?? null);
    }

    public function testMapsChatModelWithFeaturesOfTheGivenProviderOnly()
    {
        $definition = ModelMapper::map(self::chatModel(), 'novita');

        $this->assertSame(Completions::class, $definition['class'] ?? null);
        $this->assertSame([Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING], $definition['capabilities'] ?? null);
    }

    public function testIgnoresProvidersThatAreNotLive()
    {
        $this->assertNull(ModelMapper::map(self::chatModel(), 'groq'));
    }

    public function testReturnsNullForProviderThatDoesNotServeTheModel()
    {
        $this->assertNull(ModelMapper::map(self::chatModel(), 'cerebras'));
    }

    public function testAddsImageInputForImageTextToTextModels()
    {
        $definition = ModelMapper::map([
            'id' => 'Qwen/Qwen2.5-VL-7B-Instruct',
            'pipeline_tag' => 'image-text-to-text',
            'inferenceProviderMapping' => [
                ['provider' => 'hyperbolic', 'status' => 'live', 'task' => 'conversational', 'type' => 'single-model'],
            ],
        ]);

        $this->assertSame([Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::INPUT_IMAGE], $definition['capabilities'] ?? null);
    }

    public function testMapsMappingsKeyedByProvider()
    {
        $definition = ModelMapper::map([
            'id' => 'Qwen/Qwen3-32B',
            'pipeline_tag' => 'text-generation',
            'inferenceProviderMapping' => [
                'nscale' => ['status' => 'live', 'task' => 'conversational'],
                'groq' => ['status' => 'error', 'task' => 'conversational'],
            ],
        ], 'nscale');

        $this->assertSame(Completions::class, $definition['class'] ?? null);
    }

    /**
     * @param class-string     $expectedClass
     * @param list<Capability> $expectedCapabilities
     */
    #[DataProvider('pipelineProvider')]
    public function testMapsPipelineTasksOfTheInferenceService(string $task, string $expectedClass, array $expectedCapabilities)
    {
        $definition = ModelMapper::map(self::pipelineModel($task));

        $this->assertSame($expectedClass, $definition['class'] ?? null);
        $this->assertSame($expectedCapabilities, $definition['capabilities'] ?? null);
    }

    /**
     * @return iterable<string, array{string, class-string, list<Capability>}>
     */
    public static function pipelineProvider(): iterable
    {
        yield 'feature extraction' => ['feature-extraction', Embeddings::class, [Capability::INPUT_TEXT, Capability::EMBEDDINGS]];
        yield 'sentence similarity' => ['sentence-similarity', Embeddings::class, [Capability::INPUT_TEXT, Capability::EMBEDDINGS]];
        yield 'text ranking' => ['text-ranking', Reranker::class, [Capability::INPUT_MULTIPLE, Capability::RERANKING]];
        yield 'text classification' => ['text-classification', Classification::class, [Capability::INPUT_TEXT]];
        yield 'image classification' => ['image-classification', Classification::class, [Capability::INPUT_IMAGE]];
        yield 'speech recognition' => ['automatic-speech-recognition', SpeechRecognition::class, [Capability::INPUT_AUDIO, Capability::OUTPUT_TEXT, Capability::SPEECH_TO_TEXT]];
        yield 'text to image' => ['text-to-image', TextToImage::class, [Capability::INPUT_TEXT, Capability::OUTPUT_IMAGE, Capability::TEXT_TO_IMAGE]];
    }

    public function testMapsPipelineModelsIndependentOfTheChatProvider()
    {
        $definition = ModelMapper::map(self::pipelineModel('feature-extraction'), 'nscale');

        $this->assertSame(Embeddings::class, $definition['class'] ?? null);
    }

    public function testIgnoresPipelineTasksOfThirdPartyProviders()
    {
        $this->assertNull(ModelMapper::map([
            'id' => 'black-forest-labs/FLUX.1-dev',
            'pipeline_tag' => 'text-to-image',
            'inferenceProviderMapping' => [
                ['provider' => 'fal-ai', 'status' => 'live', 'task' => 'text-to-image', 'type' => 'single-model'],
            ],
        ]));
    }

    public function testIgnoresAdapterMappings()
    {
        $this->assertNull(ModelMapper::map([
            'id' => 'some-user/flux-lora',
            'pipeline_tag' => 'text-to-image',
            'inferenceProviderMapping' => [
                ['provider' => 'hf-inference', 'status' => 'live', 'task' => 'text-to-image', 'type' => 'tag-filter'],
            ],
        ]));
    }

    public function testReturnsNullForUnsupportedTask()
    {
        $this->assertNull(ModelMapper::map(self::pipelineModel('image-to-video')));
    }

    public function testReturnsNullForModelWithoutProviders()
    {
        $this->assertNull(ModelMapper::map(['id' => 'openai-community/gpt2', 'pipeline_tag' => 'text-generation']));
    }

    /**
     * @return HubModel
     */
    private static function chatModel(): array
    {
        return [
            'id' => 'meta-llama/Llama-3.1-8B-Instruct',
            'pipeline_tag' => 'text-generation',
            'inferenceProviderMapping' => [
                ['provider' => 'novita', 'status' => 'live', 'task' => 'conversational', 'type' => 'single-model', 'features' => ['structuredOutput' => false, 'toolCalling' => false]],
                ['provider' => 'nscale', 'status' => 'live', 'task' => 'conversational', 'type' => 'single-model', 'features' => ['structuredOutput' => true, 'toolCalling' => false]],
                ['provider' => 'featherless-ai', 'status' => 'live', 'task' => 'conversational', 'type' => 'single-model', 'features' => null],
                ['provider' => 'deepinfra', 'status' => 'live', 'task' => 'conversational', 'type' => 'single-model', 'features' => ['structuredOutput' => false, 'toolCalling' => true]],
                ['provider' => 'groq', 'status' => 'error', 'task' => 'conversational', 'type' => 'single-model', 'features' => ['structuredOutput' => true, 'toolCalling' => true]],
            ],
        ];
    }

    /**
     * @return HubModel
     */
    private static function pipelineModel(string $task): array
    {
        return [
            'id' => 'test/model',
            'pipeline_tag' => $task,
            'inferenceProviderMapping' => [
                ['provider' => 'hf-inference', 'status' => 'live', 'task' => $task, 'type' => 'single-model'],
            ],
        ];
    }
}
