<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Voyage;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class ModelClient implements ModelClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Voyage;
    }

    public function request(Model $model, object|string|array $payload, array $options = []): RawHttpResult
    {
        return new RawHttpResult($this->httpClient->request('POST', 'https://api.voyageai.com/v1/embeddings', [
            'auth_bearer' => $this->apiKey,
            'json' => [
                'model' => $model->getName(),
                'input' => $payload,
            ],
        ]));
    }
}
