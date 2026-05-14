<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Codex;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Bridge\Codex\Exception\CliNotFoundException;
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
 * Codex CLI client.
 *
 * Spawns the local `codex` binary as a subprocess. Codex's quirk is that there
 * is no `--system-prompt` flag, so the system prompt is prepended to the user
 * prompt. Mirrors the {@see \Symfony\AI\Platform\Bridge\ClaudeCode\CliInvokeClient}
 * pattern.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class CliInvokeClient implements EndpointClientInterface
{
    public const ENDPOINT = 'codex.cli_invoke';

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

        if (isset($options['system_prompt']) && '' !== $options['system_prompt']) {
            $prompt = \sprintf("[System]\n%s\n\n[User]\n%s", $options['system_prompt'], $prompt);
        }

        $cwd = $options['cwd'] ?? $this->workingDirectory;
        unset($options['cwd'], $options['stream'], $options['system_prompt']);

        if (!isset($options['sandbox'])) {
            $options['sandbox'] = 'read-only';
        }

        $command = self::buildCommand($this->getCliBinary(), $prompt, $options);

        $this->logger->info('Spawning Codex CLI subprocess.', [
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
            throw new RuntimeException('Codex CLI did not return any result.');
        }

        if ('error' === ($data['type'] ?? '')) {
            throw new RuntimeException(\sprintf('Codex CLI error: "%s"', $data['message'] ?? 'Unknown error'));
        }

        $text = $data['item']['text'] ?? null;
        if (null === $text) {
            throw new RuntimeException('Codex CLI result does not contain a text field.');
        }

        return new TextResult($text);
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
        $command = [$cliBinary, '--ask-for-approval', 'never', 'exec', '--json'];

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

        $command[] = $prompt;

        return $command;
    }

    private function getCliBinary(): string
    {
        $binary = $this->cliBinary ?? (new ExecutableFinder())->find('codex');

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

            if ('item.completed' === $type
                && 'agent_message' === ($data['item']['type'] ?? '')
                && isset($data['item']['text'])
            ) {
                yield new TextDelta($data['item']['text']);
            }
        }
    }
}
