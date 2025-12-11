<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
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
        return $model instanceof Claude;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        $headers = [
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ];

        if (isset($options['tools'])) {
            $options['tool_choice'] = ['type' => 'auto'];
        }

        if (isset($options['response_format'])) {
            $options['beta_features'][] = 'structured-outputs-2025-11-13';
            $options['output_format'] = [
                'type' => 'json_schema',
                'schema' => $options['response_format']['json_schema']['schema'] ?? [],
            ];
            unset($options['response_format']);
        }

        if (isset($options['beta_features']) && \is_array($options['beta_features']) && \count($options['beta_features']) > 0) {
            $headers['anthropic-beta'] = implode(',', $options['beta_features']);
            unset($options['beta_features']);
        }

        return new RawHttpResult($this->httpClient->request('POST', 'https://api.anthropic.com/v1/messages', [
            'headers' => $headers,
            'json' => array_merge($options, $payload),
        ]));
    }
}
