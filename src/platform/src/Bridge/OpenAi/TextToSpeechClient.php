<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi;

use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TransportInterface;

/**
 * OpenAI /v1/audio/speech contract handler.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TextToSpeechClient implements EndpointClientInterface
{
    public const ENDPOINT = 'openai.audio_speech';

    public function __construct(
        private readonly TransportInterface $transport,
    ) {
    }

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }

    public function supports(Model $model): bool
    {
        return $model->supportsEndpoint(self::ENDPOINT);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if (!isset($options['voice'])) {
            throw new InvalidArgumentException('The "voice" option is required for TextToSpeech requests.');
        }

        if (isset($options['stream_format']) || isset($options['stream'])) {
            throw new InvalidArgumentException('Streaming text-to-speech results is not supported yet.');
        }

        $input = \is_string($payload)
            ? $payload
            : ($payload['text'] ?? throw new InvalidArgumentException('The payload must contain a "text" key.'));

        $envelope = new RequestEnvelope(
            payload: array_merge($options, ['model' => $model->getName(), 'input' => $input]),
            path: '/v1/audio/speech',
        );

        return $this->transport->send($model, $envelope, $options);
    }

    public function convert(RawResultInterface $raw, array $options = []): BinaryResult
    {
        if (!$raw instanceof RawHttpResult) {
            throw new RuntimeException(\sprintf('"%s" requires an HTTP-backed raw result, got "%s".', self::class, $raw::class));
        }

        $response = $raw->getObject();

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(\sprintf('The OpenAI Text-to-Speech API returned an error: "%s"', $response->getContent(false)));
        }

        return new BinaryResult($response->getContent());
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
