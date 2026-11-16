<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere\Embeddings;

use Symfony\AI\Platform\Bridge\Cohere\Embeddings;
use Symfony\AI\Platform\Bridge\Cohere\InputType;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ModelClient implements ModelClientInterface
{
    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of a Cohere-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://api.cohere.com',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Embeddings;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        $texts = \is_array($payload) ? $payload : [$payload];

        $body = [
            'model' => $model->getName(),
            'texts' => $texts,
            'input_type' => $this->resolveInputType($options['input_type'] ?? $model->getOptions()['input_type'] ?? null)->value,
        ];

        if (isset($options['embedding_types'])) {
            $body['embedding_types'] = $options['embedding_types'];
        }

        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.'/v2/embed', [
            'auth_bearer' => $this->apiKey,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => $body,
        ]));
    }

    private function resolveInputType(mixed $inputType): InputType
    {
        if (null === $inputType) {
            return InputType::SearchDocument;
        }

        if ($inputType instanceof InputType) {
            return $inputType;
        }

        if (\is_string($inputType) && null !== $resolved = InputType::tryFrom($inputType)) {
            return $resolved;
        }

        throw new InvalidArgumentException(\sprintf('The "input_type" option must be an "%s" or one of "%s".', InputType::class, implode('", "', array_map(static fn (InputType $case): string => $case->value, InputType::cases()))));
    }
}
