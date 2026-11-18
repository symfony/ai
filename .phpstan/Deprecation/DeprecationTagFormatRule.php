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
use PhpParser\Node\Stmt;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Checks the wording of @deprecated, @final and #[\Deprecated], so tools and readers can tell the version.
 *
 * @implements Rule<Stmt>
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class DeprecationTagFormatRule implements Rule
{
    private const SINCE = '/^since Symfony AI \d+\.\d+\b/';

    public function getNodeType(): string
    {
        return Stmt::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Stmt\ClassLike && !$node instanceof Stmt\ClassMethod && !$node instanceof Stmt\Property && !$node instanceof Stmt\ClassConst && !$node instanceof Stmt\EnumCase) {
            return [];
        }
        if (PackageResolver::isTestFile($scope->getFile())) {
            return [];
        }

        $errors = [];
        $doc = $node->getDocComment()?->getText() ?? '';

        if (preg_match_all('/@deprecated\b\s*([^\n*]*)/', $doc, $matches) > 0) {
            foreach ($matches[1] as $description) {
                if (1 !== preg_match(self::SINCE, trim($description))) {
                    $errors[] = $this->error('A @deprecated tag must start with "since Symfony AI <major.minor>", followed by the replacement: "@deprecated since Symfony AI 1.1, use Foo instead".', 'symfonyAi.deprecation.tagFormat', $node);
                }
            }
        }

        if (preg_match_all('/@final\b\s*([^\n*]*)/', $doc, $matches) > 0) {
            foreach ($matches[1] as $description) {
                $description = trim($description);
                if ('' !== $description && 1 !== preg_match(self::SINCE, $description)) {
                    $errors[] = $this->error('A @final tag is either plain, or announces the next major version with "@final since Symfony AI <major.minor>".', 'symfonyAi.deprecation.finalFormat', $node);
                }
            }
        }

        $package = PackageResolver::packageOf($scope->getFile());
        foreach ($node->attrGroups ?? [] as $group) {
            foreach ($group->attrs as $attr) {
                if ('Deprecated' !== $attr->name->toString()) {
                    continue;
                }
                $since = null;
                foreach ($attr->args as $i => $arg) {
                    if ('since' === $arg->name?->toString() || (null === $arg->name && 1 === $i)) {
                        $since = $arg->value instanceof Node\Scalar\String_ ? $arg->value->value : '';
                    }
                }
                if (null === $since || 1 !== preg_match('/^'.preg_quote($package ?? 'symfony/ai-platform', '/').' \d+\.\d+$/', $since)) {
                    $errors[] = $this->error(\sprintf('The #[\Deprecated] attribute needs a "since" argument like "%s 1.1".', $package ?? 'symfony/ai-platform'), 'symfonyAi.deprecation.attributeFormat', $node);
                }
            }
        }

        return $errors;
    }

    private function error(string $message, string $identifier, Node $node): IdentifierRuleError
    {
        return RuleErrorBuilder::message($message)->identifier($identifier)->line($node->getStartLine())->build();
    }
}
