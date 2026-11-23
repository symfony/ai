<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic\TextToSpeech;

use Symfony\AI\Platform\Bridge\Generic\TextToSpeechModel;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * This generic implementation is based on OpenAI's speech endpoint, which many local and hosted servers
 * (Kokoro-FastAPI, LocalAI, Speaches, VoiceStudio, ...) implement as well. The response body is the raw audio.
 *
 * Options like "voice", "response_format", "speed" or "instructions" are passed through as they are, since
 * the accepted values depend on the server.
 *
 * @author Tac Tacelosky <tacman@gmail.com>
 */
class ModelClient implements ModelClientInterface
{
    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of an OpenAI-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        private readonly ?HttpClientInterface $httpClient,
        string $baseUrl,
        #[\SensitiveParameter] private readonly ?string $apiKey = null,
        private readonly string $path = '/v1/audio/speech',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof TextToSpeechModel;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (isset($options['stream_format']) || isset($options['stream'])) {
            throw new InvalidArgumentException('Streaming text to speech results is not supported yet.');
        }

        $input = \is_string($payload) ? $payload : ($payload['text'] ?? throw new InvalidArgumentException('The payload must contain a "text" key.'));

        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.$this->path, [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Content-Type' => 'application/json'],
            'json' => array_merge($options, [
                'model' => $model->getName(),
                'input' => $input,
            ]),
        ]));
    }
}
