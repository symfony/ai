<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bifrost\Audio;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class SpeechResultConverter implements ResultConverterInterface
{
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof SpeechModel;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        $response = $result->getObject();
        if (!$response instanceof ResponseInterface) {
            throw new RuntimeException('Expected an HTTP response for the Bifrost text-to-speech endpoint.');
        }

        $this->throwOnHttpError($response);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(\sprintf('The Bifrost text-to-speech API returned an error: "%s"', $response->getContent(false)));
        }

        $headers = $response->getHeaders(false);
        $contentTypeHeader = $headers['content-type'] ?? null;
        $contentType = \is_array($contentTypeHeader) && isset($contentTypeHeader[0]) && \is_string($contentTypeHeader[0])
            ? $contentTypeHeader[0]
            : null;

        return new BinaryResult($response->getContent(), $contentType);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
