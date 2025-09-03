<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Whisper;

use Symfony\AI\Platform\Bridge\OpenAi\Whisper;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface as BaseModelClient;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class ModelClient implements BaseModelClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
    ) {
        if ('' === $apiKey) {
            throw new InvalidArgumentException('The API key must not be empty.');
        }
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Whisper;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        $task = $options['task'] ?? Task::TRANSCRIPTION;
        $endpoint = Task::TRANSCRIPTION === $task ? 'transcriptions' : 'translations';
        unset($options['task']);

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('https://api.openai.com/v1/audio/%s', $endpoint), [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'body' => array_merge($options, $payload, ['model' => $model->getName()]),
        ]));
    }
}
