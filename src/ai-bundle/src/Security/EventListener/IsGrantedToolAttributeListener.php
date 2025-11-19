<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Security\EventListener;

use Symfony\AI\Agent\Toolbox\Event\ToolCallArgumentsResolved;
use Symfony\AI\AiBundle\Security\Attribute\IsGrantedTool;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\RuntimeException;

/**
 * Checks {@see IsGrantedTool} attributes on tools just before they are called.
 *
 * @author Valtteri R <valtzu@gmail.com>
 */
class IsGrantedToolAttributeListener
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authChecker,
        private ?ExpressionLanguage $expressionLanguage = null,
    ) {
    }

    public function __invoke(ToolCallArgumentsResolved $event): void
    {
        $tool = $event->getTool();
        $class = new \ReflectionClass($tool);
        $method = $class->getMethod($event->getMetadata()->getReference()->getMethod());
        $classAttributes = $class->getAttributes(IsGrantedTool::class);
        $methodAttributes = $method->getAttributes(IsGrantedTool::class);

        if (!$classAttributes && !$methodAttributes) {
            return;
        }

        $arguments = $event->getArguments();

        foreach (array_merge($classAttributes, $methodAttributes) as $attr) {
            /** @var IsGrantedTool $attribute */
            $attribute = $attr->newInstance();
            $subject = null;

            if ($subjectRef = $attribute->subject) {
                if (\is_array($subjectRef)) {
                    foreach ($subjectRef as $refKey => $ref) {
                        $subject[\is_string($refKey) ? $refKey : (string) $ref] = $this->getIsGrantedSubject($ref, $tool, $arguments);
                    }
                } else {
                    $subject = $this->getIsGrantedSubject($subjectRef, $tool, $arguments);
                }
            }

            $accessDecision = null;
            // bc layer
            if (class_exists(AccessDecision::class)) {
                $accessDecision = new AccessDecision();
                $accessDecision->isGranted = false;
                $decision = &$accessDecision->isGranted;
            }

            if (!$decision = $this->authChecker->isGranted($attribute->attribute, $subject, $accessDecision)) {
                $message = $attribute->message ?: (class_exists(AccessDecision::class, false) ? $accessDecision->getMessage() : 'Access Denied.');

                $e = new AccessDeniedException($message, code: $attribute->exceptionCode ?? 403);
                $e->setAttributes([$attribute->attribute]);
                $e->setSubject($subject);
                if ($accessDecision && method_exists($e, 'setAccessDecision')) {
                    $e->setAccessDecision($accessDecision);
                }

                throw $e;
            }
        }
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function getIsGrantedSubject(string|Expression|\Closure $subjectRef, object $tool, array $arguments): mixed
    {
        if ($subjectRef instanceof \Closure) {
            return $subjectRef($arguments, $tool);
        }

        if ($subjectRef instanceof Expression) {
            $this->expressionLanguage ??= new ExpressionLanguage();

            return $this->expressionLanguage->evaluate($subjectRef, [
                'tool' => $tool,
                'args' => $arguments,
            ]);
        }

        if (!\array_key_exists($subjectRef, $arguments)) {
            throw new RuntimeException(\sprintf('Could not find the subject "%s" for the #[IsGranted] attribute. Try adding a "$%s" argument to your tool method.', $subjectRef, $subjectRef));
        }

        return $arguments[$subjectRef];
    }
}
