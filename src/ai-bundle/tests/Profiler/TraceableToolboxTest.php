<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AIBundle\Tests\Profiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\AIBundle\Profiler\TraceableToolbox;
use Symfony\AI\Platform\Response\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

#[CoversClass(TraceableToolbox::class)]
#[Small]
final class TraceableToolboxTest extends TestCase
{
    #[Test]
    public function getMap(): void
    {
        $metadata = new Tool(new ExecutionReference('Foo\Bar'), 'bar', 'description', null);
        $toolbox = $this->createToolbox(['tool' => $metadata]);
        $traceableToolbox = new TraceableToolbox($toolbox);

        $map = $traceableToolbox->getTools();

        self::assertSame(['tool' => $metadata], $map);
    }

    #[Test]
    public function execute(): void
    {
        $metadata = new Tool(new ExecutionReference('Foo\Bar'), 'bar', 'description', null);
        $toolbox = $this->createToolbox(['tool' => $metadata]);
        $traceableToolbox = new TraceableToolbox($toolbox);
        $toolCall = new ToolCall('foo', '__invoke');

        $result = $traceableToolbox->execute($toolCall);

        self::assertSame('tool_result', $result);
        self::assertCount(1, $traceableToolbox->calls);
        self::assertSame($toolCall, $traceableToolbox->calls[0]['call']);
        self::assertSame('tool_result', $traceableToolbox->calls[0]['result']);
    }

    /**
     * @param Tool[] $tools
     */
    private function createToolbox(array $tools): ToolboxInterface
    {
        return new class($tools) implements ToolboxInterface {
            public function __construct(
                private readonly array $tools,
            ) {
            }

            public function getTools(): array
            {
                return $this->tools;
            }

            public function execute(ToolCall $toolCall): string
            {
                return 'tool_result';
            }
        };
    }
}
