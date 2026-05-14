<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Deepgram;

use Amp\Websocket\Client\WebsocketConnection;
use Amp\Websocket\Client\WebsocketHandshake;
use Amp\Websocket\WebsocketMessage;
use Revolt\EventLoop;
use Symfony\AI\Platform\Bridge\Deepgram\Websocket\AmpWebsocketConnector;
use Symfony\AI\Platform\Bridge\Deepgram\Websocket\WebsocketConnectorInterface;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawResultInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class WebsocketClient implements ModelClientInterface
{
    private readonly WebsocketConnectorInterface $connector;

    public function __construct(
        private readonly string $endpoint,
        #[\SensitiveParameter] private readonly string $apiKey,
        ?WebsocketConnectorInterface $connector = null,
        private readonly float $keepAliveInterval = 5.0,
    ) {
        $this->connector = $connector ?? new AmpWebsocketConnector();
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Deepgram;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        $deepgramPayload = new DeepgramPayload($payload);

        return match (true) {
            $model->supports(Capability::TEXT_TO_SPEECH) => $this->doTextToSpeech($model, $deepgramPayload, $options),
            $model->supports(Capability::SPEECH_TO_TEXT) => $this->doSpeechToText($model, $deepgramPayload, $options),
            default => throw new InvalidArgumentException(\sprintf('The model "%s" is not supported, please check the Deepgram API.', $model->getName())),
        };
    }

    /**
     * @param array<string, mixed> $options
     */
    private function doTextToSpeech(Model $model, DeepgramPayload $payload, array $options): RawResultInterface
    {
        $connection = $this->connect('speak', $model, $options);

        try {
            $connection->sendText(json_encode([
                'type' => 'Speak',
                'text' => $payload->asTextToSpeechPayload(),
            ], \JSON_THROW_ON_ERROR));

            $connection->sendText(json_encode(['type' => 'Flush'], \JSON_THROW_ON_ERROR));

            $audio = '';
            $metadata = null;
            $flushed = false;

            /** @var WebsocketMessage $message */
            foreach ($connection as $message) {
                $payload = $message->read();
                if (null === $payload) {
                    continue;
                }

                if ($message->isBinary()) {
                    $audio .= $payload;
                    continue;
                }

                $decoded = json_decode($payload, true);
                if (!\is_array($decoded)) {
                    continue;
                }

                if ('Metadata' === ($decoded['type'] ?? null)) {
                    $metadata = $decoded;
                }

                if ('Flushed' === ($decoded['type'] ?? null)) {
                    $flushed = true;
                    $connection->sendText(json_encode(['type' => 'Close'], \JSON_THROW_ON_ERROR));
                    break;
                }
            }

            if (!$flushed && '' === $audio) {
                throw new RuntimeException('Deepgram closed the WebSocket without emitting any audio.');
            }

            return new InMemoryRawResult([
                'content' => $audio,
                'metadata' => $metadata,
            ]);
        } finally {
            $this->safeClose($connection);
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function doSpeechToText(Model $model, DeepgramPayload $payload, array $options): RawResultInterface
    {
        $connection = $this->connect('listen', $model, $options);
        $keepAlive = $this->scheduleKeepAlive($connection);

        try {
            $connection->sendBinary($payload->getAudioBinary());
            $connection->sendText(json_encode(['type' => 'CloseStream'], \JSON_THROW_ON_ERROR));

            $transcripts = [];
            $metadata = null;

            /** @var WebsocketMessage $message */
            foreach ($connection as $message) {
                if (!$message->isText()) {
                    continue;
                }

                $payload = $message->read();
                if (null === $payload) {
                    continue;
                }

                $decoded = json_decode($payload, true);
                if (!\is_array($decoded)) {
                    continue;
                }

                $type = $decoded['type'] ?? null;

                if ('Metadata' === $type) {
                    $metadata = $decoded;
                    continue;
                }

                if ('Results' !== $type || true !== ($decoded['is_final'] ?? false)) {
                    continue;
                }

                $channel = $decoded['channel'] ?? null;
                $alternatives = \is_array($channel) ? ($channel['alternatives'] ?? null) : null;
                if (!\is_array($alternatives) || !isset($alternatives[0]) || !\is_array($alternatives[0])) {
                    continue;
                }

                $alternative = $alternatives[0]['transcript'] ?? null;
                if (\is_string($alternative) && '' !== $alternative) {
                    $transcripts[] = $alternative;
                }
            }

            if ([] === $transcripts && null === $metadata) {
                throw new RuntimeException('Deepgram closed the WebSocket without emitting any transcript.');
            }

            $transcript = implode(' ', $transcripts);

            return new InMemoryRawResult([
                'transcript' => $transcript,
                'results' => [
                    'channels' => [
                        ['alternatives' => [['transcript' => $transcript]]],
                    ],
                ],
                'metadata' => $metadata,
            ]);
        } finally {
            if (null !== $keepAlive) {
                EventLoop::cancel($keepAlive);
            }
            $this->safeClose($connection);
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function connect(string $route, Model $model, array $options): WebsocketConnection
    {
        $query = http_build_query([
            'model' => $model->getName(),
            ...$options,
        ]);

        $url = \sprintf('%s/%s?%s', rtrim($this->endpoint, '/'), $route, $query);

        $handshake = new WebsocketHandshake($url, [
            'Authorization' => \sprintf('Token %s', $this->apiKey),
        ]);

        return $this->connector->connect($handshake);
    }

    private function scheduleKeepAlive(WebsocketConnection $connection): ?string
    {
        if ($this->keepAliveInterval <= 0.0) {
            return null;
        }

        return EventLoop::repeat($this->keepAliveInterval, static function () use ($connection): void {
            if ($connection->isClosed()) {
                return;
            }

            $connection->sendText(json_encode(['type' => 'KeepAlive'], \JSON_THROW_ON_ERROR));
        });
    }

    private function safeClose(WebsocketConnection $connection): void
    {
        if ($connection->isClosed()) {
            return;
        }

        try {
            $connection->close();
        } catch (\Throwable) {
            // The connection might already be in a closed state from the server side; nothing to surface.
        }
    }
}
