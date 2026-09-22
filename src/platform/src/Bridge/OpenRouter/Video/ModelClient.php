<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Video;

use Symfony\AI\Platform\Bridge\OpenRouter\VideoGenerationModel;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Submits a video generation job; the returned job is resolved by {@see JobClient}.
 *
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class ModelClient implements ModelClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly ?string $apiKey = null,
        private readonly string $baseUrl = 'https://openrouter.ai/api',
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof VideoGenerationModel;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        $prompt = $this->extractTextPrompt($payload, $options);
        $imageFrame = $this->extractImageFrame($payload, $options);
        unset($options['prompt']);

        $body = [
            'model' => $model->getName(),
            'prompt' => $prompt,
            ...$options,
            ...$imageFrame,
        ];

        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.'/v1/videos', [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Content-Type' => 'application/json'],
            'json' => $body,
        ]));
    }

    /**
     * @param array<string|int, mixed>|string $payload
     * @param array<string, mixed>            $options
     *
     * @return array<string, mixed>
     */
    private function extractImageFrame(array|string $payload, array $options): array
    {
        foreach ($payload['messages'] ?? [] as $message) {
            if (($message['content'][0]['type'] ?? '') === 'image_url') {
                return [
                    'frame_images' => [
                        array_merge($message['content'][0], ['frame_type' => 'first_frame']),
                    ],
                ];
            }
        }

        return [];
    }

    /**
     * @param array<string|int, mixed>|string $payload
     * @param array<string, mixed>            $options
     */
    private function extractTextPrompt(array|string $payload, array $options): string
    {
        if (\is_string($payload)) {
            return $payload;
        }

        if (isset($payload['text']) && \is_string($payload['text'])) {
            return $payload['text'];
        }

        if (isset($options['prompt']) && \is_string($options['prompt'])) {
            return $options['prompt'];
        }

        foreach ($payload['messages'] ?? [] as $message) {
            if (isset($message['role']) && 'system' === $message['role']) {
                return $message['content'];
            }
        }

        throw new InvalidArgumentException('The video generation request requires a text prompt.');
    }
}
