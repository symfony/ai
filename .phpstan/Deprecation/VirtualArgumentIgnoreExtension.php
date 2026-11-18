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
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Error;
use PHPStan\Analyser\IgnoreErrorExtension;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;

/**
 * Accepts a @param without a declared argument when it is a virtual argument read with func_get_arg().
 *
 * Abstract and interface methods only document it, and implementations read it.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class VirtualArgumentIgnoreExtension implements IgnoreErrorExtension
{
    public function shouldIgnore(Error $error, Node $node, Scope $scope): bool
    {
        if ('parameter.notFound' !== $error->getIdentifier()) {
            return false;
        }

        $method = $node instanceof InClassMethodNode ? $node->getOriginalNode() : $node;
        if (!$method instanceof ClassMethod) {
            return false;
        }

        if (null === $method->stmts) {
            return true;
        }

        foreach ((new NodeFinder())->findInstanceOf($method->stmts, FuncCall::class) as $call) {
            if ($call->name instanceof Node\Name && \in_array(strtolower($call->name->getLast()), ['func_get_arg', 'func_get_args', 'func_num_args'], true)) {
                return true;
            }
        }

        return false;
    }
}
