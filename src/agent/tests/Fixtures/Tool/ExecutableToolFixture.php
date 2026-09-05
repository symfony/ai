<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Fixtures\Tool;

use Symfony\AI\Agent\Toolbox\ExecutableToolInterface;
use Symfony\AI\Agent\Toolbox\ToolFactoryInterface;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

/**
 * Test fixture: one object representing N "remote" tools, each dispatched via
 * {@see ExecutableToolInterface::execute()} without going through the
 * reflection-based argument resolver.
 */
final class ExecutableToolFixture implements ExecutableToolInterface, ToolFactoryInterface
{
    /**
     * @var array<string, callable>
     */
    private array $handlers;

    public function __construct()
    {
        $this->handlers = [
            'remote_echo' => static fn (array $args): string => 'echo: '.($args['text'] ?? ''),
            'remote_sum' => static fn (array $args): int => (int) ($args['a'] ?? 0) + (int) ($args['b'] ?? 0),
        ];
    }

    public function execute(Tool $metadata, ToolCall $toolCall): mixed
    {
        $name = $metadata->getReference()->getMethod();

        return ($this->handlers[$name])($toolCall->getArguments());
    }

    public function getTool(object|string $reference): iterable
    {
        foreach ($this->handlers as $remoteName => $_) {
            yield new Tool(
                new ExecutionReference(self::class, $remoteName),
                $remoteName,
                'Remote tool '.$remoteName,
            );
        }
    }
}
