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

use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\HasToolCallInterface;
use Symfony\AI\Agent\Toolbox\HasToolCallTrait;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * @author Andrey Bolonin <andreybolonin1989@gmail.com>
 */
#[AsTool('call_aware', 'Return the current tool call')]
final class ToolCallAware implements HasToolCallInterface
{
    use HasToolCallTrait;

    public bool $suspend = false;
    public bool $fail = false;

    /**
     * @return array{query: string, before: ToolCall|null, after: ToolCall|null}
     */
    public function __invoke(string $query): array
    {
        $before = $this->getToolCall();
        if ($this->suspend) {
            \Fiber::suspend();
        }
        if ($this->fail) {
            throw new RuntimeException('Tool failed.');
        }

        return ['query' => $query, 'before' => $before, 'after' => $this->getToolCall()];
    }
}
