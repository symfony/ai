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

use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ApiClient
{
    public function __construct(
        private readonly string $apiUrl,
        #[\SensitiveParameter] private readonly string $apiKey,
        private ?HttpClientInterface $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? HttpClient::create();
    }

    /**
     * @return Model[]
     */
    public function getModels(): array
    {
        $result = $this->httpClient->request('GET', \sprintf('%s/models', $this->apiUrl), [
            'auth_bearer' => $this->apiKey,
        ]);

        return array_map(fn (array $model) => new Model($model['id']), $result->toArray()['data']);
    }
}
