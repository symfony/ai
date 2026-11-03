<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Azure\OpenAi;

use Symfony\AI\Platform\Bridge\Azure\BaseUrlNormalizerTrait;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper\Task;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class WhisperModelClient implements ModelClientInterface
{
    use BaseUrlNormalizerTrait;

    private readonly EventSourceHttpClient $httpClient;
    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of the Azure resource; accepts a bare host (https assumed) or a
     *                        full URL with scheme, with or without a trailing slash
     */
    public function __construct(
        HttpClientInterface $httpClient,
        string $baseUrl,
        private readonly string $deployment,
        private readonly string $apiVersion,
        #[\SensitiveParameter] private readonly string $apiKey,
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
        $this->baseUrl = $this->normalizeBaseUrl($baseUrl);
        if ('' === $deployment) {
            throw new InvalidArgumentException('The deployment must not be empty.');
        }
        if ('' === $apiVersion) {
            throw new InvalidArgumentException('The API version must not be empty.');
        }
        if ('' === $apiKey) {
            throw new InvalidArgumentException('The API key must not be empty.');
        }
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Whisper;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        /** @var array<string, mixed> $httpOptions */
        $httpOptions = $options[PlatformOptions::HTTP_OPTIONS] ?? [];
        unset($options[PlatformOptions::HTTP_OPTIONS]);

        $task = $options['task'] ?? Task::TRANSCRIPTION;
        $endpoint = Task::TRANSCRIPTION === $task ? 'transcriptions' : 'translations';
        $url = \sprintf('%s/openai/deployments/%s/audio/%s', $this->baseUrl, $this->deployment, $endpoint);

        unset($options['task']);

        if ($options['verbose'] ?? false) {
            $options['response_format'] = 'verbose_json';
            unset($options['verbose']);
        }

        return new RawHttpResult($this->httpClient->request('POST', $url, [
            'headers' => [
                'api-key' => $this->apiKey,
                'Content-Type' => 'multipart/form-data',
            ],
            'query' => ['api-version' => $this->apiVersion],
            'body' => array_merge($options, $payload),
        ] + $httpOptions));
    }
}
