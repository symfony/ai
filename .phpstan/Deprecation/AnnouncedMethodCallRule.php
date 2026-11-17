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
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Checks that methods announced on an interface with @method are only called after method_exists().
 *
 * Until the next major version declares them, implementations outside the monorepo may lack them.
 *
 * @implements Rule<MethodCall>
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AnnouncedMethodCallRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Node\Identifier) {
            return [];
        }

        $name = $node->name->toString();
        foreach ($scope->getType($node->var)->getObjectClassReflections() as $class) {
            if (!$class->isInterface() || !str_starts_with($class->getName(), 'Symfony\\AI\\') || !$class->hasMethod($name) || $class->getNativeReflection()->hasMethod($name)) {
                continue;
            }

            $guard = new FuncCall(new FullyQualified('method_exists'), [new Arg($node->var), new Arg(new String_($name))]);
            if ($scope->getType($guard)->isTrue()->yes()) {
                continue;
            }

            return [
                RuleErrorBuilder::message(\sprintf('The method %s::%s() is only announced with @method, so implementations may lack it. Call it after checking method_exists().', $class->getName(), $name))
                    ->identifier('symfonyAi.deprecation.announcedMethodCall')
                    ->tip('See https://symfony.com/doc/current/contributing/code/bc.html#changing-interfaces')
                    ->build(),
            ];
        }

        return [];
    }
}
