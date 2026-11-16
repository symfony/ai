<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama\TextToSpeech;

use Symfony\AI\Platform\Bridge\Ollama\TextToSpeech;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ResultConverter implements ResultConverterInterface
{
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof TextToSpeech;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        $response = $result->getObject();

        $this->throwOnHttpError($response);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(\sprintf('The Ollama Text-to-Speech API returned an error: "%s"', $response->getContent(false)));
        }

        $mimeType = $response->getHeaders(false)['content-type'][0] ?? null;

        return new BinaryResult($response->getContent(), null !== $mimeType ? explode(';', $mimeType, 2)[0] : match ($options['response_format'] ?? Format::WAV) {
            Format::PCM => 'audio/pcm',
            Format::WAV => 'audio/wav',
            default => null,
        });
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }

    private function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            $data = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            return null;
        }

        $error = $data['error'] ?? null;

        if (\is_string($error)) {
            return $error;
        }

        if (\is_array($error) && \is_string($error['message'] ?? null)) {
            return $error['message'];
        }

        return \is_string($data['message'] ?? null) ? $data['message'] : null;
    }
}
