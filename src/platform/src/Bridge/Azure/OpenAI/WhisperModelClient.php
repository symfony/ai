<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Azure\OpenAI;

use Symfony\AI\Platform\Bridge\OpenAI\Whisper;
use Symfony\AI\Platform\Bridge\OpenAI\Whisper\Task;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class WhisperModelClient implements ModelClientInterface
{
    private EventSourceHttpClient $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        private string $baseUrl,
        private string $deployment,
        private string $apiVersion,
        #[\SensitiveParameter] private string $apiKey,
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
        !str_starts_with($this->baseUrl, 'http://') || throw new InvalidArgumentException('The base URL must not contain the protocol.');
        !str_starts_with($this->baseUrl, 'https://') || throw new InvalidArgumentException('The base URL must not contain the protocol.');
        '' !== $deployment || throw new InvalidArgumentException('The deployment must not be empty.');
        '' !== $apiVersion || throw new InvalidArgumentException('The API version must not be empty.');
        '' !== $apiKey || throw new InvalidArgumentException('The API key must not be empty.');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Whisper;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        $task = $options['task'] ?? Task::TRANSCRIPTION;
        $endpoint = Task::TRANSCRIPTION === $task ? 'transcriptions' : 'translations';
        $url = \sprintf('https://%s/openai/deployments/%s/audio/%s', $this->baseUrl, $this->deployment, $endpoint);

        unset($options['task']);

        return new RawHttpResult($this->httpClient->request('POST', $url, [
            'headers' => [
                'api-key' => $this->apiKey,
                'Content-Type' => 'multipart/form-data',
            ],
            'query' => ['api-version' => $this->apiVersion],
            'body' => array_merge($options, $payload),
        ]));
    }
}
