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
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Forbids named arguments in tests when calling the Symfony AI API, as argument names are not covered by BC.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @implements Rule<CallLike>
 */
final class ForbidNamedArgumentsInTestsRule implements Rule
{
    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
    }

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!str_contains($scope->getFile(), '/Tests/') && !str_contains($scope->getFile(), '/tests/')) {
            return [];
        }

        if ($node->isFirstClassCallable()) {
            return [];
        }

        $namedArguments = array_filter($node->getArgs(), static fn (Node\Arg $arg): bool => null !== $arg->name);
        if ([] === $namedArguments) {
            return [];
        }

        $className = $this->resolveClassName($node, $scope);
        if (null === $className || !$this->isCoveredByBackwardCompatibility($className)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf('Named arguments are forbidden in tests when calling "%s", use positional arguments instead.', $className))
                ->line($node->getStartLine())
                ->identifier('symfonyAi.forbidNamedArgumentsInTests')
                ->tip('Argument names are not covered by the Symfony BC promise.')
                ->build(),
        ];
    }

    private function resolveClassName(CallLike $node, Scope $scope): ?string
    {
        if ($node instanceof New_) {
            if (!$node->class instanceof Name) {
                return null;
            }

            $className = $scope->resolveName($node->class);

            // argument names of attributes are covered by BC
            if ($this->reflectionProvider->hasClass($className) && $this->reflectionProvider->getClass($className)->isAttributeClass()) {
                return null;
            }

            return $className;
        }

        if ($node instanceof StaticCall) {
            if (!$node->class instanceof Name || !$node->name instanceof Identifier) {
                return null;
            }

            $type = $scope->resolveTypeByName($node->class);
            $methodName = $node->name->toString();
        } elseif ($node instanceof MethodCall || $node instanceof NullsafeMethodCall) {
            if (!$node->name instanceof Identifier) {
                return null;
            }

            $type = $scope->getType($node->var);
            $methodName = $node->name->toString();
        } else {
            return null;
        }

        if (!$type->hasMethod($methodName)->yes()) {
            return null;
        }

        return $type->getMethod($methodName, $scope)->getDeclaringClass()->getName();
    }

    private function isCoveredByBackwardCompatibility(string $className): bool
    {
        if (!str_starts_with($className, 'Symfony\\AI\\')) {
            return false;
        }

        return !str_contains($className, '\\Tests\\') && !str_starts_with($className, 'Symfony\\AI\\PHPStan\\');
    }
}
