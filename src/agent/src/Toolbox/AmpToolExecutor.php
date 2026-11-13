<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox;

use Amp\Future;
use Symfony\AI\Agent\Exception\LogicException;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\UpdateInterface;
use Symfony\AI\Platform\Result\ToolCall;

use function Amp\async;
use function Amp\Future\awaitAll;

/**
 * Executes tool calls concurrently using Amp and the Revolt event loop.
 *
 * @author Illia Vasylevskyi <ineersa@gmail.com>
 */
final class AmpToolExecutor implements ToolExecutorInterface
{
    public function __construct(
        private readonly ToolboxInterface $toolbox,
    ) {
        if (!class_exists(Future::class)) {
            throw new LogicException('For using the Amp tool executor, the amphp/amp package is required. Try running "composer require amphp/amp:^3".');
        }
    }

    /**
     * @param ToolCall[] $toolCalls
     *
     * @return \Generator<int, UpdateInterface, mixed, ToolResult[]>
     */
    public function execute(array $toolCalls): \Generator
    {
        foreach ($toolCalls as $toolCall) {
            yield new Progress(Progress::STAGE_TOOL_CALL, \sprintf('Executing tool "%s".', $toolCall->getName()), $toolCall);
        }

        $futures = [];
        foreach ($toolCalls as $toolCall) {
            $futures[] = async(fn (): ToolResult => $this->toolbox->execute($toolCall));
        }

        [$errors, $results] = awaitAll($futures);
        if ([] !== $errors) {
            throw $errors[array_key_first($errors)];
        }

        ksort($results);

        return array_values($results);
    }
}
