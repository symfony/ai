<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Azure\Responses;

use Symfony\AI\Platform\Bridge\OpenResponses\ResponsesModel;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelClient implements ModelClientInterface
{
    private readonly EventSourceHttpClient $httpClient;
    private readonly string $endpoint;

    public function __construct(
        HttpClientInterface $httpClient,
        string $baseUrl,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly ?string $deployment = null,
    ) {
        if ('' === $baseUrl) {
            throw new InvalidArgumentException('The base URL must not be empty.');
        }
        if (str_starts_with($baseUrl, 'http://') || str_starts_with($baseUrl, 'https://')) {
            throw new InvalidArgumentException('The base URL must not contain the protocol.');
        }
        if ('' === $apiKey) {
            throw new InvalidArgumentException('The API key must not be empty.');
        }

        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
        $this->endpoint = \sprintf('https://%s/openai/v1/responses', rtrim($baseUrl, '/'));
    }

    public function supports(Model $model): bool
    {
        return $model instanceof ResponsesModel;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (isset($options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema']['schema'])) {
            $schema = $options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema'];
            $options['text']['format'] = $schema;
            $options['text']['format']['name'] = $schema['name'];
            $options['text']['format']['type'] = $options[PlatformSubscriber::RESPONSE_FORMAT]['type'];

            unset($options[PlatformSubscriber::RESPONSE_FORMAT]);
        }

        return new RawHttpResult($this->httpClient->request('POST', $this->endpoint, [
            'headers' => [
                'api-key' => $this->apiKey,
            ],
            'json' => array_merge($options, ['model' => $this->deployment ?? $model->getName()], $payload),
        ]));
    }
}
