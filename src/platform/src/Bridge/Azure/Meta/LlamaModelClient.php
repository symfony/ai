<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Azure\Meta;

use Symfony\AI\Platform\Bridge\Meta\Llama;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class LlamaModelClient implements ModelClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        #[\SensitiveParameter] private readonly string $apiKey,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Llama;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        $url = \sprintf('https://%s/chat/completions', $this->baseUrl);

        return new RawHttpResult($this->httpClient->request('POST', $url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $this->apiKey,
            ],
            'json' => array_merge($options, $payload),
        ]));
    }
}
