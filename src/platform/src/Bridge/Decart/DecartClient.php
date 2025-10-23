<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Decart;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final readonly class DecartClient implements ModelClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
        private ?string $hostUrl = 'https://api.decart.ai/v1',
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Decart;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        dd($this->httpClient->request('POST', \sprintf('%s/generate/%s', $this->hostUrl, $model->getName()), [
            'headers' => [
                'x-api-key' => $this->apiKey,
            ],
            'body' => [
                'prompt' => \is_string($payload) ? $payload : $payload['text'],
                ...$options,
            ],
        ])->getContent(false));

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/generate/%s', $this->hostUrl, $model->getName()), [
            'headers' => [
                'x-api-key' => $this->apiKey,
            ],
            'body' => [
                'prompt' => \is_string($payload) ? $payload : $payload['text'],
                ...$options,
            ],
        ]));
    }
}
