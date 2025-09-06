<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * PHPStan rule that forbids usage of native PHP exceptions in favor of subpackage-specific exceptions.
 *
 * This rule enforces the use of component-specific exception classes instead of native PHP exceptions
 * for better error handling and consistency across the Symfony AI monorepo.
 *
 * @implements Rule<Node>
 */
final class ForbidNativeExceptionRule implements Rule
{
    private const FORBIDDEN_EXCEPTIONS = [
        \Exception::class,
        \InvalidArgumentException::class,
        \RuntimeException::class,
        \LogicException::class,
        \BadMethodCallException::class,
        \BadFunctionCallException::class,
        \DomainException::class,
        \LengthException::class,
        \OutOfBoundsException::class,
        \OutOfRangeException::class,
        \OverflowException::class,
        \RangeException::class,
        \UnderflowException::class,
        \UnexpectedValueException::class,
    ];

    private const PACKAGE_EXCEPTION_NAMESPACES = [
        'Symfony\\AI\\Agent' => 'Symfony\\AI\\Agent\\Exception\\',
        'Symfony\\AI\\Platform' => 'Symfony\\AI\\Platform\\Exception\\',
        'Symfony\\AI\\Store' => 'Symfony\\AI\\Store\\Exception\\',
        'Symfony\\AI\\AiBundle' => 'Symfony\\AI\\AiBundle\\Exception\\',
        'Symfony\\AI\\McpBundle' => 'Symfony\\AI\\McpBundle\\Exception\\',
    ];

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        if ($node instanceof New_ && $node->class instanceof Name) {
            $exceptionClass = $node->class->toString();
            if ($this->isForbiddenException($exceptionClass)) {
                $errors[] = $this->createError($node, $exceptionClass, $scope, 'instantiation');
            }
        }

        if ($node instanceof Throw_ && $node->expr instanceof New_ && $node->expr->class instanceof Name) {
            $exceptionClass = $node->expr->class->toString();
            if ($this->isForbiddenException($exceptionClass)) {
                $errors[] = $this->createError($node, $exceptionClass, $scope, 'throw');
            }
        }

        return $errors;
    }

    private function isForbiddenException(string $exceptionClass): bool
    {
        // Remove leading backslash if present
        $exceptionClass = ltrim($exceptionClass, '\\');

        // Check if it's a native PHP exception
        return \in_array($exceptionClass, self::FORBIDDEN_EXCEPTIONS, true);
    }

    private function createError(Node $node, string $exceptionClass, Scope $scope, string $context): RuleError
    {
        $currentNamespace = $scope->getNamespace();

        if (null === $currentNamespace) {
            throw new \RuntimeException('All classes should have a namespace.');
        }

        $suggestedNamespace = $this->getSuggestedExceptionNamespace($currentNamespace);

        $message = \sprintf(
            'Use of native PHP exception "%s" is forbidden in %s context. Use "%s%s" instead.',
            $exceptionClass,
            $context,
            $suggestedNamespace,
            $exceptionClass
        );

        return RuleErrorBuilder::message($message)
            ->line($node->getLine())
            ->identifier('symfonyAi.forbidNativeException')
            ->tip(\sprintf(
                'Replace "%s" with "%s%s" to use a package-specific exception.',
                $exceptionClass,
                $suggestedNamespace,
                $exceptionClass
            ))
            ->build();
    }

    private function getSuggestedExceptionNamespace(string $currentNamespace): string
    {
        foreach (self::PACKAGE_EXCEPTION_NAMESPACES as $packageNamespace => $exceptionNamespace) {
            if (str_starts_with($currentNamespace, $packageNamespace)) {
                return $exceptionNamespace;
            }
        }

        throw new \RuntimeException(\sprintf('Unexpected namespace "%s".', $currentNamespace));
    }
}
