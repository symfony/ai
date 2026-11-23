<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic\TextToSpeech;

use Symfony\AI\Platform\Bridge\Generic\TextToSpeechModel;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * Turns the raw audio body of an OpenAI-compatible speech endpoint into a BinaryResult,
 * taking the MIME type from the Content-Type header of the response.
 *
 * @author Tac Tacelosky <tacman@gmail.com>
 */
class ResultConverter implements ResultConverterInterface
{
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof TextToSpeechModel;
    }

    public function convert(RawResultInterface $result, array $options = []): BinaryResult
    {
        $response = $result->getObject();

        $this->throwOnHttpError($response);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $response->getStatusCode(), $response->getContent(false)));
        }

        $contentType = $response->getHeaders(false)['content-type'][0] ?? null;
        $mimeType = null !== $contentType ? strtolower(trim(explode(';', $contentType)[0])) : null;

        if (null !== $mimeType && (str_starts_with($mimeType, 'application/json') || str_starts_with($mimeType, 'text/'))) {
            throw new RuntimeException(\sprintf('Expected an audio response, got "%s": "%s"', $mimeType, $response->getContent(false)));
        }

        return new BinaryResult($response->getContent(), '' !== $mimeType ? $mimeType : null);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
