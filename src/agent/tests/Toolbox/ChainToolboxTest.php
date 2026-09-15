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
use Symfony\AI\Agent\Toolbox\Exception\ToolConfigurationException;
use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
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

    public function testACallRunsOnTheToolboxAdvertisingTheTool()
    {
        $toolCall = new ToolCall('call_1', 'remote');
        $result = new ToolResult($toolCall, 'remote result');

        $chain = new ChainToolbox([
            $this->toolbox([$this->tool('local')]),
            $this->toolbox([$this->tool('remote')], $result),
        ]);

        $this->assertSame($result, $chain->execute($toolCall));
    }

    public function testAnEmptyToolboxDoesNotInterfere()
    {
        $toolCall = new ToolCall('call_1', 'fs_read');
        $result = new ToolResult($toolCall, 'content');
        $tool = $this->tool('fs_read');

        $chain = new ChainToolbox([
            $this->toolbox([]),
            $this->toolbox([$tool], $result),
            $this->toolbox([]),
        ]);

        $this->assertSame([$tool], $chain->getTools());
        $this->assertSame($result, $chain->execute($toolCall));
    }

    public function testACallAfterListingDoesNotListTheToolboxesAgain()
    {
        $toolCall = new ToolCall('call_1', 'clock');
        $result = new ToolResult($toolCall, '12:00');

        $unreachable = new class implements ToolboxInterface {
            public int $listings = 0;

            public function getTools(): array
            {
                ++$this->listings;

                return [];
            }

            public function execute(ToolCall $toolCall): ToolResult
            {
                throw new \LogicException('Not expected to run.');
            }
        };

        $chain = new ChainToolbox([$this->toolbox([$this->tool('clock')], $result), $unreachable]);
        $chain->getTools();

        $this->assertSame($result, $chain->execute($toolCall));
        $this->assertSame($result, $chain->execute($toolCall));
        $this->assertSame(1, $unreachable->listings);
    }

    public function testAToolNameOfferedByTwoToolboxesIsRefusedWhenListing()
    {
        $chain = new ChainToolbox([
            $this->toolbox([$this->tool('local'), $this->tool('fs_read', 'App\Tool\FileReader')]),
            $this->toolbox([$this->tool('fs_read', 'App\Mcp\Toolbox')]),
        ]);

        $this->expectException(ToolConfigurationException::class);
        $this->expectExceptionMessageMatches('/^Tool "fs_read" is offered by more than one toolbox: ".+" \(App\\\\Tool\\\\FileReader::__invoke\) and ".+" \(App\\\\Mcp\\\\Toolbox::__invoke\)\./');

        $chain->getTools();
    }

    public function testAToolNameOfferedByTwoToolboxesIsRefusedWhenExecuting()
    {
        $chain = new ChainToolbox([
            $this->toolbox([$this->tool('fs_read')], new ToolResult(new ToolCall('call_1', 'fs_read'), 'content')),
            $this->toolbox([$this->tool('fs_read')]),
        ]);

        $this->expectException(ToolConfigurationException::class);

        $chain->execute(new ToolCall('call_1', 'fs_read'));
    }

    public function testACallAfterARefusedListingIsRefusedToo()
    {
        $toolCall = new ToolCall('call_1', 'local');

        $changing = new class implements ToolboxInterface {
            /**
             * @var Tool[]
             */
            public array $tools = [];

            public function getTools(): array
            {
                return $this->tools;
            }

            public function execute(ToolCall $toolCall): ToolResult
            {
                throw new \LogicException('Not expected to run.');
            }
        };

        $chain = new ChainToolbox([$this->toolbox([$this->tool('local')], new ToolResult($toolCall, 'local result')), $changing]);
        $chain->getTools();

        $changing->tools = [$this->tool('local')];

        try {
            $chain->getTools();
            $this->fail('The listing is expected to be refused.');
        } catch (ToolConfigurationException) {
        }

        $this->expectException(ToolConfigurationException::class);

        $chain->execute($toolCall);
    }

    public function testAnUnknownToolIsNotFound()
    {
        $chain = new ChainToolbox([$this->toolbox([$this->tool('local')])]);

        $this->expectException(ToolNotFoundException::class);

        $chain->execute(new ToolCall('call_1', 'remote'));
    }

    private function tool(string $name, string $class = 'Foo\Bar'): Tool
    {
        return new Tool(new ExecutionReference($class), $name, 'description', null);
    }

    /**
     * @param Tool[] $tools
     */
    private function toolbox(array $tools, ?ToolResult $result = null): ToolboxInterface
    {
        return new class($tools, $result) implements ToolboxInterface {
            /**
             * @param Tool[] $tools
             */
            public function __construct(
                private readonly array $tools,
                private readonly ?ToolResult $result,
            ) {
            }

            public function getTools(): array
            {
                return $this->tools;
            }

            public function execute(ToolCall $toolCall): ToolResult
            {
                if (null === $this->result) {
                    throw new \LogicException('Not expected to run.');
                }

                return $this->result;
            }
        };
    }
}
