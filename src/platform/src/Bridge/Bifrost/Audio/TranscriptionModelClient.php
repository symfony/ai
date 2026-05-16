<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bifrost\Audio;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Bifrost speech-to-text client. Issues `POST /v1/audio/transcriptions`
 * (or `/v1/audio/translations` when `Task::TRANSLATION` is requested) on
 * relative paths resolved by the scoped HTTP client built by the Factory,
 * with a multipart body matching the OpenAI Whisper / `gpt-4o-transcribe`
 * contract.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class TranscriptionModelClient implements ModelClientInterface
{
    private const TRANSCRIPTIONS_PATH = '/v1/audio/transcriptions';
    private const TRANSLATIONS_PATH = '/v1/audio/translations';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof TranscriptionModel;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        $task = $options['task'] ?? Task::TRANSCRIPTION;
        $path = Task::TRANSLATION === $task ? self::TRANSLATIONS_PATH : self::TRANSCRIPTIONS_PATH;
        unset($options['task']);

        if ($options['verbose'] ?? false) {
            $options['response_format'] = 'verbose_json';
            unset($options['verbose']);
        }

        return new RawHttpResult($this->httpClient->request('POST', $path, [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'body' => array_merge($options, $payload, ['model' => $model->getName()]),
        ]));
    }
}
