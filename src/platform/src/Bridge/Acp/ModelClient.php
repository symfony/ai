<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Acp;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Bridge\Acp\Exception\ProtocolException;
use Symfony\AI\Platform\Bridge\Acp\Exception\TransportException;
use Symfony\AI\Platform\Bridge\Acp\Transport\ProcessTransport;
use Symfony\AI\Platform\Bridge\Acp\Transport\TransportInterface;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawResultInterface;

/**
 * ACP model client using transport abstraction.
 */
final class ModelClient implements ModelClientInterface
{
    private TransportInterface $transport;
    private ?string $sessionId = null;
    private int $nextId = 0;
    private bool $handshakeDone = false;

    /**
     * @var array<string, mixed>
     */
    private array $agentCapabilities = [];

    /**
     * @var array<string, mixed>
     */
    private array $agentInfo = [];

    /**
     * @param array<string, string>       $environment
     * @param callable(string): void|null $onStatus
     */
    public function __construct(
        private readonly string $command,
        private readonly ?string $workingDirectory = null,
        private readonly array $environment = [],
        /** @var callable(string): void|null */
        private $onStatus = null,
        private readonly LoggerInterface $logger = new NullLogger(),
        ?TransportInterface $transport = null,
    ) {
        $this->transport = $transport ?? new ProcessTransport(
            $command,
            $workingDirectory,
            $environment,
            $logger,
        );
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Acp;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if (!$model instanceof Acp) {
            throw new ProtocolException(\sprintf('Unsupported model "%s".', $model::class));
        }

        $this->transport->start();

        if (!$this->handshakeDone) {
            $this->performHandshake($model, $options);
        }

        $prompt = $this->normalizePrompt($payload);
        $requestId = $this->nextId++;

        $this->transport->send([
            'jsonrpc' => '2.0',
            'id' => $requestId,
            'method' => 'session/prompt',
            'params' => [
                'sessionId' => $this->sessionId,
                'prompt' => $prompt,
            ],
        ]);

        return new RawProcessResult($this, $requestId);
    }

    public function close(): void
    {
        if (!$this->transport->isRunning()) {
            return;
        }

        if (null !== $this->sessionId && ($this->agentCapabilities['sessionCapabilities']['close'] ?? null) !== null) {
            $requestId = $this->nextId++;
            $this->transport->send([
                'jsonrpc' => '2.0',
                'id' => $requestId,
                'method' => 'session/close',
                'params' => ['sessionId' => $this->sessionId],
            ]);
            $this->readUntilResponse($requestId);
        }

        $this->transport->close();
        $this->sessionId = null;
        $this->handshakeDone = false;
        $this->agentCapabilities = [];
        $this->agentInfo = [];
    }

    /**
     * @return array<string, mixed>
     */
    public function readNextMessage(): array
    {
        return $this->transport->readNextMessage();
    }

