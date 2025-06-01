<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Event;

use Symfony\AI\Agent\Toolbox\ToolCallResult;
use Symfony\AI\Platform\Response\ResponseInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolCallsExecuted
{
    /**
     * @var ToolCallResult[]
     */
    public readonly array $toolCallResults;
    public ResponseInterface $response;

    public function __construct(ToolCallResult ...$toolCallResults)
    {
        $this->toolCallResults = $toolCallResults;
    }

    public function hasResponse(): bool
    {
        return isset($this->response);
    }
}
