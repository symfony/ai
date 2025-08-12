<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Embeddings;

use Symfony\AI\Platform\Action;
use Symfony\AI\Platform\Bridge\Gemini\Embeddings;
use Symfony\AI\Platform\Exception\InvalidActionArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Valtteri R <valtzu@gmail.com>
 */
final readonly class ModelClient implements ModelClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter]
        private string $apiKey,
    ) {
    }

    public function supports(Model $model, Action $action): bool
    {
        if (Action::CALCULATE_EMBEDDINGS !== $action) {
            return false;
        }

        return $model instanceof Embeddings;
    }

    public function request(Model $model, Action $action, array|string $payload, array $options = []): RawHttpResult
    {
        if (Action::CALCULATE_EMBEDDINGS !== $action) {
            return throw new InvalidActionArgumentException($model, $action, [Action::CALCULATE_EMBEDDINGS]);
        }

        $url = \sprintf('https://generativelanguage.googleapis.com/v1beta/models/%s:%s', $model->getName(), 'batchEmbedContents');
        $modelOptions = $model->getOptions();

        return new RawHttpResult($this->httpClient->request('POST', $url, [
            'headers' => [
                'x-goog-api-key' => $this->apiKey,
            ],
            'json' => [
                'requests' => array_map(
                    static fn (string $text) => array_filter([
                        'model' => 'models/'.$model->getName(),
                        'content' => ['parts' => [['text' => $text]]],
                        'outputDimensionality' => $modelOptions['dimensions'] ?? null,
                        'taskType' => $modelOptions['task_type'] ?? null,
                        'title' => $options['title'] ?? null,
                    ]),
                    \is_array($payload) ? $payload : [$payload],
                ),
            ],
        ]));
    }
}
