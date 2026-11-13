<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Server\Handler\Request;

use Mcp\Capability\Registry;
use Mcp\Exception\InvalidCursorException;
use Mcp\Schema\Request\ListToolsRequest;
use Mcp\Schema\Request\PingRequest;
use Mcp\Schema\Tool;
use Mcp\Server\Session\SessionInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\Server\Handler\Request\FilteredListToolsHandler;
use Symfony\AI\McpBundle\Server\ToolListFilterInterface;

class FilteredListToolsHandlerTest extends TestCase
{
    private const ALLOWED_TOOL_ONE = 'allowed-one';
    private const ALLOWED_TOOL_TWO = 'allowed-two';
    private const HIDDEN_TOOL = 'hidden';

    public function testFiltersBeforePaginatingWithoutRemovingToolsFromTheRegistry()
    {
        $registry = new Registry();
        $registry->registerTool($this->createTool(self::ALLOWED_TOOL_ONE), static fn () => null);
        $registry->registerTool($this->createTool(self::HIDDEN_TOOL), static fn () => null);
        $registry->registerTool($this->createTool(self::ALLOWED_TOOL_TWO), static fn () => null);

        $filter = new class(self::HIDDEN_TOOL) implements ToolListFilterInterface {
            public function __construct(private readonly string $hiddenTool)
            {
            }

            public function isVisible(Tool $tool): bool
            {
                return $this->hiddenTool !== $tool->name;
            }
        };
        $handler = new FilteredListToolsHandler($registry, $filter, 1);
        $session = $this->createStub(SessionInterface::class);

        $firstPage = $handler->handle((new ListToolsRequest())->withId(1), $session)->result;
        $this->assertSame([self::ALLOWED_TOOL_ONE], array_column($firstPage->tools, 'name'));
        $this->assertSame(base64_encode('1'), $firstPage->nextCursor);

        $secondPage = $handler->handle((new ListToolsRequest($firstPage->nextCursor))->withId(2), $session)->result;
        $this->assertSame([self::ALLOWED_TOOL_TWO], array_column($secondPage->tools, 'name'));
        $this->assertNull($secondPage->nextCursor);

        $this->assertSame(self::HIDDEN_TOOL, $registry->getTool(self::HIDDEN_TOOL)->tool->name);
    }

    public function testSupportsOnlyListToolsRequests()
    {
        $handler = $this->createHandler();

        $this->assertTrue($handler->supports(new ListToolsRequest()));
        $this->assertFalse($handler->supports(new PingRequest()));
    }

    #[DataProvider('provideInvalidCursors')]
    public function testRejectsAnInvalidCursor(string $cursor)
    {
        $handler = $this->createHandler();

        $this->expectException(InvalidCursorException::class);

        $handler->handle(
            (new ListToolsRequest($cursor))->withId(1),
            $this->createStub(SessionInterface::class),
        );
    }

    public static function provideInvalidCursors(): iterable
    {
        yield 'not base64' => ['not-a-cursor'];
        yield 'negative offset' => [base64_encode('-1')];
        yield 'offset past the end' => [base64_encode('1')];
    }

    private function createHandler(): FilteredListToolsHandler
    {
        return new FilteredListToolsHandler(
            new Registry(),
            new class implements ToolListFilterInterface {
                public function isVisible(Tool $tool): bool
                {
                    return true;
                }
            },
        );
    }

    private function createTool(string $name): Tool
    {
        return new Tool($name, null, ['type' => 'object', 'properties' => [], 'required' => null], null, null);
    }
}
