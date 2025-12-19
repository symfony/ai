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

#[AsTool('tool_hello_world', 'Function to say hello', method: 'hello')]
#[AsTool('tool_required_params', 'Function to say a number', method: 'bar')]
final class ToolMultiple
{
    /**
     * @param string $world The world to say hello to
     */
    public function hello(string $world): string
    {
        return \sprintf('Hello "%s".', $world);
    }

    /**
     * @param string $text   The text given to the tool
     * @param int    $number A number given to the tool
     */
    public function bar(string $text, int $number): string
    {
        return \sprintf('%s says "%d".', $text, $number);
    }
}
