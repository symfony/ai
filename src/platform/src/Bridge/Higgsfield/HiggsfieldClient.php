<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Higgsfield;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client for the Higgsfield Cloud API (https://cloud.higgsfield.ai).
 *
 * Higgsfield works asynchronously: a generation request is submitted, then its status is polled
 * until the media is ready. The resulting media URL is downloaded and handed over to the
 * result converter as binary content.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class HiggsfieldClient implements ModelClientInterface
{
    use JsonBodyEncodingTrait;

    private const TERMINAL_STATUSES = ['completed', 'failed', 'nsfw'];

    private readonly string $baseUrl;

    /**
     * @param string $apiKey    The Higgsfield API key id
     * @param string $apiSecret The Higgsfield API key secret
     * @param string $baseUrl   Base URL of a Higgsfield-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ClockInterface $clock,
        #[\SensitiveParameter] private readonly string $apiKey,
        #[\SensitiveParameter] private readonly string $apiSecret,
        string $baseUrl = 'https://platform.higgsfield.ai',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Higgsfield;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        $endpoint = ltrim($model->getName(), '/');

        $response = $this->httpClient->request('POST', \sprintf('%s/%s', $this->baseUrl, $endpoint), [
            'headers' => $this->headers(),
            'body' => $this->encodeJsonBody($this->createInput($payload, $options)),
        ]);

        $data = $response->toArray(false);

        $requestId = $data['request_id'] ?? throw new RuntimeException(\sprintf('Higgsfield API error: "%s".', $this->extractError($data)));
        $status = $data['status'] ?? 'queued';

        while (!\in_array($status, self::TERMINAL_STATUSES, true)) {
            $this->clock->sleep(1); // we need to wait until the generation is ready

            $data = $this->httpClient->request('GET', \sprintf('%s/requests/%s/status', $this->baseUrl, $requestId), [
                'headers' => $this->headers(),
            ])->toArray(false);

            $status = $data['status'] ?? 'queued';
        }

        if ('completed' !== $status) {
            throw new RuntimeException(\sprintf('Higgsfield request "%s" "%s": "%s".', $requestId, $status, $this->extractError($data)));
        }

        return new RawHttpResult($this->httpClient->request('GET', $this->extractMediaUrl($data)));
    }

    /**
     * @param array<string|int, mixed>|string $payload
     * @param array<string, mixed>            $options
     *
     * @return array<string, mixed>
     */
    private function createInput(array|string $payload, array $options): array
    {
        if (\is_string($payload)) {
            return ['prompt' => $payload, ...$options];
        }

        // Image content normalized by the ImageNormalizer, e.g. for image-to-video generation.
        if (isset($payload['type']) && 'image_url' === $payload['type']) {
            return ['input_images' => [$payload], ...$options];
        }

        // Text content normalized to ['text' => '...'] by the default contract.
        if (isset($payload['text'])) {
            return ['prompt' => $payload['text'], ...$options];
        }

        return [...$payload, ...$options];
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'Authorization' => \sprintf('Key %s:%s', $this->apiKey, $this->apiSecret),
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractMediaUrl(array $data): string
    {
        if (isset($data['video']['url']) && \is_string($data['video']['url'])) {
            return $data['video']['url'];
        }

        if (isset($data['images'][0]['url']) && \is_string($data['images'][0]['url'])) {
            return $data['images'][0]['url'];
        }

        throw new RuntimeException('The Higgsfield response does not contain any media URL.');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractError(array $data): string
    {
        if (isset($data['detail']) && \is_string($data['detail'])) {
            return $data['detail'];
        }

        if (isset($data['message']) && \is_string($data['message'])) {
            return $data['message'];
        }

        return 'Unknown error';
    }
}
