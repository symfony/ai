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
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\DeclareDeclare;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * PHPStan rule that forbids usage of declare(strict_types=1) statements.
 *
 * This rule enforces that strict_types declaration should not be used.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 *
 * @implements Rule<Declare_>
 */
final class ForbidDeclareStrictTypesRule implements Rule
{
    public function getNodeType(): string
    {
        return Declare_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Declare_) {
            return [];
        }

        $errors = [];

        foreach ($node->declares as $declare) {
            if ($declare instanceof DeclareDeclare) {
                $key = $declare->key->toString();
                if ('strict_types' === $key) {
                    $errors[] = RuleErrorBuilder::message(
                        'Usage of declare(strict_types=1) is forbidden. Remove the declare statement.'
                    )
                    ->line($node->getLine())
                    ->identifier('symfonyAi.forbidDeclareStrictTypes')
                    ->tip('Remove the declare(strict_types=1) statement from the file.')
                    ->build();
                }
            }
        }

        return $errors;
    }
}
