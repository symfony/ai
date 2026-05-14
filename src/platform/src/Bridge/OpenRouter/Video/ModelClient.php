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
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class ModelClient implements ModelClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly ?string $apiKey = null,
        private readonly string $baseUrl = 'https://openrouter.ai/api',
        private readonly int $pollIntervalSeconds = 5,
        private readonly int $pollTimeoutSeconds = 600,
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

        $pollIntervalSeconds = $options['poll_interval'] ?? $this->pollIntervalSeconds;
        $pollTimeoutSeconds = $options['poll_timeout'] ?? $this->pollTimeoutSeconds;
        unset($options['poll_interval'], $options['poll_timeout'], $options['prompt']);

        $body = [
            'model' => $model->getName(),
            'prompt' => $prompt,
            ...$options,
            ...$imageFrame,
        ];

        $createResponse = $this->httpClient->request('POST', $this->baseUrl.'/v1/videos', [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Content-Type' => 'application/json'],
            'json' => $body,
        ]);

        $data = $createResponse->toArray();

        if (!isset($data['id'])) {
            throw new RuntimeException('The video generation request did not return a job ID.');
        }

        $contentUrl = $this->waitForCompletion((string) $data['id'], (int) $pollIntervalSeconds, (int) $pollTimeoutSeconds);

        return new RawHttpResult($this->httpClient->request('GET', $contentUrl, [
            'auth_bearer' => $this->apiKey,
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

    private function waitForCompletion(string $jobId, int $pollIntervalSeconds, int $pollTimeoutSeconds): string
    {
        $deadline = microtime(true) + $pollTimeoutSeconds;

        while (true) {
            $statusData = $this->httpClient->request('GET', $this->baseUrl.'/v1/videos/'.$jobId, [
                'auth_bearer' => $this->apiKey,
            ])->toArray();

            $status = $statusData['status'] ?? null;

            if ('completed' === $status) {
                $contentUrl = $statusData['unsigned_urls'][0] ?? null;
                if (!\is_string($contentUrl) || '' === $contentUrl) {
                    throw new RuntimeException(\sprintf('Video generation completed but no download URL was returned for job "%s".', $jobId));
                }

                return $contentUrl;
            }

            if ('failed' === $status) {
                throw new RuntimeException(\sprintf('Video generation failed for job "%s".', $jobId));
            }

            if (microtime(true) >= $deadline) {
                throw new RuntimeException(\sprintf('Video generation timed out for job "%s" after %d seconds.', $jobId, $pollTimeoutSeconds));
            }

            if ($pollIntervalSeconds > 0) {
                sleep($pollIntervalSeconds);
            }
        }
    }
}
