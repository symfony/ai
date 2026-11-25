<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ovh\TextToSpeech;

use Symfony\AI\Platform\Bridge\Ovh\TextToSpeech;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://nvr-tts-en-us.endpoints.kepler.ai.cloud.ovh.net/doc/openapi.json
 *
 * @author Loïc Sapone <loic@sapone.fr>
 */
final class ModelClient implements ModelClientInterface
{
    private const LINEAR_PCM = 1;
    private const DEFAULT_SAMPLE_RATE = 16000;

    private readonly string $domain;

    /**
     * @param string $domain Domain the models are served from, each one on a subdomain named after it
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $domain = 'endpoints.kepler.ai.cloud.ovh.net',
    ) {
        $this->domain = trim($domain, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof TextToSpeech;
    }

    /**
     * @param array{voice?: string, language_code?: string, encoding?: int, sample_rate_hz?: int} $options
     */
    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (!isset($options['voice'])) {
            throw new InvalidArgumentException('The "voice" option is required for text-to-speech requests.');
        }

        if (\is_string($payload)) {
            $text = $payload;
        } elseif (isset($payload['text'])) {
            $text = $payload['text'];
        } else {
            throw new InvalidArgumentException('The payload must be a string or an array with a "text" key.');
        }

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('https://%s.%s/api/v1/tts/text_to_audio', $model->getName(), $this->domain), [
            'auth_bearer' => $this->apiKey,
            'json' => [
                'text' => $text,
                'language_code' => $options['language_code'] ?? $this->guessLanguageCode($model->getName()),
                'encoding' => $options['encoding'] ?? self::LINEAR_PCM,
                'sample_rate_hz' => $options['sample_rate_hz'] ?? self::DEFAULT_SAMPLE_RATE,
                'voice_name' => $options['voice'],
            ],
        ]));
    }

    private function guessLanguageCode(string $modelName): string
    {
        // "nvr-tts-en-us" speaks "en-US"
        if (1 !== preg_match('/^nvr-tts-([a-z]{2})-([a-z]{2})$/', $modelName, $matches)) {
            throw new InvalidArgumentException(\sprintf('The language of the model "%s" is unknown, pass it with the "language_code" option.', $modelName));
        }

        return $matches[1].'-'.strtoupper($matches[2]);
    }
}
