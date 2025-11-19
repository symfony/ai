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
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * PHPStan rule that forbids usage of test coverage attributes in tests.
 *
 * This rule enforces that Large, Small, Medium, CoversClass and UsesClass attributes
 * should not be used in test files.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 *
 * @implements Rule<Node>
 */
final class ForbidTestCoverageAttributesRule implements Rule
{
    private const FORBIDDEN_ATTRIBUTES = [
        'Large',
        'Small',
        'Medium',
        'CoversClass',
        'UsesClass',
    ];

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // Only check test files
        if (!str_ends_with($scope->getFile(), 'Test.php')) {
            return [];
        }

        $errors = [];

        if ($node instanceof Class_ || $node instanceof ClassMethod) {
            foreach ($node->attrGroups as $attrGroup) {
                $errors = array_merge($errors, $this->checkAttributeGroup($attrGroup));
            }
        }

        return $errors;
    }

    /**
     * @return array<\PHPStan\Rules\RuleError>
     */
    private function checkAttributeGroup(AttributeGroup $attrGroup): array
    {
        $errors = [];

        foreach ($attrGroup->attrs as $attr) {
            $attributeName = $attr->name->toString();

            // Handle both fully qualified and short names
            $shortName = $attributeName;
            if (str_contains($attributeName, '\\')) {
                $shortName = substr($attributeName, strrpos($attributeName, '\\') + 1);
            }

            if (\in_array($shortName, self::FORBIDDEN_ATTRIBUTES, true)) {
                $errors[] = RuleErrorBuilder::message(
                    \sprintf('Usage of #[%s] attribute is forbidden in test files. Remove the attribute.', $shortName)
                )
                ->line($attr->getLine())
                ->identifier('symfonyAi.forbidTestCoverageAttributes')
                ->tip(\sprintf('Remove the #[%s] attribute from the test.', $shortName))
                ->build();
            }
        }

        return $errors;
    }
}
