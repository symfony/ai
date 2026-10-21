<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Deepgram;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class DeepgramClient implements ModelClientInterface
{
    use JsonBodyEncodingTrait;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Deepgram;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        /** @var array<string, mixed> $httpOptions */
        $httpOptions = $options[PlatformOptions::HTTP_OPTIONS] ?? [];
        unset($options[PlatformOptions::HTTP_OPTIONS]);

        $deepgramPayload = new DeepgramPayload($payload);

        return match (true) {
            $model->supports(Capability::TEXT_TO_SPEECH) => $this->doTextToSpeech($model, $deepgramPayload, $options, $httpOptions),
            $model->supports(Capability::SPEECH_TO_TEXT) => $this->doSpeechToText($model, $deepgramPayload, $options, $httpOptions),
            default => throw new InvalidArgumentException(\sprintf('The model "%s" is not supported, please check the Deepgram API.', $model->getName())),
        };
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $httpOptions
     */
    private function doTextToSpeech(Model $model, DeepgramPayload $payload, array $options, array $httpOptions): RawResultInterface
    {
        // "stream" is an SDK-internal flag consumed by the result converter, not a Deepgram query param
        $stream = true === ($options['stream'] ?? false);
        unset($options['stream']);

        return new RawHttpResult($this->httpClient->request('POST', 'speak', [
            'buffer' => !$stream,
            'query' => [
                'model' => $model->getName(),
                ...$options,
            ],
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $this->encodeJsonBody([
                'text' => $payload->asTextToSpeechPayload(),
            ]),
        ] + $httpOptions));
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $httpOptions
     */
    private function doSpeechToText(Model $model, DeepgramPayload $payload, array $options, array $httpOptions): RawResultInterface
    {
        unset($options['stream']);

        $query = [
            'model' => $model->getName(),
            ...$options,
        ];

        if ($payload->isUrlBased()) {
            return new RawHttpResult($this->httpClient->request('POST', 'listen', [
                'query' => $query,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => $this->encodeJsonBody([
                    'url' => $payload->getAudioUrl(),
                ]),
            ] + $httpOptions));
        }

        return new RawHttpResult($this->httpClient->request('POST', 'listen', [
            'query' => $query,
            'headers' => [
                'Content-Type' => $payload->getAudioMimeType(),
            ],
            'body' => $this->resolveAudioBody($payload),
        ] + $httpOptions));
    }

    /**
     * Streams the audio from disk when possible to avoid materializing
     * large files in memory; falls back to the decoded base64 payload.
     *
     * @return resource|string
     */
    private function resolveAudioBody(DeepgramPayload $payload)
    {
        $path = $payload->getAudioPath();
        if (null !== $path && is_file($path) && is_readable($path)) {
            $stream = fopen($path, 'r');
            if (false !== $stream) {
                return $stream;
            }
        }

        return $payload->getAudioBinary();
    }
}
