<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\TextToSpeech;

use Symfony\AI\Platform\Bridge\Mistral\TextToSpeech;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Anthony Hacart <anthony.hacart@gmail.com>
 */
final class ResultConverter implements ResultConverterInterface
{
    use HttpStatusErrorHandlingTrait;

    private const MIME_TYPES = [
        Format::MP3 => 'audio/mpeg',
        Format::WAV => 'audio/wav',
        Format::FLAC => 'audio/flac',
    ];

    public function supports(Model $model): bool
    {
        return $model instanceof TextToSpeech;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function convert(RawResultInterface $result, array $options = []): BinaryResult
    {
        $httpResponse = $result->getObject();

        $this->throwOnHttpError($httpResponse);

        if (200 !== $httpResponse->getStatusCode()) {
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $httpResponse->getStatusCode(), $httpResponse->getContent(false)));
        }

        $data = $result->getData();

        if (!isset($data['audio_data']) || !\is_string($data['audio_data'])) {
            throw new RuntimeException('Response does not contain audio data.');
        }

        $format = $options['response_format'] ?? Format::MP3;

        return BinaryResult::fromBase64($data['audio_data'], \is_string($format) ? self::MIME_TYPES[$format] ?? null : null);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
