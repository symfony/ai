<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class VeniceClient implements ModelClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Venice;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        return match (true) {
            $model->supports(Capability::INPUT_MESSAGES) => $this->doGenerateCompletion($model, $payload, $options),
            $model->supports(Capability::TEXT_TO_SPEECH) => $this->doTextToSpeech($model, $payload, $options),
            $model->supports(Capability::EMBEDDINGS) => $this->doGenerateEmbeddings($model, $payload),
            default => throw new InvalidArgumentException('Unsupported model capability for Venice client'),
        };
    }

    private function doGenerateCompletion(Model $model, array|string $payload, array $options): RawResultInterface
    {
        if (\is_array($payload) && !\array_key_exists('messages', $payload)) {
            throw new InvalidArgumentException('Payload must contain "messages" key for completion.');
        }

        if (($options['stream'] ?? false) && !isset($options['stream_options']['include_usage'])) {
            $options['stream_options']['include_usage'] = true;
        }

        return new RawHttpResult($this->httpClient->request('POST', 'chat/completions', [
            'json' => [
                'messages' => $payload['messages'],
                'model' => $model->getName(),
                ...$options,
            ],
        ]));
    }

    private function doTextToSpeech(Model $model, array|string $payload, array $options): RawResultInterface
    {
        return new RawHttpResult($this->httpClient->request('POST', 'audio/speech', [
            'json' => [
                'response_format' => 'mp3',
                'input' => \is_string($payload) ? $payload : $payload['text'],
                'model' => $model->getName(),
                ...$options,
            ],
        ]));
    }

    private function doGenerateEmbeddings(Model $model, array|string $payload): RawResultInterface
    {
        return new RawHttpResult($this->httpClient->request('POST', 'embeddings', [
            'json' => [
                'encoding_format' => 'float',
                'input' => \is_string($payload) ? $payload : $payload['text'],
                'model' => $model->getName(),
            ],
        ]));
    }
}
