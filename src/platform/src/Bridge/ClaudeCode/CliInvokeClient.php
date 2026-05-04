<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ClaudeCode;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Bridge\ClaudeCode\Exception\CliNotFoundException;
use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Claude Code CLI client.
 *
 * Spawns the local `claude` binary as a subprocess, maps Symfony AI options
 * to CLI flags, parses the stream-json output. Demonstrates the
 * EndpointClient pattern for non-HTTP bridges.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class CliInvokeClient implements EndpointClientInterface
{
    public const ENDPOINT = 'claudecode.cli_invoke';

    /**
     * @var array<string, string>
     */
    private const OPTION_FLAG_MAP = [
        'tools' => '--allowedTools',
        'allowed_tools' => '--allowedTools',
    ];

    /**
     * @param array<string, string> $environment
     */
    public function __construct(
        private readonly ?string $cliBinary = null,
        private readonly ?string $workingDirectory = null,
        private readonly ?float $timeout = 300,
        private readonly array $environment = [],
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }

    public function supports(Model $model): bool
    {
        return $model->supportsEndpoint(self::ENDPOINT);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if (!isset($options['model'])) {
            $options['model'] = $model->getName();
        }

        $prompt = $this->extractPrompt($payload);

        if (\is_array($payload)) {
            $options = array_merge($payload, $options);
            unset($options['prompt']);
        }

        $cwd = $options['cwd'] ?? $this->workingDirectory;
        unset($options['cwd'], $options['stream']);

        $command = self::buildCommand($this->getCliBinary(), $prompt, $options);

        $this->logger->info('Spawning Claude Code CLI subprocess.', [
            'command' => implode(' ', array_map('escapeshellarg', $command)),
            'cwd' => $cwd,
        ]);

        $process = new Process($command, $cwd, $this->environment, null, $this->timeout);
        $process->start();

        return new RawProcessResult($process);
    }

    public function convert(RawResultInterface $raw, array $options = []): ResultInterface
    {
        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($raw));
        }

        $data = $raw->getData();

        if ([] === $data) {
            throw new RuntimeException('Claude Code CLI did not return any result.');
        }

        if (isset($data['is_error']) && true === $data['is_error']) {
            throw new RuntimeException(\sprintf('Claude Code CLI error: "%s"', $data['result'] ?? 'Unknown error'));
        }

        if (!isset($data['result'])) {
            throw new RuntimeException('Claude Code CLI result does not contain a "result" field.');
        }

        return new TextResult($data['result']);
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<string>
     */
    public static function buildCommand(string $cliBinary, string $prompt, array $options = []): array
    {
        $command = [$cliBinary, '--output-format', 'stream-json', '--verbose', '--include-partial-messages'];

        foreach ($options as $key => $value) {
            $flag = self::OPTION_FLAG_MAP[$key] ?? '--'.str_replace('_', '-', $key);

            if (\is_array($value)) {
                foreach ($value as $item) {
                    $command[] = $flag;
                    $command[] = (string) $item;
                }
            } elseif (true === $value) {
                $command[] = $flag;
            } elseif (false !== $value) {
                $command[] = $flag;
                $command[] = (string) $value;
            }
        }

        $command[] = '-p';
        $command[] = $prompt;

        return $command;
    }

    private function getCliBinary(): string
    {
        $binary = $this->cliBinary ?? (new ExecutableFinder())->find('claude');

        if (null === $binary || !is_executable($binary)) {
            throw new CliNotFoundException();
        }

        return $binary;
    }

    /**
     * @param array<string|int, mixed>|string $payload
     */
    private function extractPrompt(array|string $payload): string
    {
        if (\is_string($payload)) {
            return $payload;
        }

        return (string) ($payload['prompt'] ?? json_encode($payload, \JSON_THROW_ON_ERROR));
    }

    private function convertStream(RawResultInterface $result): \Generator
    {
        foreach ($result->getDataStream() as $data) {
            $type = $data['type'] ?? '';

            if ('stream_event' === $type
                && 'content_block_delta' === ($data['event']['type'] ?? '')
                && 'text_delta' === ($data['event']['delta']['type'] ?? '')
            ) {
                yield new TextDelta($data['event']['delta']['text']);
            }
        }
    }
}
