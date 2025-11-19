<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\Security;

use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\Event\ToolCallArgumentsResolved;
use Symfony\AI\AiBundle\Security\EventListener\IsGrantedToolAttributeListener;
use Symfony\AI\AiBundle\Tests\Fixture\Tool\ToolWithIsGrantedOnClass;
use Symfony\AI\AiBundle\Tests\Fixture\Tool\ToolWithIsGrantedOnMethod;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class IsGrantedToolAttributeListenerTest extends TestCase
{
    private EventDispatcherInterface $dispatcher;
    private AuthorizationCheckerInterface&MockObject $authChecker;

    #[TestWith([new ToolWithIsGrantedOnMethod(), new Tool(new ExecutionReference(ToolWithIsGrantedOnMethod::class, 'simple'), 'simple', '')])]
    #[TestWith([new ToolWithIsGrantedOnMethod(), new Tool(new ExecutionReference(ToolWithIsGrantedOnMethod::class, 'expressionAsSubject'), 'expressionAsSubject', '')])]
    #[TestWith([new ToolWithIsGrantedOnClass(), new Tool(new ExecutionReference(ToolWithIsGrantedOnClass::class, '__invoke'), 'ToolWithIsGrantedOnClass', '')])]
    public function testItWillThrowWhenNotGranted(object $tool, Tool $metadata)
    {
        $this->authChecker->expects($this->once())->method('isGranted')->willReturn(false);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage(\sprintf('No access to %s tool.', $metadata->getName()));
        $this->dispatcher->dispatch(new ToolCallArgumentsResolved($tool, $metadata, []));
    }

    #[TestWith([new ToolWithIsGrantedOnMethod(), new Tool(new ExecutionReference(ToolWithIsGrantedOnMethod::class, 'simple'), '', '')], 'method')]
    public function testItWillNotThrowWhenGranted(object $tool, Tool $metadata)
    {
        $this->authChecker->expects($this->once())->method('isGranted')->with('ROLE_USER')->willReturn(true);
        $this->dispatcher->dispatch(new ToolCallArgumentsResolved($tool, $metadata, []));
    }

    #[TestWith([new ToolWithIsGrantedOnMethod(), new Tool(new ExecutionReference(ToolWithIsGrantedOnMethod::class, 'argumentAsSubject'), '', '')], 'method')]
    #[TestWith([new ToolWithIsGrantedOnClass(), new Tool(new ExecutionReference(ToolWithIsGrantedOnClass::class, '__invoke'), '', '')], 'class')]
    public function testItWillProvideArgumentAsSubject(object $tool, Tool $metadata)
    {
        $this->authChecker->expects($this->once())->method('isGranted')->with('test:permission', 44)->willReturn(true);
        $this->dispatcher->dispatch(new ToolCallArgumentsResolved($tool, $metadata, ['itemId' => 44]));
    }

    #[TestWith([new ToolWithIsGrantedOnMethod(), new Tool(new ExecutionReference(ToolWithIsGrantedOnMethod::class, 'expressionAsSubject'), '', '')], 'method')]
    public function testItWillEvaluateSubjectExpression(object $tool, Tool $metadata)
    {
        $this->authChecker->expects($this->once())->method('isGranted')->with('test:permission', 44)->willReturn(true);
        $this->dispatcher->dispatch(new ToolCallArgumentsResolved($tool, $metadata, ['itemId' => 44]));
    }

    #[Before]
    protected function setupTool(): void
    {
        $this->dispatcher = new EventDispatcher();
        $this->authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->dispatcher->addListener(ToolCallArgumentsResolved::class, new IsGrantedToolAttributeListener($this->authChecker));
    }
}
