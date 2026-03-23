<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Security;

use Mcp\Capability\Registry\ElementReference;
use Mcp\Capability\Registry\ReferenceHandlerInterface;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Schema\Tool;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\Security\IsGrantedCheckerInterface;
use Symfony\AI\McpBundle\Security\SecurityReferenceHandler;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class SecurityReferenceHandlerTest extends TestCase
{
    public function testDelegatesNonToolReferencesToInner()
    {
        $reference = new ElementReference(static fn () => 'result');
        $inner = self::createMock(ReferenceHandlerInterface::class);
        $inner->expects($this->once())
            ->method('handle')
            ->with($reference, [])
            ->willReturn('result');

        $handler = new SecurityReferenceHandler($inner, $this->createStub(IsGrantedCheckerInterface::class));

        $this->assertSame('result', $handler->handle($reference, []));
    }

    public function testAllowsToolWhenGranted()
    {
        $tool = new Tool('my_tool', ['type' => 'object', 'properties' => [], 'required' => null], null, null);
        $reference = new ToolReference($tool, [ToolWithoutAttribute::class, 'handle']);

        $checker = $this->createStub(IsGrantedCheckerInterface::class);
        $checker->method('isGranted')->willReturn(true);

        $inner = self::createMock(ReferenceHandlerInterface::class);
        $inner->expects($this->once())->method('handle')->willReturn('ok');

        $handler = new SecurityReferenceHandler($inner, $checker);

        $this->assertSame('ok', $handler->handle($reference, []));
    }

    public function testDeniesToolWhenNotGranted()
    {
        $tool = new Tool('restricted_tool', ['type' => 'object', 'properties' => [], 'required' => null], null, null);
        $reference = new ToolReference($tool, [ToolWithoutAttribute::class, 'handle']);

        $checker = $this->createStub(IsGrantedCheckerInterface::class);
        $checker->method('isGranted')->willReturn(false);

        $inner = $this->createStub(ReferenceHandlerInterface::class);

        $handler = new SecurityReferenceHandler($inner, $checker);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Access denied to tool "restricted_tool"');

        $handler->handle($reference, []);
    }

    public function testDeniesToolWithNonArrayHandler()
    {
        $tool = new Tool('closure_tool', ['type' => 'object', 'properties' => [], 'required' => null], null, null);
        $reference = new ToolReference($tool, static fn () => null);

        $inner = $this->createStub(ReferenceHandlerInterface::class);

        $handler = new SecurityReferenceHandler($inner, $this->createStub(IsGrantedCheckerInterface::class));

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('unable to resolve handler');

        $handler->handle($reference, []);
    }
}
