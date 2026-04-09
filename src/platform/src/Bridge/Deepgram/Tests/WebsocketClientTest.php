<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Deepgram\Tests;

use Amp\Websocket\WebsocketMessage;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Deepgram\Contract\AudioNormalizer;
use Symfony\AI\Platform\Bridge\Deepgram\Deepgram;
use Symfony\AI\Platform\Bridge\Deepgram\Tests\Fake\FakeWebsocketConnection;
use Symfony\AI\Platform\Bridge\Deepgram\Tests\Fake\FakeWebsocketConnector;
use Symfony\AI\Platform\Bridge\Deepgram\WebsocketClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Result\InMemoryRawResult;

final class WebsocketClientTest extends TestCase
{
    public function testUnsupportedCapabilityThrows()
    {
        $client = new WebsocketClient('wss://api.deepgram.com/v1', 'key', new FakeWebsocketConnector());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The model "foo" is not supported, please check the Deepgram API.');

        $client->request(new Deepgram('foo', [Capability::INPUT_IMAGE]), 'irrelevant');
    }

    public function testTextToSpeechBuildsHandshakeWithAuthAndQueryParams()
    {
        $connection = $this->newConnection([
            $this->jsonMessage(['type' => 'Metadata', 'request_id' => 'r-1']),
            $this->binaryMessage('chunk-1'),
            $this->binaryMessage('chunk-2'),
            $this->jsonMessage(['type' => 'Flushed', 'sequence_id' => 1]),
        ]);
        $connector = new FakeWebsocketConnector($connection);

        $client = new WebsocketClient('wss://api.deepgram.com/v1', 'secret', $connector, 0.0);

        $result = $client->request(
            new Deepgram('aura-2-thalia-en', [Capability::TEXT_TO_SPEECH]),
            ['type' => 'text', 'text' => 'Hello'],
            ['encoding' => 'linear16'],
        );

        $this->assertInstanceOf(InMemoryRawResult::class, $result);
        $this->assertSame('chunk-1chunk-2', $result->getData()['content']);

        $handshake = $connector->lastHandshake;
        $this->assertNotNull($handshake);
        $url = (string) $handshake->getUri();
        $this->assertStringStartsWith('wss://api.deepgram.com/v1/speak?', $url);
        $this->assertStringContainsString('model=aura-2-thalia-en', $url);
        $this->assertStringContainsString('encoding=linear16', $url);

        $headers = array_change_key_case($handshake->getHeaders());
        $this->assertArrayHasKey('authorization', $headers);
        $this->assertSame(['Token secret'], $headers['authorization']);

        $sentText = $connection->sentText;
        $this->assertCount(3, $sentText, 'Speak + Flush + Close text frames');
        $this->assertSame(['type' => 'Speak', 'text' => 'Hello'], json_decode($sentText[0], true));
        $this->assertSame(['type' => 'Flush'], json_decode($sentText[1], true));
        $this->assertSame(['type' => 'Close'], json_decode($sentText[2], true));
    }

    public function testTextToSpeechRaisesWhenServerSendsNothing()
    {
        $connection = $this->newConnection([]);
        $connector = new FakeWebsocketConnector($connection);

        $client = new WebsocketClient('wss://api.deepgram.com/v1', 'secret', $connector, 0.0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Deepgram closed the WebSocket without emitting any audio.');

        $client->request(
            new Deepgram('aura-2-thalia-en', [Capability::TEXT_TO_SPEECH]),
            ['type' => 'text', 'text' => 'Hello'],
        );
    }

    public function testSpeechToTextSendsAudioAsBinaryFrame()
    {
        $connection = $this->newConnection([
            $this->jsonMessage([
                'type' => 'Results',
                'is_final' => true,
                'channel' => ['alternatives' => [['transcript' => 'hello']]],
            ]),
            $this->jsonMessage([
                'type' => 'Results',
                'is_final' => true,
                'channel' => ['alternatives' => [['transcript' => 'world']]],
            ]),
        ]);
        $connector = new FakeWebsocketConnector($connection);

        $payload = (new AudioNormalizer())->normalize(Audio::fromFile(__DIR__.'/Fixtures/audio.mp3'));

        $client = new WebsocketClient('wss://api.deepgram.com/v1', 'secret', $connector, 0.0);
        $result = $client->request(
            new Deepgram('nova-3', [Capability::SPEECH_TO_TEXT]),
            $payload,
            ['language' => 'en'],
        );

        $this->assertInstanceOf(InMemoryRawResult::class, $result);
        $this->assertSame('hello world', $result->getData()['transcript']);

        $handshake = $connector->lastHandshake;
        $this->assertNotNull($handshake);
        $url = (string) $handshake->getUri();
        $this->assertStringContainsString('/listen?', $url);
        $this->assertStringContainsString('model=nova-3', $url);
        $this->assertStringContainsString('language=en', $url);

        $this->assertCount(1, $connection->sentBinary, 'Audio is sent as a single binary frame');
        $this->assertSame((string) file_get_contents(__DIR__.'/Fixtures/audio.mp3'), $connection->sentBinary[0]);

        $this->assertCount(1, $connection->sentText, 'Only CloseStream control frame is sent');
        $this->assertSame(['type' => 'CloseStream'], json_decode($connection->sentText[0], true));
    }

    public function testSpeechToTextRaisesWhenServerSendsNothing()
    {
        $connection = $this->newConnection([]);
        $connector = new FakeWebsocketConnector($connection);

        $payload = (new AudioNormalizer())->normalize(Audio::fromFile(__DIR__.'/Fixtures/audio.mp3'));

        $client = new WebsocketClient('wss://api.deepgram.com/v1', 'secret', $connector, 0.0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Deepgram closed the WebSocket without emitting any transcript.');

        $client->request(new Deepgram('nova-3', [Capability::SPEECH_TO_TEXT]), $payload);
    }

    public function testConnectionIsClosedEvenWhenSendThrows()
    {
        $connection = new FakeWebsocketConnection([], throwOnSend: true);
        $connector = new FakeWebsocketConnector($connection);

        $client = new WebsocketClient('wss://api.deepgram.com/v1', 'secret', $connector, 0.0);

        try {
            $client->request(
                new Deepgram('aura-2-thalia-en', [Capability::TEXT_TO_SPEECH]),
                ['type' => 'text', 'text' => 'Hi'],
            );
            $this->fail('An exception was expected to be thrown.');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertTrue($connection->isClosed(), 'Connection must be closed when the request fails.');
    }

    /**
     * @param list<WebsocketMessage> $messages
     */
    private function newConnection(array $messages): FakeWebsocketConnection
    {
        return new FakeWebsocketConnection($messages);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonMessage(array $payload): WebsocketMessage
    {
        return WebsocketMessage::fromText(json_encode($payload, \JSON_THROW_ON_ERROR));
    }

    private function binaryMessage(string $bytes): WebsocketMessage
    {
        return WebsocketMessage::fromBinary($bytes);
    }
}
