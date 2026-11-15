<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sends decisions to the "POST /v1/systemone" endpoint of Ollama.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class DecisionModelClient implements ModelClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Decision;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (!isset($options['questions'])) {
            throw new InvalidArgumentException(\sprintf('The decision model "%s" requires the "questions" option.', $model->getName()));
        }

        if (!\is_array($payload) || !isset($payload['state'])) {
            throw new InvalidArgumentException(\sprintf('The decision model "%s" requires a string, an array, a "Text" or a "Collection" input.', $model->getName()));
        }

        return new RawHttpResult($this->httpClient->request('POST', 'v1/systemone', ['json' => ['model' => $model->getName()] + $payload + ['questions' => (object) $options['questions']]]));
    }
}
