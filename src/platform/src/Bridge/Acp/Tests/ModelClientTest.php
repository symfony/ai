<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Acp\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Bridge\Acp\Acp;
use Symfony\AI\Platform\Bridge\Acp\Exception\CliNotFoundException;
use Symfony\AI\Platform\Bridge\Acp\ModelClient;
use Symfony\AI\Platform\Bridge\Acp\RawProcessResult;
use Symfony\AI\Platform\Bridge\Acp\Transport\TransportInterface;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;

/**
 * @covers \Symfony\AI\Platform\Bridge\Acp\ModelClient
 */
final class ModelClientTest extends TestCase
{
    public function testSupportsAcpModel()
    {
        $client = new ModelClient('dummy', null, [], null, new NullLogger(), new FakeTransport());

        $this->assertTrue($client->supports(new Acp('acp-v1')));
    }

    public function testDoesNotSupportOtherModels()
    {
        $client = new ModelClient('dummy', null, [], null, new NullLogger(), new FakeTransport());

        $this->assertFalse($client->supports(new Model('other')));
    }

    public function testThrowsExceptionWhenCliNotFound()
    {
        $this->expectException(CliNotFoundException::class);
        $this->expectExceptionMessage('ACP binary "" was not found.');

        $client = new ModelClient('');
        $client->request(new Acp('acp-v1'), 'Hello');
    }

    public function testRequestReturnsRawProcessResult()
    {
        $transport = new FakeTransport([
            ['jsonrpc' => '2.0', 'id' => 0, 'result' => ['protocolVersion' => 1, 'agentCapabilities' => [], 'agentInfo' => []]],
            ['jsonrpc' => '2.0', 'id' => 1, 'result' => ['sessionId' => 'session-1']],
        ]);
        $client = new ModelClient('dummy', null, [], null, new NullLogger(), $transport);

        $result = $client->request(new Acp('acp-v1'), 'Hello');

        $this->assertInstanceOf(RawProcessResult::class, $result);
    }

    public function testRequestSendsNormalizedStringPrompt()
    {
        $transport = new FakeTransport([
            ['jsonrpc' => '2.0', 'id' => 0, 'result' => ['protocolVersion' => 1, 'agentCapabilities' => [], 'agentInfo' => []]],
            ['jsonrpc' => '2.0', 'id' => 1, 'result' => ['sessionId' => 'session-1']],
        ]);
        $client = new ModelClient('dummy', null, [], null, new NullLogger(), $transport);

        $client->request(new Acp('acp-v1'), 'Hello');

        $messages = $transport->messages;
        $this->assertCount(3, $messages);
        $this->assertSame('session/prompt', $messages[2]['method']);
        $this->assertSame([['type' => 'text', 'text' => 'Hello']], $messages[2]['params']['prompt']);
    }

    public function testRequestSendsPromptArrayFromPayload()
    {
        $transport = new FakeTransport([
            ['jsonrpc' => '2.0', 'id' => 0, 'result' => ['protocolVersion' => 1, 'agentCapabilities' => [], 'agentInfo' => []]],
            ['jsonrpc' => '2.0', 'id' => 1, 'result' => ['sessionId' => 'session-1']],
        ]);
        $client = new ModelClient('dummy', null, [], null, new NullLogger(), $transport);

        $client->request(new Acp('acp-v1'), ['prompt' => ['What is PHP?']]);

        $messages = $transport->messages;
        $this->assertSame([['type' => 'text', 'text' => 'What is PHP?']], $messages[2]['params']['prompt']);
    }

    public function testRequestNormalizesMessageBagPayload()
    {
        $transport = new FakeTransport([
            ['jsonrpc' => '2.0', 'id' => 0, 'result' => ['protocolVersion' => 1, 'agentCapabilities' => [], 'agentInfo' => []]],
            ['jsonrpc' => '2.0', 'id' => 1, 'result' => ['sessionId' => 'session-1']],
        ]);
        $client = new ModelClient('dummy', null, [], null, new NullLogger(), $transport);
        $payload = Contract::create()->createRequestPayload(new Acp('acp-v1'), new MessageBag(
            Message::forSystem('You are helpful.'),
            Message::ofUser('What is Symfony?'),
        ));

        $client->request(new Acp('acp-v1'), $payload);

        $messages = $transport->messages;
        $this->assertSame([
            ['type' => 'text', 'text' => '[system] You are helpful.'],
            ['type' => 'text', 'text' => '[user] What is Symfony?'],
        ], $messages[2]['params']['prompt']);
    }

    public function testCloseClosesTransport()
    {
        $transport = new FakeTransport();
        $transport->start();
        $client = new ModelClient('dummy', null, [], null, new NullLogger(), $transport);

        $client->close();

        $this->assertFalse($transport->isRunning());
    }
}

final class FakeTransport implements TransportInterface
{
    /**
     * @var list<array<string, mixed>>
     */
    public array $messages = [];

    /**
     * @var list<array<string, mixed>>
     */
    private array $responses;

    private bool $running = false;

    /**
     * @param list<array<string, mixed>> $responses
     */
    public function __construct(array $responses = [])
    {
        $this->responses = $responses;
    }

    public function start(): void
    {
        $this->running = true;
    }

    public function send(array $message): void
    {
        $this->messages[] = $message;
    }

    public function readNextMessage(): array
    {
        if ([] === $this->responses) {
            throw new CliNotFoundException('ACP command cannot be empty.');
        }

        return array_shift($this->responses);
    }

    public function close(): void
    {
        $this->running = false;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }
}
