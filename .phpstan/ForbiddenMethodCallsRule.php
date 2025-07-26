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
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * PHPStan rule that forbids usage of specific debugging and output functions.
 *
 * This rule enforces code quality by preventing debugging functions from being
 * committed to the codebase, helping maintain clean production code.
 *
 * @implements Rule<FuncCall>
 * @author Claude <noreply@anthropic.com>
 */
final class ForbiddenMethodCallsRule implements Rule
{
    private const FORBIDDEN_FUNCTIONS = [
        'dump',
        'var_dump',
        'print_r',
        'dd',
        'var_export',
        'debug_print_backtrace',
        'debug_backtrace',
        'print',
        'printf',
        'echo',
        'exit',
        'die',
    ];

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall) {
            return [];
        }

        if (!$node->name instanceof Name) {
            return [];
        }

        $functionName = $node->name->toString();

        if (!$this->isForbiddenFunction($functionName)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Function "%s()" is forbidden. Remove debugging/output functions from production code.',
                $functionName
            ))
                ->line($node->getLine())
                ->identifier('symfonyAi.forbiddenMethodCall')
                ->tip(sprintf(
                    'Remove the "%s()" call or use proper logging mechanisms instead.',
                    $functionName
                ))
                ->build(),
        ];
    }

    private function isForbiddenFunction(string $functionName): bool
    {
        return in_array(strtolower($functionName), self::FORBIDDEN_FUNCTIONS, true);
    }
}
