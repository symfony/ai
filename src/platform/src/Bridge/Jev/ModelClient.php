<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Jev;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class ModelClient implements ModelClientInterface
{
    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of a TypeSafe-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://api.typesafe.ai',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Jev;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        $questions = $options['questions'] ?? null;
        if (!\is_array($questions) || [] === $questions) {
            throw new InvalidArgumentException('The "questions" option is required and must be a non-empty map of typed questions.');
        }

        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.'/v1/systemone', [
            'auth_bearer' => $this->apiKey,
            'json' => [
                'model' => $model->getName(),
                'state' => $payload,
                'questions' => $questions,
            ],
        ]));
    }
}
