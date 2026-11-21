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

/**
 * @author Andrey Bolonin <andreybolonin1989@gmail.com>
 */
#[AsTool('call_aware', 'Return the current tool call')]
final class ToolCallAware
{
    public bool $suspend = false;
    public bool $fail = false;

    /**
     * @return array{query: string, before: string, after: string}
     */
    public function __invoke(string $query, string $_toolCallId): array
    {
        $before = $_toolCallId;
        if ($this->suspend) {
            \Fiber::suspend();
        }
        if ($this->fail) {
            throw new RuntimeException('Tool failed.');
        }

        return ['query' => $query, 'before' => $before, 'after' => $_toolCallId];
    }
}
