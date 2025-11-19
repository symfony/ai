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
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\Vector\Vector;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return true;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        $httpResponse = $result->getObject();
        if (503 === $httpResponse->getStatusCode()) {
            throw new RuntimeException('Service unavailable.');
        }

        if (404 === $httpResponse->getStatusCode()) {
            throw new InvalidArgumentException('Model, provider or task not found (404).');
        }

        $headers = $httpResponse->getHeaders(false);
        $contentType = $headers['content-type'][0] ?? null;
        $content = str_contains($contentType, 'application/json') ? $httpResponse->toArray(false) : $httpResponse->getContent(false);

        if (str_starts_with((string) $httpResponse->getStatusCode(), '4')) {
            $message = match (true) {
                \is_string($content) => $content,
                \is_array($content['error']) => $content['error'][0],
                default => $content['error'],
            };

            throw new InvalidArgumentException(\sprintf('API Client Error (%d): "%s"', $httpResponse->getStatusCode(), $message));
        }

        if (200 !== $httpResponse->getStatusCode()) {
            throw new RuntimeException(\sprintf('Unhandled response code: %d', $httpResponse->getStatusCode()));
        }

        $task = $options['task'] ?? null;

        return match ($task) {
            Task::AUDIO_CLASSIFICATION, Task::IMAGE_CLASSIFICATION => new ObjectResult(ClassificationResult::fromArray($content)),
            Task::AUTOMATIC_SPEECH_RECOGNITION => new TextResult($content['text'] ?? ''),
            Task::CHAT_COMPLETION => new TextResult($content['choices'][0]['message']['content'] ?? ''),
            Task::FEATURE_EXTRACTION => new VectorResult(new Vector($content)),
            Task::TEXT_CLASSIFICATION => new ObjectResult(ClassificationResult::fromArray(reset($content) ?? [])),
            Task::FILL_MASK => new ObjectResult(FillMaskResult::fromArray($content)),
            Task::IMAGE_SEGMENTATION => new ObjectResult(ImageSegmentationResult::fromArray($content)),
            Task::IMAGE_TO_TEXT, Task::TEXT_GENERATION => new TextResult($content[0]['generated_text'] ?? ''),
            Task::TEXT_TO_IMAGE => new BinaryResult($content, $contentType),
            Task::OBJECT_DETECTION => new ObjectResult(ObjectDetectionResult::fromArray($content)),
            Task::QUESTION_ANSWERING => new ObjectResult(QuestionAnsweringResult::fromArray($content)),
            Task::SENTENCE_SIMILARITY => new ObjectResult(SentenceSimilarityResult::fromArray($content)),
            Task::SUMMARIZATION => new TextResult($content[0]['summary_text']),
            Task::TABLE_QUESTION_ANSWERING => new ObjectResult(TableQuestionAnsweringResult::fromArray($content)),
            Task::TOKEN_CLASSIFICATION => new ObjectResult(TokenClassificationResult::fromArray($content)),
            Task::TRANSLATION => new TextResult($content[0]['translation_text'] ?? ''),
            Task::ZERO_SHOT_CLASSIFICATION => new ObjectResult(ZeroShotClassificationResult::fromArray($content)),

            default => throw new RuntimeException(\sprintf('Unsupported task: %s', $task)),
        };
    }
}
