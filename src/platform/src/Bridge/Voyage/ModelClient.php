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

use Symfony\AI\Platform\Capability;
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
        [$inputKey, $endpoint] = $model->supports(Capability::INPUT_MULTIMODAL)
            ? ['inputs', 'multimodalembeddings']
            : ['input', 'embeddings'];

        $body = [
            'auth_bearer' => $this->apiKey,
            'json' => [
                'model' => $model->getName(),
                $inputKey => $payload,
                'input_type' => $options['input_type'] ?? null,
                'truncation' => $options['truncation'] ?? true,
            ],
        ];

        if ($model->supports(Capability::INPUT_MULTIMODAL)) {
            $body['json']['output_encoding'] = $options['encoding'] ?? null;
        } else {
            $body['json']['output_dimension'] = $options['dimensions'] ?? null;
            $body['json']['encoding_format'] = $options['encoding'] ?? null;
        }

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('https://api.voyageai.com/v1/%s', $endpoint), $body));
    }
}
