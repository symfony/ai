<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;

/**
 * Maps a model of the Hugging Face Hub API to the model class and the capabilities it is served with.
 *
 * @phpstan-type ProviderMapping array{
 *     provider?: string,
 *     status: string,
 *     task: string,
 *     type?: string,
 *     features?: array{toolCalling?: bool, structuredOutput?: bool}|null,
 * }
 * @phpstan-type HubModel array{
 *     id: string,
 *     pipeline_tag?: string|null,
 *     inferenceProviderMapping?: list<ProviderMapping>|array<string, ProviderMapping>|null,
 * }
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelMapper
{
    private const PIPELINES = [
        'automatic-speech-recognition' => [SpeechRecognition::class, [Capability::INPUT_AUDIO, Capability::OUTPUT_TEXT, Capability::SPEECH_TO_TEXT]],
        'feature-extraction' => [Embeddings::class, [Capability::INPUT_TEXT, Capability::EMBEDDINGS]],
        'fill-mask' => [FillMask::class, [Capability::INPUT_TEXT]],
        'image-classification' => [Classification::class, [Capability::INPUT_IMAGE]],
        'image-segmentation' => [ImageSegmentation::class, [Capability::INPUT_IMAGE]],
        'object-detection' => [ObjectDetection::class, [Capability::INPUT_IMAGE]],
        'question-answering' => [QuestionAnswering::class, [Capability::INPUT_TEXT]],
        'sentence-similarity' => [Embeddings::class, [Capability::INPUT_TEXT, Capability::EMBEDDINGS]],
        'summarization' => [Summarization::class, [Capability::INPUT_TEXT, Capability::OUTPUT_TEXT]],
        'table-question-answering' => [TableQuestionAnswering::class, [Capability::INPUT_TEXT]],
        'text-classification' => [Classification::class, [Capability::INPUT_TEXT]],
        'text-ranking' => [Reranker::class, [Capability::INPUT_MULTIPLE, Capability::RERANKING]],
        'text-to-image' => [TextToImage::class, [Capability::INPUT_TEXT, Capability::OUTPUT_IMAGE, Capability::TEXT_TO_IMAGE]],
        'token-classification' => [TokenClassification::class, [Capability::INPUT_TEXT]],
        'translation' => [Translation::class, [Capability::INPUT_TEXT, Capability::OUTPUT_TEXT]],
        'zero-shot-classification' => [ZeroShotClassification::class, [Capability::INPUT_TEXT]],
    ];

    /**
     * @return list<string>
     */
    public static function getPipelineTasks(): array
    {
        return array_keys(self::PIPELINES);
    }

    /**
     * @param HubModel $model
     * @param ?string  $provider Only consider chat completion on this inference provider, any provider when null
     *
     * @return array{class: class-string<Model>, capabilities: list<Capability>}|null null if the model is not served by a supported task
     */
    public static function map(array $model, ?string $provider = null): ?array
    {
        $chatMappings = [];
        $pipelineTask = null;

        foreach (self::getLiveMappings($model) as $mappingProvider => $mapping) {
            if ('conversational' === $mapping['task']) {
                if (null === $provider || $provider === $mappingProvider) {
                    $chatMappings[] = $mapping;
                }

                continue;
            }

            // Task pipelines run on the Hugging Face inference service only, see Pipeline\ModelClient
            if (Provider::HF_INFERENCE === $mappingProvider && isset(self::PIPELINES[$mapping['task']])) {
                $pipelineTask = $mapping['task'];
            }
        }

        if ([] !== $chatMappings) {
            return ['class' => Completions::class, 'capabilities' => self::getChatCapabilities($model, $chatMappings)];
        }

        if (null !== $pipelineTask) {
            return ['class' => self::PIPELINES[$pipelineTask][0], 'capabilities' => self::PIPELINES[$pipelineTask][1]];
        }

        return null;
    }

    /**
     * @param HubModel $model
     *
     * @return iterable<string, ProviderMapping>
     */
    private static function getLiveMappings(array $model): iterable
    {
        // The model listing returns the mappings as a list, the model endpoint keyed by provider
        foreach ($model['inferenceProviderMapping'] ?? [] as $key => $mapping) {
            if ('live' !== $mapping['status'] || 'tag-filter' === ($mapping['type'] ?? null)) {
                continue;
            }

            yield $mapping['provider'] ?? (string) $key => $mapping;
        }
    }

    /**
     * @param HubModel              $model
     * @param list<ProviderMapping> $mappings
     *
     * @return list<Capability>
     */
    private static function getChatCapabilities(array $model, array $mappings): array
    {
        $capabilities = [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING];

        if ('image-text-to-text' === ($model['pipeline_tag'] ?? null)) {
            $capabilities[] = Capability::INPUT_IMAGE;
        }

        $features = array_map(static fn (array $mapping): array => $mapping['features'] ?? [], $mappings);

        if (\in_array(true, array_column($features, 'toolCalling'), true)) {
            $capabilities[] = Capability::TOOL_CALLING;
        }

        if (\in_array(true, array_column($features, 'structuredOutput'), true)) {
            $capabilities[] = Capability::OUTPUT_STRUCTURED;
        }

        return $capabilities;
    }
}
