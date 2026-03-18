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
    public function testDelegatesNonToolReferencesToInner(): void
    {
        $reference = new ElementReference(static fn () => 'result');
        $inner = self::createMock(ReferenceHandlerInterface::class);
        $inner->expects(self::once())
            ->method('handle')
            ->with($reference, [])
            ->willReturn('result');

        $handler = new SecurityReferenceHandler($inner, self::createStub(IsGrantedCheckerInterface::class));

        self::assertSame('result', $handler->handle($reference, []));
    }

    public function testAllowsToolWhenGranted(): void
    {
        $tool = new Tool('my_tool', ['type' => 'object', 'properties' => [], 'required' => null], null, null);
        $reference = new ToolReference($tool, [ToolWithoutAttribute::class, 'handle']);

        $checker = self::createStub(IsGrantedCheckerInterface::class);
        $checker->method('isGranted')->willReturn(true);

        $inner = self::createMock(ReferenceHandlerInterface::class);
        $inner->expects(self::once())->method('handle')->willReturn('ok');

        $handler = new SecurityReferenceHandler($inner, $checker);

        self::assertSame('ok', $handler->handle($reference, []));
    }

    public function testDeniesToolWhenNotGranted(): void
    {
        $tool = new Tool('restricted_tool', ['type' => 'object', 'properties' => [], 'required' => null], null, null);
        $reference = new ToolReference($tool, [ToolWithoutAttribute::class, 'handle']);

        $checker = self::createStub(IsGrantedCheckerInterface::class);
        $checker->method('isGranted')->willReturn(false);

        $inner = self::createStub(ReferenceHandlerInterface::class);

        $handler = new SecurityReferenceHandler($inner, $checker);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Access denied to tool "restricted_tool"');

        $handler->handle($reference, []);
    }

    public function testDeniesToolWithNonArrayHandler(): void
    {
        $tool = new Tool('closure_tool', ['type' => 'object', 'properties' => [], 'required' => null], null, null);
        $reference = new ToolReference($tool, static fn () => null);

        $inner = self::createStub(ReferenceHandlerInterface::class);

        $handler = new SecurityReferenceHandler($inner, self::createStub(IsGrantedCheckerInterface::class));

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('unable to resolve handler');

        $handler->handle($reference, []);
    }
}
