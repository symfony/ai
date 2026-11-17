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
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Checks that @deprecated and trigger_deprecation() always come together.
 *
 * The docblock informs IDEs and static analysis, the call informs test suites and the profiler.
 * A deprecated class or trait triggers at file level, after the use statements, or in the method users
 * reach it through, e.g. a factory listed in a registry. A deprecated interface needs no call, since
 * a file-level one would fire whenever any implementation is loaded.
 *
 * @implements Rule<FileNode>
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class DeprecationTriggerRule implements Rule
{
    public function getNodeType(): string
    {
        return FileNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (PackageResolver::isTestFile($scope->getFile())) {
            return [];
        }

        $errors = [];
        foreach (self::statementLists($node->getNodes()) as $statements) {
            $fileTrigger = self::triggers($statements);

            foreach ($statements as $statement) {
                foreach (self::classLikes($statement) as $class) {
                    $deprecatedClass = self::isDeprecated($class);
                    if ($deprecatedClass && !$class instanceof Stmt\Interface_ && !$fileTrigger && !self::triggers($class->stmts, true)) {
                        $errors[] = RuleErrorBuilder::message(\sprintf('The deprecated %s "%s" must call trigger_deprecation(), at file level right after the use statements, or in the method users reach it through.', self::kind($class), $class->name?->toString()))
                            ->identifier('symfonyAi.deprecation.missingTrigger')
                            ->line($class->getStartLine())
                            ->build();
                    }

                    foreach ($class->getMethods() as $method) {
                        $deprecatedMethod = self::isDeprecated($method);
                        if (null === $method->stmts) {
                            continue;
                        }
                        if ($deprecatedMethod && !self::hasDeprecatedAttribute($method) && !self::triggers($method->stmts, true)) {
                            $errors[] = RuleErrorBuilder::message(\sprintf('The deprecated method "%s()" must call trigger_deprecation(), or use the #[\Deprecated] attribute.', $method->name->toString()))
                                ->identifier('symfonyAi.deprecation.missingTrigger')
                                ->line($method->getStartLine())
                                ->build();
                        }
                        if (!$deprecatedMethod && !$deprecatedClass && self::triggers($method->stmts)) {
                            $errors[] = RuleErrorBuilder::message(\sprintf('The method "%s()" always triggers a deprecation, so it needs a @deprecated tag as well. Move the call into a condition if only some arguments are deprecated.', $method->name->toString()))
                                ->identifier('symfonyAi.deprecation.missingTag')
                                ->line($method->getStartLine())
                                ->build();
                        }
                    }
                }
            }

            if ($fileTrigger && !self::declaresDeprecatedClass($statements)) {
                $errors[] = RuleErrorBuilder::message('A file-level trigger_deprecation() needs a class, interface or trait with a @deprecated tag.')
                    ->identifier('symfonyAi.deprecation.missingTag')
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * @param array<Node> $nodes
     *
     * @return iterable<list<Stmt>>
     */
    private static function statementLists(array $nodes): iterable
    {
        $topLevel = [];
        foreach ($nodes as $node) {
            if ($node instanceof Stmt\Namespace_) {
                yield array_values($node->stmts);
            } elseif ($node instanceof Stmt) {
                $topLevel[] = $node;
            }
        }

        if ([] !== $topLevel) {
            yield $topLevel;
        }
    }

    /**
     * Class-likes declared directly, or inside "if (false) { ... }" stubs and other conditional blocks.
     *
     * @return list<Stmt\ClassLike>
     */
    private static function classLikes(Stmt $statement): array
    {
        if ($statement instanceof Stmt\ClassLike) {
            return null !== $statement->name ? [$statement] : [];
        }
        if ($statement instanceof Stmt\If_) {
            return array_values(array_filter(
                (new NodeFinder())->findInstanceOf($statement->stmts, Stmt\ClassLike::class),
                static fn (Stmt\ClassLike $class): bool => null !== $class->name,
            ));
        }

        return [];
    }

    /**
     * @param list<Stmt> $statements
     */
    private static function declaresDeprecatedClass(array $statements): bool
    {
        foreach ($statements as $statement) {
            foreach (self::classLikes($statement) as $class) {
                if (self::isDeprecated($class)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<Stmt> $statements
     * @param bool        $nested     whether calls inside conditions count
     */
    private static function triggers(array $statements, bool $nested = false): bool
    {
        if ($nested) {
            foreach ((new NodeFinder())->findInstanceOf($statements, FuncCall::class) as $call) {
                if (self::isTrigger($call)) {
                    return true;
                }
            }

            return false;
        }

        foreach ($statements as $statement) {
            if ($statement instanceof Stmt\Expression && $statement->expr instanceof FuncCall && self::isTrigger($statement->expr)) {
                return true;
            }
        }

        return false;
    }

    private static function isTrigger(FuncCall $call): bool
    {
        return $call->name instanceof Node\Name && 'trigger_deprecation' === strtolower($call->name->getLast());
    }

    private static function isDeprecated(Stmt\ClassLike|Stmt\ClassMethod $node): bool
    {
        return 1 === preg_match('/@deprecated\b/', $node->getDocComment()?->getText() ?? '') || self::hasDeprecatedAttribute($node);
    }

    private static function hasDeprecatedAttribute(Stmt\ClassLike|Stmt\ClassMethod $node): bool
    {
        foreach ($node->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if ('Deprecated' === ltrim($attr->name->toString(), '\\')) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function kind(Stmt\ClassLike $class): string
    {
        return match (true) {
            $class instanceof Stmt\Interface_ => 'interface',
            $class instanceof Stmt\Trait_ => 'trait',
            $class instanceof Stmt\Enum_ => 'enum',
            default => 'class',
        };
    }
}
