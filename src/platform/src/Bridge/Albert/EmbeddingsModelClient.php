<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Albert;

use Symfony\AI\Platform\Bridge\Albert\Validator\AlbertValidator;
use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final readonly class EmbeddingsModelClient implements ModelClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
        private string $baseUrl,
    ) {
        AlbertValidator::validateApi($apiKey, $baseUrl);
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Embeddings;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/embeddings', $this->baseUrl), [
            'auth_bearer' => $this->apiKey,
            'json' => \is_array($payload) ? array_merge($payload, $options) : $payload,
        ]));
    }
}
