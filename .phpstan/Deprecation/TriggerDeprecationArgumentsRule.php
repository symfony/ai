<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\PHPStan\Deprecation;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Checks that trigger_deprecation() names the package of the calling code and a "major.minor" version.
 *
 * @implements Rule<FuncCall>
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TriggerDeprecationArgumentsRule implements Rule
{
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Node\Name || 'trigger_deprecation' !== strtolower($node->name->toString()) || PackageResolver::isTestFile($scope->getFile())) {
            return [];
        }

        $args = $node->getArgs();
        $package = PackageResolver::packageOf($scope->getFile());
        $errors = [];

        if (!($args[0] ?? null)?->value instanceof String_ || (null !== $package && $args[0]->value->value !== $package)) {
            $errors[] = RuleErrorBuilder::message(\sprintf('The first argument of trigger_deprecation() must be the package name "%s".', $package ?? 'vendor/package'))
                ->identifier('symfonyAi.deprecation.triggerPackage')
                ->build();
        }

        if (!($args[1] ?? null)?->value instanceof String_ || 1 !== preg_match('/^\d+\.\d+$/', $args[1]->value->value)) {
            $errors[] = RuleErrorBuilder::message('The second argument of trigger_deprecation() must be the "major.minor" version that deprecates the code, e.g. "1.1".')
                ->identifier('symfonyAi.deprecation.triggerVersion')
                ->build();
        }

        if (!isset($args[2])) {
            $errors[] = RuleErrorBuilder::message('trigger_deprecation() needs a message that names the replacement, or says that there is none.')
                ->identifier('symfonyAi.deprecation.triggerMessage')
                ->build();
        }

        return $errors;
    }
}
