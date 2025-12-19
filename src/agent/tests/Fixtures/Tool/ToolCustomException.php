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

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Exception\ToolExecutionExceptionInterface;

#[AsTool('tool_custom_exception', description: 'This tool is broken and it exposes the error', method: 'bar')]
final class ToolCustomException
{
    public function bar(): string
    {
        throw new class('Custom error.') extends \RuntimeException implements ToolExecutionExceptionInterface {
            public function getToolCallResult(): array
            {
                return [
                    'error' => true,
                    'error_code' => 'ERR42',
                    'error_description' => 'Temporary error, try again later.',
                ];
            }
        };
    }
}
