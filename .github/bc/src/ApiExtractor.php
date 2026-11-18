<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Bc;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

/**
 * Builds the API model from source code, without autoloading it.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ApiExtractor
{
    private readonly Parser $parser;
    private readonly Standard $printer;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->printer = new Standard();
    }

    /**
     * @param list<Package> $packages
     */
    public function extract(string $root, array $packages): Api
    {
        $classes = [];
        foreach ($packages as $package) {
            foreach ($package->sourceFiles as $file) {
                foreach ($this->extractCode((string) file_get_contents($root.'/'.$file), $package->name, $file) as $class) {
                    $classes[strtolower($class->name)] ??= $class;
                }
            }
        }

        return new Api($classes);
    }

    /**
     * @return list<ClassApi>
     */
    public function extractCode(string $code, string $package, string $file): array
    {
        $statements = $this->parser->parse($code) ?? [];

        $traverser = new NodeTraverser(new NameResolver());
        $statements = $traverser->traverse($statements);

        $classes = [];
        foreach ((new NodeFinder())->findInstanceOf($statements, Stmt\ClassLike::class) as $node) {
            if (null === $node->namespacedName) {
                continue;
            }
            $classes[] = $this->extractClass($node, $package, $file);
        }

        return $classes;
    }

    private function extractClass(Stmt\ClassLike $node, string $package, string $file): ClassApi
    {
        $doc = DocBlock::parse($node->getDocComment()?->getText());
        $name = $node->namespacedName->toString();

        $kind = match (true) {
            $node instanceof Stmt\Interface_ => 'interface',
            $node instanceof Stmt\Trait_ => 'trait',
            $node instanceof Stmt\Enum_ => 'enum',
            default => 'class',
        };

        $parent = null;
        $interfaces = [];
        if ($node instanceof Stmt\Class_) {
            $parent = $node->extends?->toString();
            $interfaces = array_map(static fn (Node\Name $name): string => $name->toString(), $node->implements);
        } elseif ($node instanceof Stmt\Interface_) {
            $interfaces = array_map(static fn (Node\Name $name): string => $name->toString(), $node->extends);
        } elseif ($node instanceof Stmt\Enum_) {
            $interfaces = array_map(static fn (Node\Name $name): string => $name->toString(), $node->implements);
        }

        $traits = [];
        foreach ($node->getTraitUses() as $traitUse) {
            foreach ($traitUse->traits as $trait) {
                $traits[] = $trait->toString();
            }
        }

        $attribute = false;
        foreach ($node->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if ('Attribute' === $attr->name->toString()) {
                    $attribute = true;
                }
            }
        }

        $constants = [];
        foreach ($node->getConstants() as $constGroup) {
            $constDoc = DocBlock::parse($constGroup->getDocComment()?->getText());
            foreach ($constGroup->consts as $const) {
                $constants[$const->name->toString()] = new ConstantApi(
                    $const->name->toString(),
                    self::visibility($constGroup->flags),
                    $constGroup->isFinal(),
                    $constDoc->internal,
                    $constDoc->deprecated ?? $this->deprecatedAttribute($constGroup->attrGroups),
                    $constGroup->getStartLine(),
                    $this->expression($const->value),
                );
            }
        }

        $cases = [];
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Stmt\EnumCase) {
                $cases[] = $stmt->name->toString();
            }
        }

        $properties = [];
        foreach ($node->getProperties() as $propertyGroup) {
            $propertyDoc = DocBlock::parse($propertyGroup->getDocComment()?->getText());
            foreach ($propertyGroup->props as $property) {
                $properties[$property->name->toString()] = new PropertyApi(
                    $property->name->toString(),
                    self::visibility($propertyGroup->flags),
                    $propertyGroup->isStatic(),
                    $propertyGroup->isReadonly() || ($node instanceof Stmt\Class_ && $node->isReadonly()),
                    $this->type($propertyGroup->type),
                    $propertyDoc->internal,
                    $propertyDoc->deprecated,
                    $propertyGroup->getStartLine(),
                );
            }
        }

        $methods = [];
        foreach ($node->getMethods() as $method) {
            $methodApi = $this->extractMethod($method, $name);
            $methods[strtolower($methodApi->name)] = $methodApi;

            if ($methodApi->isConstructor()) {
                foreach ($method->params as $param) {
                    if (0 === $param->flags || !$param->var instanceof Node\Expr\Variable || !\is_string($param->var->name)) {
                        continue;
                    }
                    $paramDoc = DocBlock::parse($param->getDocComment()?->getText());
                    $properties[$param->var->name] = new PropertyApi(
                        $param->var->name,
                        self::visibility($param->flags),
                        false,
                        0 !== ($param->flags & \PhpParser\Modifiers::READONLY) || ($node instanceof Stmt\Class_ && $node->isReadonly()),
                        $this->type($param->type),
                        $paramDoc->internal,
                        $paramDoc->deprecated,
                        $param->getStartLine(),
                    );
                }
            }
        }

        sort($cases);
        ksort($constants);
        ksort($properties);
        ksort($methods);
        $methodTags = $doc->methods;
        ksort($methodTags);

        return new ClassApi(
            $name,
            $kind,
            $package,
            $file,
            $node->getStartLine(),
            $node instanceof Stmt\Class_ && $node->isFinal(),
            $node instanceof Stmt\Class_ && $node->isAbstract(),
            $node instanceof Stmt\Class_ && $node->isReadonly(),
            $doc->final,
            $doc->internal,
            $doc->experimental,
            $doc->deprecated,
            $attribute,
            $parent,
            $interfaces,
            $traits,
            $constants,
            $properties,
            $methods,
            $methodTags,
            $cases,
            $doc->announcedInternal,
        );
    }

    private function extractMethod(Stmt\ClassMethod $method, string $class): MethodApi
    {
        $doc = DocBlock::parse($method->getDocComment()?->getText());

        $parameters = [];
        foreach ($method->params as $param) {
            if (!$param->var instanceof Node\Expr\Variable || !\is_string($param->var->name)) {
                continue;
            }
            $parameters[] = new ParameterApi(
                $param->var->name,
                $this->type($param->type),
                null !== $param->default ? $this->expression($param->default) : null,
                $param->variadic,
                $param->byRef,
            );
        }

        $names = array_map(static fn (ParameterApi $parameter): string => $parameter->name, $parameters);
        $virtualParameters = array_diff_key($doc->parameters, array_flip($names));

        return new MethodApi(
            $method->name->toString(),
            $class,
            self::visibility($method->flags),
            $method->isStatic(),
            $method->isAbstract(),
            $method->isFinal(),
            $doc->final,
            $doc->internal,
            $doc->deprecated ?? $this->deprecatedAttribute($method->attrGroups),
            $parameters,
            $virtualParameters,
            $this->type($method->returnType),
            $method->getStartLine(),
            $doc->announcedInternal,
        );
    }

    private function expression(Node\Expr $expression): string
    {
        return preg_replace('/(?<![\w\\\\])\\\\(true|false|null)\b/i', '$1', $this->printer->prettyPrintExpr($expression)) ?? '';
    }

    /**
     * @param list<Node\AttributeGroup> $attrGroups
     */
    private function deprecatedAttribute(array $attrGroups): ?string
    {
        foreach ($attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if ('Deprecated' !== $attr->name->toString()) {
                    continue;
                }

                $parts = [];
                foreach ($attr->args as $i => $arg) {
                    $key = $arg->name?->toString() ?? (0 === $i ? 'message' : 'since');
                    if ($arg->value instanceof Node\Scalar\String_) {
                        $parts[$key] = $arg->value->value;
                    }
                }

                return trim(isset($parts['since']) ? 'since '.$parts['since'].(isset($parts['message']) ? ', '.$parts['message'] : '') : ($parts['message'] ?? ''));
            }
        }

        return null;
    }

    private function type(Node\Identifier|Node\Name|Node\ComplexType|null $type, bool $nested = false): ?string
    {
        if (null === $type) {
            return null;
        }

        if ($type instanceof Node\NullableType) {
            return $this->type(new Node\UnionType([$type->type, new Node\Identifier('null')]), $nested);
        }

        if ($type instanceof Node\UnionType) {
            $members = array_map(fn (Node $member): string => (string) $this->type($member, true), $type->types);
            $members = array_values(array_unique($members));
            sort($members);

            return implode('|', $members);
        }

        if ($type instanceof Node\IntersectionType) {
            $members = array_map(fn (Node $member): string => (string) $this->type($member), $type->types);
            sort($members);
            $intersection = implode('&', $members);

            return $nested ? '('.$intersection.')' : $intersection;
        }

        if ($type instanceof Node\Name) {
            return $type->toString();
        }

        return strtolower($type->toString());
    }

    /**
     * @return 'public'|'protected'|'private'
     */
    private static function visibility(int $flags): string
    {
        return match (true) {
            0 !== ($flags & \PhpParser\Modifiers::PRIVATE) => 'private',
            0 !== ($flags & \PhpParser\Modifiers::PROTECTED) => 'protected',
            default => 'public',
        };
    }
}
