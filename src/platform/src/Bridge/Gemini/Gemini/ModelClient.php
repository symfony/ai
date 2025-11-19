<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Gemini;

use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Roy Garrido
 */
final class ModelClient implements ModelClientInterface
{
    private readonly EventSourceHttpClient $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Gemini;
    }

    /**
     * @throws TransportExceptionInterface When the HTTP request fails due to network issues
     */
    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        $url = \sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:%s',
            $model->getName(),
            $options['stream'] ?? false ? 'streamGenerateContent' : 'generateContent',
        );

        if (isset($options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema']['schema'])) {
            $options['responseMimeType'] = 'application/json';
            $options['responseJsonSchema'] = $options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema']['schema'];
            unset($options[PlatformSubscriber::RESPONSE_FORMAT]);
        }

        $generationConfig = ['generationConfig' => $options];
        unset($generationConfig['generationConfig']['stream']);
        unset($generationConfig['generationConfig']['tools']);
        unset($generationConfig['generationConfig']['server_tools']);

        if ([] === $generationConfig['generationConfig']) {
            $generationConfig = [];
        }

        if (isset($options['tools'])) {
            $generationConfig['tools'][] = ['functionDeclarations' => $options['tools']];
            unset($options['tools']);
        }

        foreach ($options['server_tools'] ?? [] as $tool => $params) {
            if (!$params) {
                continue;
            }

            $generationConfig['tools'][] = [$tool => true === $params ? new \ArrayObject() : $params];
        }

        return new RawHttpResult($this->httpClient->request('POST', $url, [
            'headers' => [
                'x-goog-api-key' => $this->apiKey,
            ],
            'json' => array_merge($generationConfig, $payload),
        ]));
    }
}
