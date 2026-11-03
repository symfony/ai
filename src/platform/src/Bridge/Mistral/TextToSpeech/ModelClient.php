<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\TextToSpeech;

use Symfony\AI\Platform\Bridge\Mistral\TextToSpeech;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Anthony Hacart <anthony.hacart@gmail.com>
 */
final class ModelClient implements ModelClientInterface
{
    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of a Mistral-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://api.mistral.ai',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof TextToSpeech;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (isset($options['stream'])) {
            throw new InvalidArgumentException('Streaming text to speech results is not supported yet.');
        }

        $input = \is_string($payload) ? $payload : ($payload['text'] ?? throw new InvalidArgumentException('The payload must contain a "text" key.'));

        $options = array_merge($model->getOptions(), $options);

        // "voice" is the option name shared by the other text-to-speech bridges, Mistral expects "voice_id"
        if (isset($options['voice'])) {
            $options['voice_id'] = $options['voice'];
            unset($options['voice']);
        }

        if (!isset($options['voice_id']) && !isset($options['ref_audio'])) {
            throw new InvalidArgumentException('Either the "voice" or the "ref_audio" option is required for Mistral text to speech requests.');
        }

        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.'/v1/audio/speech', [
            'auth_bearer' => $this->apiKey,
            'json' => array_merge($options, ['model' => $model->getName(), 'input' => $input]),
        ]));
    }
}
