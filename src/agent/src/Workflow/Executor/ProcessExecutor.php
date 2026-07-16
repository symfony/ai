<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow\Executor;

use Symfony\AI\Agent\Exception\WorkflowExecutorException;
use Symfony\AI\Agent\Workflow\ExecutorInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\Component\Process\Process;

/**
 * Executor that runs a Symfony Process command.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ProcessExecutor implements ExecutorInterface
{
    /**
     * @param list<string>|\Closure(WorkflowStateInterface, non-empty-string): list<string> $command        Static command array or a closure that builds the command from state
     * @param non-empty-string                                                              $outputKey      State key to write the process standard output into
     * @param float|null                                                                    $timeout        Process timeout in seconds (null for no timeout)
     * @param non-empty-string|null                                                         $errorOutputKey State key to write the process error output into, or null to skip
     * @param non-empty-string|null                                                         $exitCodeKey    State key to write the process exit code into, or null to skip
     */
    public function __construct(
        private readonly array|\Closure $command,
        private readonly string $outputKey = 'process_output',
        private readonly ?float $timeout = 60,
        private readonly ?string $errorOutputKey = null,
        private readonly ?string $exitCodeKey = null,
    ) {
    }

    public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
    {
        if ($this->command instanceof \Closure) {
            $command = ($this->command)($state, $place);
        } else {
            $command = $this->command;
        }

        $process = new Process($command);
        $process->setTimeout($this->timeout);

        try {
            $process->mustRun();
        } catch (\Throwable $e) {
            throw new WorkflowExecutorException(\sprintf('Process execution failed at place "%s": "%s".', $place, $e->getMessage()), 0, $e);
        }

        $state = $state->set($this->outputKey, $process->getOutput());

        if (null !== $this->errorOutputKey) {
            $state = $state->set($this->errorOutputKey, $process->getErrorOutput());
        }

        if (null !== $this->exitCodeKey) {
            $state = $state->set($this->exitCodeKey, $process->getExitCode());
        }

        return $state;
    }
}
