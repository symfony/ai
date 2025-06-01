<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Response;

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolCallResponse extends BaseResponse
{
    /**
     * @var ToolCall[]
     */
    private readonly array $toolCalls;

    public function __construct(ToolCall ...$toolCalls)
    {
        if (0 === \count($toolCalls)) {
            throw new InvalidArgumentException('Response must have at least one tool call.');
        }

        $this->toolCalls = $toolCalls;
    }

    /**
     * @return ToolCall[]
     */
    public function getContent(): array
    {
        return $this->toolCalls;
    }
}
