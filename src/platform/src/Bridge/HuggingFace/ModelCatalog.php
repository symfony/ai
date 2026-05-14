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

use Symfony\AI\Platform\Endpoint;
use Symfony\AI\Platform\ModelCatalog\FallbackModelCatalog;

/**
 * HuggingFace supports a wide range of models dynamically; models are
 * identified by repository/model format (e.g., "microsoft/DialoGPT-medium").
 *
 * Because the catalog has no static knowledge of which task each model
 * actually supports, every descriptor exposes *all* HF task endpoints —
 * the user picks one via `$options['endpoint']` (or the legacy
 * `$options['task']`, see {@see TaskAwareDispatcher}). chat_completion is
 * the default since it's the most common consumer-facing case.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalog extends FallbackModelCatalog
{
    protected function endpointsForModel(array $modelConfig): array
    {
        return [
            new Endpoint(ChatCompletionClient::ENDPOINT),
            new Endpoint(TextGenerationClient::ENDPOINT),
            new Endpoint(ImageToTextClient::ENDPOINT),
            new Endpoint(SummarizationClient::ENDPOINT),
            new Endpoint(TranslationClient::ENDPOINT),
            new Endpoint(AutomaticSpeechRecognitionClient::ENDPOINT),
            new Endpoint(FeatureExtractionClient::ENDPOINT),
            new Endpoint(TextRankingClient::ENDPOINT),
            new Endpoint(TextClassificationClient::ENDPOINT),
            new Endpoint(AudioClassificationClient::ENDPOINT),
            new Endpoint(ImageClassificationClient::ENDPOINT),
            new Endpoint(FillMaskClient::ENDPOINT),
            new Endpoint(ImageSegmentationClient::ENDPOINT),
            new Endpoint(ObjectDetectionClient::ENDPOINT),
            new Endpoint(QuestionAnsweringClient::ENDPOINT),
            new Endpoint(SentenceSimilarityClient::ENDPOINT),
            new Endpoint(TableQuestionAnsweringClient::ENDPOINT),
            new Endpoint(TokenClassificationClient::ENDPOINT),
            new Endpoint(ZeroShotClassificationClient::ENDPOINT),
            new Endpoint(TextToImageClient::ENDPOINT),
        ];
    }
}
