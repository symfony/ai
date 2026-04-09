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
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawResultInterface;

use function Amp\Websocket\Client\connect;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class WebsocketClient implements ModelClientInterface
{
    public function __construct(
        private readonly string $endpoint,
        #[\SensitiveParameter] private readonly string $apiKey,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Deepgram;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        $payload = new DeepgramPayload($payload);

        return match (true) {
            $model->supports(Capability::TEXT_TO_SPEECH) => $this->doTextToSpeech($model, $payload, $options),
            $model->supports(Capability::SPEECH_TO_TEXT) => $this->doSpeechToText($model, $payload, $options),
            default => throw new InvalidArgumentException(\sprintf('The model "%s" is not supported, please check the Deepgram API.', $model->getName())),
        };
    }

    private function doTextToSpeech(Model $model, DeepgramPayload $payload, array $options): RawResultInterface
    {
        $client = $this->configureClient($model, $options);

        $client->sendText(json_encode([
            'type' => 'Speak',
            'text' => $payload->asTextToSpeechPayload(),
        ]));

        $client->sendText(json_encode([
            'type' => 'Flush',
        ]));

        $result = null;

        /** @var WebsocketMessage $message */
        foreach ($client as $message) {
            if (!$message->isBinary()) {
                continue;
            }

            $result = new InMemoryRawResult($message->read());
        }

        $client->sendText(json_encode([
            'type' => 'Close',
        ]));

        return $result ?? throw new RuntimeException('An error occurred while consuming the websocket.');
    }

    private function doSpeechToText(Model $model, DeepgramPayload $payload, array $options): RawResultInterface
    {
        $client = $this->configureClient($model, $options);

        $client->sendText($payload->asSpeechToTextPayload());
        $client->sendText(json_encode([
            'type' => 'Finalize',
        ]));

        $result = null;

        /** @var WebsocketMessage $message */
        foreach ($client as $message) {
            if (!$message->isText()) {
                continue;
            }

            $result = new InMemoryRawResult(json_decode($message->read(), true));
        }

        $client->sendText(json_encode([
            'type' => 'CloseStream',
        ]));

        return $result ?? throw new RuntimeException('An error occurred while consuming the websocket.');
    }

    private function configureClient(Model $model, array $options): WebsocketConnection
    {
        $handShake = new WebsocketHandshake($this->endpoint, [
            'Authorization' => \sprintf('Token %s', $this->apiKey),
            'model' => $model->getName(),
            ...$options,
        ]);

        return connect($handShake);
    }
}
