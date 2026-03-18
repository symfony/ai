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

use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\Security\IsGrantedChecker;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class IsGrantedCheckerTest extends TestCase
{
    public function testGrantsAccessWithoutIsGrantedAttribute(): void
    {
        $authChecker = self::createStub(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(false);

        $checker = new IsGrantedChecker($authChecker);

        self::assertTrue($checker->isGranted([ToolWithoutAttribute::class, 'handle']));
    }

    public function testGrantsAccessWhenAuthorized(): void
    {
        $authChecker = self::createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects(self::once())
            ->method('isGranted')
            ->with('ROLE_ADMIN')
            ->willReturn(true);

        $checker = new IsGrantedChecker($authChecker);

        self::assertTrue($checker->isGranted([ToolWithAttribute::class, 'handle']));
    }

    public function testDeniesAccessWhenNotAuthorized(): void
    {
        $authChecker = self::createStub(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(false);

        $checker = new IsGrantedChecker($authChecker);

        self::assertFalse($checker->isGranted([ToolWithAttribute::class, 'handle']));
    }

    public function testDeniesAccessOnReflectionFailure(): void
    {
        $authChecker = self::createStub(AuthorizationCheckerInterface::class);

        $checker = new IsGrantedChecker($authChecker);

        self::assertFalse($checker->isGranted(['NonExistentClass', 'nonExistentMethod']));
    }

    public function testChecksAllAttributes(): void
    {
        $authChecker = self::createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects(self::exactly(2))
            ->method('isGranted')
            ->willReturnCallback(static fn (string $attr) => match ($attr) {
                'ROLE_ADMIN' => true,
                'ROLE_SUPER' => false,
                default => false,
            });

        $checker = new IsGrantedChecker($authChecker);

        self::assertFalse($checker->isGranted([ToolWithMultipleAttributes::class, 'handle']));
    }
}

class ToolWithoutAttribute
{
    public function handle(): void
    {
    }
}

class ToolWithAttribute
{
    #[IsGranted('ROLE_ADMIN')]
    public function handle(): void
    {
    }
}

class ToolWithMultipleAttributes
{
    #[IsGranted('ROLE_ADMIN')]
    #[IsGranted('ROLE_SUPER')]
    public function handle(): void
    {
    }
}
