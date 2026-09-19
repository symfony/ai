<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\TypeSafe;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Yoan Bernabeu <yoan.bernabeu@gmail.com>
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
        if (!\is_array($payload) || !isset($payload['state'], $payload['questions']) || !\is_array($payload['questions'])) {
            throw new InvalidArgumentException('Jev payload must be an array with a "state" key and a "questions" map.');
        }

        $questions = [];
        foreach ($payload['questions'] as $id => $question) {
            if (!\is_array($question)) {
                throw new InvalidArgumentException(\sprintf('Question "%s" must be an array, "%s" given.', $id, get_debug_type($question)));
            }

            if (isset($question['criteria']) && 'score' !== ($question['type'] ?? null)) {
                // The criteria of noul and choice questions are maps, which must remain JSON objects even with numeric keys
                $question['criteria'] = (object) $question['criteria'];
            }

            $questions[$id] = $question;
        }

        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.'/v1/systemone', [
            'auth_bearer' => $this->apiKey,
            'json' => [
                'model' => $model->getName(),
                'state' => $payload['state'],
                'questions' => (object) $questions,
            ],
        ]));
    }
}
