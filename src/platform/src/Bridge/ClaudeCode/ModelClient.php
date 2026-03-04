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
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Spawns the Claude Code CLI as a subprocess and returns the result.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelClient implements ModelClientInterface
{
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

    public function supports(Model $model): bool
    {
        return $model instanceof ClaudeCode;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if (!isset($options['model'])) {
            $options['model'] = $model->getName();
        }

        $prompt = $this->extractPrompt($payload);

        // Merge payload fields (e.g. system_prompt from the normalizer) into
        // options, giving explicit options priority.
        if (\is_array($payload)) {
            $options = array_merge($payload, $options);
            unset($options['prompt']);
        }

        $cwd = $options['cwd'] ?? $this->workingDirectory;
        unset($options['cwd'], $options['stream']);
        $command = $this->buildCommand($prompt, $options);

        $this->logger->info('Spawning Claude Code CLI subprocess.', [
            'command' => implode(' ', array_map('escapeshellarg', $command)),
            'cwd' => $cwd,
        ]);

        $process = new Process($command, $cwd, $this->environment, null, $this->timeout);
        $process->start();

        return new RawProcessResult($process);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return string[]
     */
    public function buildCommand(string $prompt, array $options = []): array
    {
        $command = [$this->getCliBinary(), '--output-format', 'stream-json', '--verbose', '--include-partial-messages'];

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
}
