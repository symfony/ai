<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Handler;

use Mcp\Capability\Registry\ToolReference;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Page;
use Mcp\Schema\Request\ListToolsRequest;
use Mcp\Schema\Result\ListToolsResult;
use Mcp\Schema\Tool;
use Mcp\Server\Session\SessionInterface;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\Handler\FilteredListToolsHandler;
use Symfony\AI\McpBundle\Security\IsGrantedCheckerInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

final class FilteredListToolsHandlerTest extends TestCase
{
    public function testSupportsListToolsRequest()
    {
        $handler = new FilteredListToolsHandler(
            $this->createStub(RegistryInterface::class),
            $this->createStub(IsGrantedCheckerInterface::class),
            $this->createStub(TokenStorageInterface::class),
        );

        $this->assertTrue($handler->supports((new ListToolsRequest())->withId('1')));
    }

    public function testReturnsEmptyListWithoutAuthentication()
    {
        $registry = $this->createStub(RegistryInterface::class);
        $registry->method('getTools')->willReturn(new Page([], null));

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        $handler = new FilteredListToolsHandler($registry, $this->createStub(IsGrantedCheckerInterface::class), $tokenStorage);
        $response = $handler->handle((new ListToolsRequest())->withId('1'), $this->createStub(SessionInterface::class));

        $this->assertInstanceOf(ListToolsResult::class, $response->result);
        $this->assertCount(0, $response->result->tools);
    }

    public function testReturnsEmptyListWithNullToken()
    {
        $registry = $this->createStub(RegistryInterface::class);
        $registry->method('getTools')->willReturn(new Page([], null));

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(new NullToken());

        $handler = new FilteredListToolsHandler($registry, $this->createStub(IsGrantedCheckerInterface::class), $tokenStorage);
        $response = $handler->handle((new ListToolsRequest())->withId('1'), $this->createStub(SessionInterface::class));

        $this->assertCount(0, $response->result->tools);
    }

    public function testFiltersToolsByAuthorizationWhenAuthenticated()
    {
        $allowedTool = new Tool('allowed', ['type' => 'object', 'properties' => [], 'required' => null], null, null);
        $deniedTool = new Tool('denied', ['type' => 'object', 'properties' => [], 'required' => null], null, null);

        $allowedRef = new ToolReference($allowedTool, [self::class, 'dummyAllowed']);
        $deniedRef = new ToolReference($deniedTool, [self::class, 'dummyDenied']);

        $registry = $this->createStub(RegistryInterface::class);
        $registry->method('getTools')->willReturn(new Page([$allowedTool, $deniedTool], null));
        $registry->method('getTool')->willReturnCallback(
            static fn (string $name) => match ($name) {
                'allowed' => $allowedRef,
                'denied' => $deniedRef,
                default => throw new \Mcp\Exception\InvalidArgumentException('Unknown tool: '.$name),
            }
        );

        $checker = $this->createStub(IsGrantedCheckerInterface::class);
        $checker->method('isGranted')->willReturnCallback(
            static fn (array $handler) => 'dummyAllowed' === $handler[1]
        );

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(
            $this->createStub(PostAuthenticationToken::class)
        );

        $handler = new FilteredListToolsHandler($registry, $checker, $tokenStorage);
        $response = $handler->handle((new ListToolsRequest())->withId('1'), $this->createStub(SessionInterface::class));

        $this->assertCount(1, $response->result->tools);
        $this->assertSame('allowed', $response->result->tools[0]->name);
    }

    public function testDeniesToolWithNonArrayHandler()
    {
        $tool = new Tool('closure_tool', ['type' => 'object', 'properties' => [], 'required' => null], null, null);
        $ref = new ToolReference($tool, static fn () => null);

        $registry = $this->createStub(RegistryInterface::class);
        $registry->method('getTools')->willReturn(new Page([$tool], null));
        $registry->method('getTool')->willReturn($ref);

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(
            $this->createStub(PostAuthenticationToken::class)
        );

        $handler = new FilteredListToolsHandler($registry, $this->createStub(IsGrantedCheckerInterface::class), $tokenStorage);
        $response = $handler->handle((new ListToolsRequest())->withId('1'), $this->createStub(SessionInterface::class));

        $this->assertCount(0, $response->result->tools);
    }

    public static function dummyAllowed(): void
    {
    }

    public static function dummyDenied(): void
    {
    }
}
