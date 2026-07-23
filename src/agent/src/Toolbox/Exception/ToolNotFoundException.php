<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Exception;

use Symfony\AI\Platform\Result\ToolCall;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolNotFoundException extends \RuntimeException implements ExceptionInterface
{
    public function __construct(private ToolCall $toolCall)
    {
        parent::__construct(\sprintf('Tool not found for call: %s.', $toolCall->getName()));
    }

    public function getToolCall(): ToolCall
    {
        return $this->toolCall;
    }
}
