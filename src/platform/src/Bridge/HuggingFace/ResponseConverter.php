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

use Symfony\AI\Platform\Bridge\HuggingFace\Output\ClassificationResult;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\FillMaskResult;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\ImageSegmentationResult;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\ObjectDetectionResult;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\QuestionAnsweringResult;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\SentenceSimilarityResult;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\TableQuestionAnsweringResult;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\TokenClassificationResult;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\ZeroShotClassificationResult;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Response\BinaryResponse;
use Symfony\AI\Platform\Response\ObjectResponse;
use Symfony\AI\Platform\Response\ResponseInterface as LlmResponse;
use Symfony\AI\Platform\Response\TextResponse;
use Symfony\AI\Platform\Response\VectorResponse;
use Symfony\AI\Platform\ResponseConverterInterface as PlatformResponseConverter;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class ResponseConverter implements PlatformResponseConverter
{
    public function supports(Model $model): bool
    {
        return true;
    }

    public function convert(ResponseInterface $response, array $options = []): LlmResponse
    {
        if (503 === $response->getStatusCode()) {
            return throw new RuntimeException('Service unavailable.');
        }

        if (404 === $response->getStatusCode()) {
            return throw new InvalidArgumentException('Model, provider or task not found (404).');
        }

        $headers = $response->getHeaders(false);
        $contentType = $headers['content-type'][0] ?? null;
        $content = 'application/json' === $contentType ? $response->toArray(false) : $response->getContent(false);

        if (str_starts_with((string) $response->getStatusCode(), '4')) {
            $message = \is_string($content) ? $content :
                (\is_array($content['error']) ? $content['error'][0] : $content['error']);

            throw new InvalidArgumentException(\sprintf('API Client Error (%d): %s', $response->getStatusCode(), $message));
        }

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException('Unhandled response code: '.$response->getStatusCode());
        }

        $task = $options['task'] ?? null;

        return match ($task) {
            Task::AUDIO_CLASSIFICATION, Task::IMAGE_CLASSIFICATION => new ObjectResponse(
                ClassificationResult::fromArray($content)
            ),
            Task::AUTOMATIC_SPEECH_RECOGNITION => new TextResponse($content['text'] ?? ''),
            Task::CHAT_COMPLETION => new TextResponse($content['choices'][0]['message']['content'] ?? ''),
            Task::FEATURE_EXTRACTION => new VectorResponse(new Vector($content)),
            Task::TEXT_CLASSIFICATION => new ObjectResponse(ClassificationResult::fromArray(reset($content) ?? [])),
            Task::FILL_MASK => new ObjectResponse(FillMaskResult::fromArray($content)),
            Task::IMAGE_SEGMENTATION => new ObjectResponse(ImageSegmentationResult::fromArray($content)),
            Task::IMAGE_TO_TEXT, Task::TEXT_GENERATION => new TextResponse($content[0]['generated_text'] ?? ''),
            Task::TEXT_TO_IMAGE => new BinaryResponse($content, $contentType),
            Task::OBJECT_DETECTION => new ObjectResponse(ObjectDetectionResult::fromArray($content)),
            Task::QUESTION_ANSWERING => new ObjectResponse(QuestionAnsweringResult::fromArray($content)),
            Task::SENTENCE_SIMILARITY => new ObjectResponse(SentenceSimilarityResult::fromArray($content)),
            Task::SUMMARIZATION => new TextResponse($content[0]['summary_text']),
            Task::TABLE_QUESTION_ANSWERING => new ObjectResponse(TableQuestionAnsweringResult::fromArray($content)),
            Task::TOKEN_CLASSIFICATION => new ObjectResponse(TokenClassificationResult::fromArray($content)),
            Task::TRANSLATION => new TextResponse($content[0]['translation_text'] ?? ''),
            Task::ZERO_SHOT_CLASSIFICATION => new ObjectResponse(ZeroShotClassificationResult::fromArray($content)),

            default => throw new RuntimeException(\sprintf('Unsupported task: %s', $task)),
        };
    }
}
