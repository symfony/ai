<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AIBundle\Tests\Security;

use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\Event\ToolCallArgumentsResolved;
use Symfony\AI\AIBundle\Security\EventListener\IsGrantedToolAttributeListener;
use Symfony\AI\AIBundle\Tests\Fixture\Tool\ToolWithIsGrantedOnClass;
use Symfony\AI\AIBundle\Tests\Fixture\Tool\ToolWithIsGrantedOnMethod;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[CoversClass(IsGrantedToolAttributeListener::class)]
#[UsesClass(EventDispatcher::class)]
#[UsesClass(ToolCallArgumentsResolved::class)]
#[UsesClass(Expression::class)]
#[UsesClass(AccessDeniedException::class)]
#[UsesClass(Tool::class)]
#[UsesClass(ExecutionReference::class)]
class IsGrantedToolAttributeListenerTest extends TestCase
{
    private EventDispatcherInterface $dispatcher;
    private AuthorizationCheckerInterface&MockObject $authChecker;

    #[Before]
    protected function setupTool(): void
    {
        $this->dispatcher = new EventDispatcher();
        $this->authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->dispatcher->addListener(ToolCallArgumentsResolved::class, new IsGrantedToolAttributeListener($this->authChecker));
    }

    #[Test]
    #[TestWith([new ToolWithIsGrantedOnMethod(), new Tool(new ExecutionReference(ToolWithIsGrantedOnMethod::class, 'simple'), 'simple', '')])]
    #[TestWith([new ToolWithIsGrantedOnMethod(), new Tool(new ExecutionReference(ToolWithIsGrantedOnMethod::class, 'expressionAsSubject'), 'expressionAsSubject', '')])]
    #[TestWith([new ToolWithIsGrantedOnClass(), new Tool(new ExecutionReference(ToolWithIsGrantedOnClass::class, '__invoke'), 'ToolWithIsGrantedOnClass', '')])]
    public function itWillThrowWhenNotGranted(object $tool, Tool $metadata): void
    {
        $this->authChecker->expects(self::once())->method('isGranted')->willReturn(false);

        self::expectException(AccessDeniedException::class);
        self::expectExceptionMessage(\sprintf('No access to %s tool.', $metadata->name));
        $this->dispatcher->dispatch(new ToolCallArgumentsResolved($tool, $metadata, []));
    }

    #[Test]
    #[TestWith([new ToolWithIsGrantedOnMethod(), new Tool(new ExecutionReference(ToolWithIsGrantedOnMethod::class, 'simple'), '', '')], 'method')]
    public function itWillNotThrowWhenGranted(object $tool, Tool $metadata): void
    {
        $this->authChecker->expects(self::once())->method('isGranted')->with('ROLE_USER')->willReturn(true);
        $this->dispatcher->dispatch(new ToolCallArgumentsResolved($tool, $metadata, []));
    }

    #[Test]
    #[TestWith([new ToolWithIsGrantedOnMethod(), new Tool(new ExecutionReference(ToolWithIsGrantedOnMethod::class, 'argumentAsSubject'), '', '')], 'method')]
    #[TestWith([new ToolWithIsGrantedOnClass(), new Tool(new ExecutionReference(ToolWithIsGrantedOnClass::class, '__invoke'), '', '')], 'class')]
    public function itWillProvideArgumentAsSubject(object $tool, Tool $metadata): void
    {
        $this->authChecker->expects(self::once())->method('isGranted')->with('test:permission', 44)->willReturn(true);
        $this->dispatcher->dispatch(new ToolCallArgumentsResolved($tool, $metadata, ['itemId' => 44]));
    }

    #[Test]
    #[TestWith([new ToolWithIsGrantedOnMethod(), new Tool(new ExecutionReference(ToolWithIsGrantedOnMethod::class, 'expressionAsSubject'), '', '')], 'method')]
    public function itWillEvaluateSubjectExpression(object $tool, Tool $metadata): void
    {
        $this->authChecker->expects(self::once())->method('isGranted')->with('test:permission', 44)->willReturn(true);
        $this->dispatcher->dispatch(new ToolCallArgumentsResolved($tool, $metadata, ['itemId' => 44]));
    }
}