    /**
     * @return array<string, mixed>
     */
    public function getAgentCapabilities(): array
    {
        return $this->agentCapabilities;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAgentInfo(): array
    {
        return $this->agentInfo;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function performHandshake(Acp $model, array $options): void
    {
        if (null !== $this->onStatus) {
            ($this->onStatus)('initializing');
        }

        $requestId = $this->nextId++;
        $this->transport->send([
            'jsonrpc' => '2.0',
            'id' => $requestId,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => $model->protocolVersion,
                'clientCapabilities' => (object) $model->clientCapabilities,
                'clientInfo' => [
                    'name' => 'symfony-ai',
                    'title' => 'Symfony AI',
                    'version' => '0.1.0',
                ],
            ],
        ]);

        $response = $this->readUntilResponse($requestId);
        $result = $this->extractResult($response);
        $protocolVersion = $result['protocolVersion'] ?? null;
        if (!\is_int($protocolVersion) || $protocolVersion < 1) {
            throw new ProtocolException('ACP returned an unsupported protocol version.');
        }

        $model->protocolVersion = min($model->protocolVersion, $protocolVersion);
        $this->agentCapabilities = \is_array($result['agentCapabilities'] ?? null) ? $result['agentCapabilities'] : [];
        $this->agentInfo = \is_array($result['agentInfo'] ?? null) ? $result['agentInfo'] : [];

        $missingCapabilities = array_diff($model->requiredAgentCapabilities, array_keys(array_filter($this->agentCapabilities)));
        if ([] !== $missingCapabilities) {
            throw new ProtocolException(\sprintf('ACP agent is missing required capabilities: %s.', implode(', ', $missingCapabilities)));
        }

        $sessionRequestId = $this->nextId++;
        $params = [
            'cwd' => $this->resolveWorkingDirectory($options),
            'mcpServers' => [],
        ];

        if (isset($options['additionalDirectories']) && [] !== $options['additionalDirectories']) {
            $params['additionalDirectories'] = $options['additionalDirectories'];
        }

        $this->transport->send([
            'jsonrpc' => '2.0',
            'id' => $sessionRequestId,
            'method' => 'session/new',
            'params' => $params,
        ]);

        $sessionResponse = $this->readUntilResponse($sessionRequestId);
        $sessionResult = $this->extractResult($sessionResponse);
        $sessionId = $sessionResult['sessionId'] ?? null;

        if (!\is_string($sessionId) || '' === $sessionId) {
            throw new ProtocolException('ACP did not return a sessionId.');
        }

        $this->sessionId = $sessionId;
        $this->handshakeDone = true;

        if (null !== $this->onStatus) {
            ($this->onStatus)('handshake_complete');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readUntilResponse(int $requestId): array
    {
        while (true) {
            $message = $this->readNextMessage();
            if (($message['id'] ?? null) !== $requestId) {
                continue;
            }

            return $message;
        }
    }

    /**
     * @param array<string, mixed> $message
     *
     * @return array<string, mixed>
     */
    private function extractResult(array $message): array
    {
        if (isset($message['error'])) {
            $error = $message['error'];
            $messageText = \is_array($error) ? (string) ($error['message'] ?? 'Unknown ACP error') : 'Unknown ACP error';
            throw new ProtocolException($messageText);
        }

        return \is_array($message['result'] ?? null) ? $message['result'] : [];
    }

    /**
     * @param array<string, mixed>|string $payload
     *
     * @return list<array<string, mixed>>
     */
    private function normalizePrompt(array|string $payload): array
    {
        if (\is_string($payload)) {
            return [['type' => 'text', 'text' => $payload]];
        }

        $prompt = $payload['prompt'] ?? null;
        if (\is_array($prompt)) {
            return $this->normalizePromptEntries($prompt, $payload);
        }

        $messages = $payload['messages'] ?? null;
        if (\is_array($messages)) {
            return $this->normalizeMessages($messages, $payload);
        }

        return [['type' => 'text', 'text' => json_encode($payload, \JSON_THROW_ON_ERROR)]];
    }

    /**
     * @param list<mixed>  $prompt
     * @param array<mixed> $fallbackPayload
     *
     * @return list<array<string, mixed>>
     */
    private function normalizePromptEntries(array $prompt, array $fallbackPayload): array
    {
        $normalized = [];
        foreach ($prompt as $entry) {
            if (\is_string($entry)) {
                $normalized[] = ['type' => 'text', 'text' => $entry];
                continue;
            }

            if (!\is_array($entry)) {
                continue;
            }

            $type = $entry['type'] ?? 'text';
            if ('text' === $type && isset($entry['text']) && \is_string($entry['text'])) {
                $normalized[] = ['type' => 'text', 'text' => $entry['text']];
                continue;
            }

            $normalized[] = $entry;
        }

        if ([] !== $normalized) {
            return $normalized;
        }

        return [['type' => 'text', 'text' => json_encode($fallbackPayload, \JSON_THROW_ON_ERROR)]];
    }

    /**
     * @param list<mixed>  $messages
     * @param array<mixed> $fallbackPayload
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeMessages(array $messages, array $fallbackPayload): array
    {
        $parts = [];

        foreach ($messages as $message) {
            if (!\is_array($message)) {
                continue;
            }

            $text = $this->normalizeMessageContent($message['content'] ?? null);
            if (null === $text) {
                continue;
            }

            $role = $message['role'] ?? 'user';
            $parts[] = ['type' => 'text', 'text' => \sprintf('[%s] %s', $role, $text)];
        }

        if ([] !== $parts) {
            return $parts;
        }

        return [['type' => 'text', 'text' => json_encode($fallbackPayload, \JSON_THROW_ON_ERROR)]];
    }

    private function normalizeMessageContent(mixed $content): ?string
    {
        if (\is_string($content)) {
            return $content;
        }

        if (!\is_array($content)) {
            return null;
        }

        $parts = [];
        foreach ($content as $part) {
            if (\is_string($part)) {
                $parts[] = $part;
                continue;
            }

            if (!\is_array($part)) {
                continue;
            }

            if (($part['type'] ?? null) === 'text' && \is_string($part['text'] ?? null)) {
                $parts[] = $part['text'];
            }
        }

        if ([] === $parts) {
            return null;
        }

        return implode("\n", $parts);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function resolveWorkingDirectory(array $options): string
    {
        $cwd = $options['cwd'] ?? $this->workingDirectory ?? getcwd();

        if (!\is_string($cwd) || '' === $cwd) {
            throw new TransportException('ACP working directory must be a non-empty string.');
        }

        return $cwd;
    }
}
