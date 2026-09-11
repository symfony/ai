<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ModelsLab;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Adhik Joshi <adhik@modelslab.com>
 */
final class ModelsLabClient implements ModelClientInterface
{
    private const BASE_URL = 'https://modelslab.com/api/v6';
    private const MAX_POLL_ATTEMPTS = 24;
    private const POLL_INTERVAL_SECONDS = 5;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof ModelsLab;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if (!$model instanceof ModelsLab) {
            throw new InvalidArgumentException(\sprintf('The "%s" model is not supported.', $model->getName()));
        }

        return match (true) {
            \in_array(Capability::TEXT_TO_IMAGE, $model->getCapabilities(), true) => $this->textToImage($model, $payload, $options),
            default => throw new InvalidArgumentException(\sprintf('No supported capability found for model "%s".', $model->getName())),
        };
    }

    /**
     * @param array<string|int, mixed>|string $payload
     * @param array<string, mixed>             $options
     */
    private function textToImage(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        $prompt = \is_string($payload) ? $payload : ($payload['text'] ?? $payload['prompt'] ?? '');

        $body = array_merge([
            'key' => $this->apiKey,
            'model_id' => $model->getName(),
            'prompt' => $prompt,
            'width' => '512',
            'height' => '512',
            'samples' => '1',
            'num_inference_steps' => '20',
            'safety_checker' => 'no',
            'enhance_prompt' => 'no',
        ], $options);

        $response = $this->httpClient->request('POST', self::BASE_URL.'/images/text2img', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => $body,
        ]);

        $data = $response->toArray();

        if (isset($data['status']) && 'error' === $data['status']) {
            throw new RuntimeException($data['message'] ?? $data['messege'] ?? 'ModelsLab API error');
        }

        // Handle async processing
        if (isset($data['status']) && 'processing' === $data['status']) {
            $requestId = $data['id'] ?? null;
            if (null === $requestId) {
                throw new RuntimeException('ModelsLab returned processing status without a request ID.');
            }
            $data = $this->pollForResult((string) $requestId);
        }

        if (!isset($data['output'][0])) {
            throw new RuntimeException('ModelsLab API returned no image output.');
        }

        // Download the generated image and return raw binary
        $imageResponse = $this->httpClient->request('GET', $data['output'][0]);
        $imageContent = $imageResponse->getContent();
        $contentType = $imageResponse->getHeaders()['content-type'][0] ?? 'image/jpeg';

        return new InMemoryRawResult(['binary' => $imageContent, 'content_type' => $contentType]);
    }

    /**
     * Polls the ModelsLab fetch endpoint until the result is ready or the maximum attempts are reached.
     *
     * @return array<string, mixed>
     */
    private function pollForResult(string $requestId): array
    {
        for ($attempt = 0; $attempt < self::MAX_POLL_ATTEMPTS; ++$attempt) {
            sleep(self::POLL_INTERVAL_SECONDS);

            $response = $this->httpClient->request('POST', self::BASE_URL.'/images/fetch/'.$requestId, [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => ['key' => $this->apiKey],
            ]);

            $data = $response->toArray();

            if (isset($data['status']) && 'success' === $data['status']) {
                return $data;
            }

            if (isset($data['status']) && 'error' === $data['status']) {
                throw new RuntimeException($data['message'] ?? 'ModelsLab fetch API error');
            }
        }

        throw new RuntimeException(\sprintf('ModelsLab image generation timed out after %d seconds.', self::MAX_POLL_ATTEMPTS * self::POLL_INTERVAL_SECONDS));
    }
}
