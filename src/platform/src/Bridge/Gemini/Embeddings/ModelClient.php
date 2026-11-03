<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Embeddings;

use Symfony\AI\Platform\Bridge\Gemini\Embeddings;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Valtteri R <valtzu@gmail.com>
 */
final class ModelClient implements ModelClientInterface
{
    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of a Gemini-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://generativelanguage.googleapis.com',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Embeddings;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        /** @var array<string, mixed> $httpOptions */
        $httpOptions = $options[PlatformOptions::HTTP_OPTIONS] ?? [];
        unset($options[PlatformOptions::HTTP_OPTIONS]);

        $url = \sprintf('%s/v1beta/models/%s:%s', $this->baseUrl, $model->getName(), 'batchEmbedContents');
        $modelOptions = $model->getOptions();

        return new RawHttpResult($this->httpClient->request('POST', $url, [
            'headers' => [
                'x-goog-api-key' => $this->apiKey,
            ],
            'json' => [
                'requests' => array_map(
                    static fn (string $text) => array_filter([
                        'model' => 'models/'.$model->getName(),
                        'content' => ['parts' => [['text' => $text]]],
                        'outputDimensionality' => $modelOptions['dimensions'] ?? null,
                        'taskType' => $modelOptions['task_type'] ?? null,
                        'title' => $options['title'] ?? null,
                    ]),
                    \is_array($payload) ? $payload : [$payload],
                ),
            ],
        ] + $httpOptions));
    }
}
