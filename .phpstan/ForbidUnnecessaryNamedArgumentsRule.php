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
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;

/**
 * PHPStan rule that forbids unnecessary use of named arguments.
 *
 * This rule detects when named arguments are used but provide no benefit:
 * - All required parameters are provided
 * - Parameters are in the correct order
 * - No optional parameters are skipped
 *
 * @implements Rule<Node>
 * @author Claude <noreply@anthropic.com>
 */
final class ForbidUnnecessaryNamedArgumentsRule implements Rule
{
    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($node instanceof FuncCall) {
            return $this->processFunctionCall($node, $scope);
        }

        if ($node instanceof MethodCall || $node instanceof StaticCall) {
            return $this->processMethodCall($node, $scope);
        }

        // Handle constructor calls (new expressions)
        if ($node instanceof Node\Expr\New_) {
            return $this->processConstructorCall($node, $scope);
        }

        return [];
    }

    private function processFunctionCall(FuncCall $node, Scope $scope): array
    {
        if (!$node->name instanceof Node\Name) {
            return [];
        }

        $functionName = $node->name->toString();

        try {
            $functionReflection = $scope->getFunction($node->name, null);
        } catch (\Throwable) {
            return [];
        }

        if ($functionReflection === null) {
            return [];
        }

        return $this->analyzeArguments(
            $node->args,
            $functionReflection,
            $scope,
            sprintf('function "%s()"', $functionName)
        );
    }

    private function processMethodCall(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall && !$node instanceof StaticCall) {
            return [];
        }

        if (!$node->name instanceof Identifier) {
            return [];
        }

        $methodName = $node->name->toString();

        if ($node instanceof MethodCall) {
            $callerType = $scope->getType($node->var);
        } else {
            if (!$node->class instanceof Node\Name) {
                return [];
            }
            $callerType = $scope->resolveTypeByName($node->class);
        }

        $methodReflection = $scope->getMethodReflection($callerType, $methodName);

        if ($methodReflection === null) {
            return [];
        }

        return $this->analyzeArguments(
            $node->args,
            $methodReflection,
            $scope,
            sprintf('method "%s()"', $methodName)
        );
    }

    private function processConstructorCall(Node\Expr\New_ $node, Scope $scope): array
    {
        if (!$node->class instanceof Node\Name) {
            return [];
        }

        $className = $node->class->toString();
        $callerType = $scope->resolveTypeByName($node->class);

        $methodReflection = $scope->getMethodReflection($callerType, '__construct');

        if ($methodReflection === null) {
            return [];
        }

        return $this->analyzeArguments(
            $node->args,
            $methodReflection,
            $scope,
            sprintf('constructor "%s()"', $className)
        );
    }

    /**
     * @param Arg[] $args
     */
    private function analyzeArguments(
        array $args,
        FunctionReflection|MethodReflection $reflection,
        Scope $scope,
        string $callContext
    ): array {
        if (empty($args)) {
            return [];
        }

        // Check if any arguments use named parameters
        $hasNamedArguments = false;
        foreach ($args as $arg) {
            if ($arg->name !== null) {
                $hasNamedArguments = true;
                break;
            }
        }

        if (!$hasNamedArguments) {
            return [];
        }

        $variants = $reflection->getVariants();
        if (empty($variants)) {
            return [];
        }

        $parametersAcceptor = $variants[0];
        $parameters = $parametersAcceptor->getParameters();

        // Check if named arguments are unnecessary
        if ($this->areNamedArgumentsUnnecessary($args, $parameters)) {
            return [
                RuleErrorBuilder::message(sprintf(
                    'Named arguments are unnecessary for %s. All parameters are provided in correct order.',
                    $callContext
                ))
                    ->line($args[0]->getLine())
                    ->identifier('symfonyAi.unnecessaryNamedArguments')
                    ->tip('Remove parameter names to simplify the function call.')
                    ->build(),
            ];
        }

        return [];
    }

    /**
     * @param Arg[] $args
     * @param \PHPStan\Reflection\ParameterReflection[] $parameters
     */
    private function areNamedArgumentsUnnecessary(array $args, array $parameters): bool
    {
        // If we have more arguments than parameters (variadic), named args might be useful
        if (count($args) > count($parameters) && !empty($parameters)) {
            $lastParam = end($parameters);
            if (!$lastParam->isVariadic()) {
                return false;
            }
        }

        // Handle mixed positional and named arguments
        $hasPositionalArgs = false;
        $hasNamedArgs = false;

        foreach ($args as $arg) {
            if ($arg->name === null) {
                $hasPositionalArgs = true;
            } else {
                $hasNamedArgs = true;
            }
        }

        if ($hasPositionalArgs && $hasNamedArgs) {
            // Mixed case - check if the named args are unnecessary
            return $this->areMixedArgumentsUnnecessary($args, $parameters);
        }

        if (!$hasNamedArgs) {
            // All positional - nothing to check
            return false;
        }

        // All arguments are named - check if they're in sequential order from the beginning
        $providedParameterIndices = [];

        foreach ($args as $arg) {
            $argName = $arg->name->toString();

            // Find the parameter index for this name
            $parameterIndex = null;
            foreach ($parameters as $index => $parameter) {
                if ($parameter->getName() === $argName) {
                    $parameterIndex = $index;
                    break;
                }
            }

            if ($parameterIndex === null) {
                // Parameter name not found
                return false;
            }

            $providedParameterIndices[] = $parameterIndex;
        }

        // Check if arguments are provided in the same order as they appear in the function signature
        // AND they start from parameter index 0 (no gaps at the beginning)
        $expectedOrder = range(0, count($providedParameterIndices) - 1);

        if ($providedParameterIndices !== $expectedOrder) {
            // Either not in order, or not starting from parameter 0, or has gaps
            return false;
        }

        // Count how many parameters we would call without named arguments
        // If we have optional parameters at the end that we're not providing, named args add value
        $lastProvidedIndex = max($providedParameterIndices);
        $totalRequiredParams = 0;

        for ($i = 0; $i < count($parameters); $i++) {
            if (!$parameters[$i]->isOptional()) {
                $totalRequiredParams++;
            } else {
                break; // Stop at first optional parameter
            }
        }

        // If we're providing exactly the required parameters, named args add readability
        if (count($providedParameterIndices) === $totalRequiredParams && $lastProvidedIndex < count($parameters) - 1) {
            return false; // Don't flag this as unnecessary
        }

        // All arguments are in correct sequential order from the beginning
        return true;
    }

    /**
     * @param Arg[] $args
     * @param \PHPStan\Reflection\ParameterReflection[] $parameters
     */
    private function areMixedArgumentsUnnecessary(array $args, array $parameters): bool
    {
        // For mixed args, check if we can simply convert all named args to positional
        // by verifying they're in the correct sequential positions after positional args

        $positionalCount = 0;
        $namedArguments = [];

        // First pass: count positional args and collect named ones
        foreach ($args as $index => $arg) {
            if ($arg->name === null) {
                $positionalCount++;
            } else {
                $namedArguments[] = ['arg' => $arg, 'index' => $index];
            }
        }

        // Check if named arguments follow directly after positional ones in correct order
        $expectedParameterIndex = $positionalCount;

        foreach ($namedArguments as $namedArg) {
            $argName = $namedArg['arg']->name->toString();

            // Find the parameter index for this named argument
            $parameterIndex = null;
            foreach ($parameters as $index => $parameter) {
                if ($parameter->getName() === $argName) {
                    $parameterIndex = $index;
                    break;
                }
            }

            if ($parameterIndex === null) {
                return false; // Parameter not found
            }

            // Check if this named argument is in the expected sequential position
            if ($parameterIndex !== $expectedParameterIndex) {
                return false; // Named argument is not in sequential order
            }

            $expectedParameterIndex++;
        }

        // Check if we're providing all parameters continuously from the beginning
        // without skipping any required ones in the middle
        $allProvidedIndices = range(0, $expectedParameterIndex - 1);
        for ($i = 0; $i < $expectedParameterIndex; $i++) {
            if ($i < count($parameters) && !$parameters[$i]->isOptional()) {
                // This is a required parameter that should be provided
                if ($i >= count($args)) {
                    return false; // Required parameter missing
                }
            }
        }

        return true;
    }
}
