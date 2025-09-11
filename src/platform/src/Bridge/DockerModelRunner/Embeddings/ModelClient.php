<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\DockerModelRunner\Embeddings;

use Symfony\AI\Platform\Bridge\DockerModelRunner\Embeddings;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final readonly class ModelClient implements ModelClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $hostUrl,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Embeddings;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/engines/v1/embeddings', $this->hostUrl), [
            'json' => array_merge($options, [
                'model' => $model->getName(),
                'input' => $payload,
            ]),
        ]));
    }
}
