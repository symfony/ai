<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\ChainToolbox;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

final class ChainToolboxTest extends TestCase
{
    public function testTheToolsOfEveryToolboxAreOffered()
    {
        $local = $this->tool('local');
        $remote = $this->tool('remote');

        $chain = new ChainToolbox([$this->toolbox([$local]), $this->toolbox([$remote])]);

        $this->assertSame([$local, $remote], $chain->getTools());
    }

    private function tool(string $name): Tool
    {
        return new Tool(new ExecutionReference('Foo\Bar'), $name, 'description', null);
    }

    /**
     * @param Tool[] $tools
     */
    private function toolbox(array $tools): ToolboxInterface
    {
        return new class($tools) implements ToolboxInterface {
            /**
             * @param Tool[] $tools
             */
            public function __construct(private readonly array $tools)
            {
            }

            public function getTools(): array
            {
                return $this->tools;
            }

            public function execute(ToolCall $toolCall): ToolResult
            {
                throw new \LogicException('Not expected to run.');
            }
        };
    }
}
