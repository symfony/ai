<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Embeddings;

use Symfony\AI\Platform\Model as BaseModel;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
final class ModelClient implements ModelClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $location,
        private readonly string $projectId,
    ) {
    }

    public function supports(BaseModel $model): bool
    {
        return $model instanceof Model;
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function request(BaseModel $model, array|string $payload, array $options = []): RawHttpResult
    {
        $url = \sprintf(
            'https://aiplatform.googleapis.com/v1/projects/%s/locations/%s/publishers/google/models/%s:%s',
            $this->projectId,
            $this->location,
            $model->getName(),
            'predict',
        );

        $modelOptions = $model->getOptions();

        $payload = [
            'instances' => array_map(
                static fn (string $text) => [
                    'content' => $text,
                    'title' => $options['title'] ?? null,
                    'task_type' => $modelOptions['task_type'] ?? TaskType::RETRIEVAL_QUERY,
                ],
                \is_array($payload) ? $payload : [$payload],
            ),
        ];

        unset($modelOptions['task_type']);

        return new RawHttpResult(
            $this->httpClient->request(
                'POST',
                $url,
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'json' => array_merge($payload, $modelOptions),
                ]
            )
        );
    }
}
