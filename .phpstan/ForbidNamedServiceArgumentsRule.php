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
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Forbids configuring service arguments by name, as argument names are not covered by BC.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @implements Rule<Node>
 */
final class ForbidNamedServiceArgumentsRule implements Rule
{
    private const METHODS = ['setArgument', 'replaceArgument', 'arg'];
    private const JSON_SCHEMA_KEYWORDS = ['$ref', '$defs', '$schema', '$id', '$anchor', '$comment', '$dynamicRef', '$dynamicAnchor', '$vocabulary'];

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $argumentName = $this->findArgumentName($node, $scope);
        if (null === $argumentName) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf('Service argument "%s" is configured by name, use its index instead.', $argumentName))
                ->line($node->getStartLine())
                ->identifier('symfonyAi.forbidNamedServiceArguments')
                ->tip('Argument names are not covered by the Symfony BC promise.')
                ->build(),
        ];
    }

    private function findArgumentName(Node $node, Scope $scope): ?string
    {
        // bundles wire classes of other packages, whose argument names may change
        if (!str_contains($scope->getFile(), '-bundle/src/')) {
            return null;
        }

        if ($node instanceof MethodCall) {
            if (!$node->name instanceof Identifier || !\in_array($node->name->toString(), self::METHODS, true)) {
                return null;
            }

            return $this->extractArgumentName($node->getArgs()[0]->value ?? null);
        }

        if ($node instanceof ArrayItem) {
            return $this->extractArgumentName($node->key);
        }

        if ($node instanceof ArrayDimFetch) {
            return $this->extractArgumentName($node->dim);
        }

        return null;
    }

    private function extractArgumentName(?Node $node): ?string
    {
        if ($node instanceof String_ && 1 === preg_match('/^\$[a-zA-Z_]\w*$/', $node->value) && !\in_array($node->value, self::JSON_SCHEMA_KEYWORDS, true)) {
            return $node->value;
        }

        if ($node instanceof Concat && $node->left instanceof String_ && '$' === $node->left->value) {
            return '$…';
        }

        return null;
    }
}
