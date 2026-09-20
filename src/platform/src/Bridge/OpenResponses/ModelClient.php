<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenResponses;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\Stream\HttpStreamInterface;
use Symfony\AI\Platform\Result\Stream\RawSseStream;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class ModelClient implements ModelClientInterface
{
    use JsonBodyEncodingTrait;

    private readonly EventSourceHttpClient $httpClient;
    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of an Open Responses-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        HttpClientInterface $httpClient,
        string $baseUrl,
        #[\SensitiveParameter] private readonly ?string $apiKey = null,
        private readonly string $path = '/v1/responses',
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof ResponsesModel;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        $requestOptions = [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $this->encodeJsonBody($this->createBody($model, $payload, $options)),
        ];

        if (null !== $this->apiKey) {
            $requestOptions['auth_bearer'] = $this->apiKey;
        }

        // The ChatGPT Codex backend streams SSE without a text/event-stream
        // content type, so use a stream parser that handles that framing too.
        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.$this->path, $requestOptions), $this->createStreamParser());
    }

    /**
     * The request body of a single Responses API call, separate from sending it so a batch can build one per line.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    protected function createBody(Model $model, array $payload, array $options): array
    {
        if (isset($options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema']['schema'])) {
            $schema = $options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema'];
            $options['text']['format'] = $schema;
            $options['text']['format']['name'] = $schema['name'];
            $options['text']['format']['type'] = $options[PlatformSubscriber::RESPONSE_FORMAT]['type'];

            unset($options[PlatformSubscriber::RESPONSE_FORMAT]);
        }

        return array_merge($options, ['model' => $model->getName()], $payload);
    }

    /**
     * Stream parser applied to the raw response.
     *
     * Defaults to a lenient SSE parser for backends (e.g. ChatGPT Codex) that
     * stream without a proper "text/event-stream" content type. Bridges talking
     * to APIs that always send the correct content type can override this to
     * return a stricter parser.
     */
    protected function createStreamParser(): HttpStreamInterface
    {
        return new RawSseStream();
    }
}
