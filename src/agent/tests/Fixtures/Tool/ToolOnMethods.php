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

final class ToolOnMethods
{
    /**
     * @param string $world The world to say hello to
     */
    #[AsTool('tool_hello_world', 'Function to say hello')]
    public function hello(string $world): string
    {
        return \sprintf('Hello "%s".', $world);
    }

    #[AsTool('tool_no_params', 'A tool without parameters', method: 'bar')]
    public function bar(): string
    {
        return 'bar';
    }

    public function notATool(): string
    {
        return 'not a tool';
    }
}
