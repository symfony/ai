<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Bc\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Bc\Api;
use Symfony\AI\Bc\ApiExtractor;
use Symfony\AI\Bc\Comparer;
use Symfony\AI\Bc\Finding;

final class ComparerTest extends TestCase
{
    /**
     * @param list<array{Finding::*, string}> $expected type and message fragment of each finding
     */
    #[DataProvider('provideChanges')]
    public function testCompare(string $base, string $head, array $expected)
    {
        $findings = (new Comparer(self::api($base), self::api($head), '1.1'))->compare();

        $actual = array_map(static fn (Finding $finding): string => $finding->type.': '.$finding->message, $findings);
        $this->assertCount(\count($expected), $findings, implode("\n", $actual));
        foreach ($expected as $i => [$type, $fragment]) {
            $this->assertSame($type, $findings[$i]->type, $actual[$i]);
            $this->assertStringContainsString($fragment, $findings[$i]->message);
        }
    }

    /**
     * @return iterable<string, array{string, string, list<array{Finding::*, string}>}>
     */
    public static function provideChanges(): iterable
    {
        yield 'unchanged' => [
            'class Foo { public function bar(int $a): string {} }',
            'class Foo { public function bar(int $a): string {} }',
            [],
        ];

        yield 'removed method' => [
            'class Foo { public function bar() {} }',
            'class Foo {}',
            [[Finding::BREAK, 'The method is removed.']],
        ];

        yield 'removed deprecated method' => [
            'class Foo { /** @deprecated since Symfony AI 1.0 */ public function bar() {} }',
            'class Foo {}',
            [[Finding::MAJOR_ONLY, 'The deprecated method is removed.']],
        ];

        yield 'removed protected method of a final class' => [
            'final class Foo { protected function bar() {} }',
            'final class Foo {}',
            [],
        ];

        yield 'removed private method of a trait' => [
            'trait Foo { private function bar() {} }',
            'trait Foo {}',
            [],
        ];

        yield 'removed protected method of a trait' => [
            'trait Foo { protected function bar() {} }',
            'trait Foo {}',
            [[Finding::BREAK, 'The method is removed.']],
        ];

        yield 'method moved to the parent class' => [
            'class Base {} class Foo extends Base { public function bar(int $a) {} }',
            'class Base { public function bar(int $a) {} } class Foo extends Base {}',
            [],
        ];

        yield 'removed class' => [
            'class Foo {}',
            '',
            [[Finding::BREAK, 'The class is removed. Deprecate it']],
        ];

        yield 'moved class' => [
            'namespace A; class Foo {}',
            'namespace B; class Foo {}',
            [[Finding::BREAK, 'If it moved to "B\Foo", keep the old name working with class_alias()']],
        ];

        yield 'internal class changes freely' => [
            '/** @internal */ class Foo { public function bar() {} }',
            '/** @internal */ class Foo {}',
            [],
        ];

        yield 'class becomes internal' => [
            'class Foo {}',
            '/** @internal */ class Foo {}',
            [[Finding::BREAK, 'is now @internal']],
        ];

        yield 'class announced internal' => [
            'class Foo {}',
            '/** @internal since Symfony AI 1.1 */ class Foo {}',
            [[Finding::DEPRECATION, 'announced to become @internal']],
        ];

        yield 'announced internal class becomes internal' => [
            '/** @internal since Symfony AI 1.0 */ class Foo {}',
            '/** @internal */ class Foo {}',
            [[Finding::MAJOR_ONLY, 'is now @internal']],
        ];

        yield 'method becomes internal' => [
            'class Foo { public function bar() {} }',
            'class Foo { /** @internal */ public function bar() {} }',
            [[Finding::BREAK, 'Announce it with "@internal since Symfony AI 1.1" first.']],
        ];

        yield 'new deprecated class' => [
            '',
            '/** @deprecated since Symfony AI 1.1 */ class Foo {}',
            [[Finding::VIOLATION, 'A new class cannot be deprecated.']],
        ];

        yield 'deprecated method' => [
            'class Foo { public function bar() {} }',
            'class Foo { /** @deprecated since Symfony AI 1.1, use baz() instead */ public function bar() {} }',
            [[Finding::DEPRECATION, 'The method is deprecated.']],
        ];

        yield 'deprecated method with the native attribute' => [
            'class Foo { public function bar() {} }',
            'class Foo { #[\Deprecated(\'use baz()\', since: \'symfony/ai-platform 1.1\')] public function bar() {} }',
            [[Finding::DEPRECATION, 'The method is deprecated.']],
        ];

        yield 'deprecation since a released version' => [
            'class Foo { public function bar() {} }',
            'class Foo { /** @deprecated since Symfony AI 1.0 */ public function bar() {} }',
            [[Finding::DEPRECATION, 'The method is deprecated.'], [Finding::VIOLATION, '"since Symfony AI 1.1"']],
        ];

        yield 'class becomes final' => [
            'class Foo {}',
            'final class Foo {}',
            [[Finding::BREAK, 'The class is now final.']],
        ];

        yield 'class announced final' => [
            'class Foo {}',
            '/** @final since Symfony AI 1.1 */ class Foo {}',
            [[Finding::DEPRECATION, 'The class is announced to become final.']],
        ];

        yield 'announced final class becomes final' => [
            '/** @final since Symfony AI 1.0 */ class Foo {}',
            'final class Foo {}',
            [[Finding::MAJOR_ONLY, 'now final']],
        ];

        yield 'class tagged @final becomes final' => [
            '/** @final */ class Foo { protected function bar() {} }',
            'final class Foo {}',
            [],
        ];

        yield 'class no longer implements an interface' => [
            'interface Bar {} class Foo implements Bar {}',
            'interface Bar {} class Foo {}',
            [[Finding::BREAK, 'no longer implements "Bar"']],
        ];

        yield 'method added to an interface' => [
            'interface Foo {}',
            'interface Foo { public function bar(); }',
            [[Finding::BREAK, 'Announce it with an @method tag']],
        ];

        yield 'method announced on an interface' => [
            'interface Foo {}',
            '/** @method string bar() */ interface Foo {}',
            [[Finding::NOTICE, 'method_exists()']],
        ];

        yield 'announced interface method gets declared' => [
            '/** @method string bar() */ interface Foo {}',
            'interface Foo { public function bar(): string; }',
            [[Finding::MAJOR_ONLY, 'announced with @method is now declared']],
        ];

        yield 'abstract method added to an abstract class' => [
            'abstract class Foo {}',
            'abstract class Foo { abstract public function bar(); }',
            [[Finding::BREAK, 'breaks every child class']],
        ];

        yield 'concrete method added to a class' => [
            'class Foo {}',
            'class Foo { public function bar() {} }',
            [],
        ];

        yield 'optional argument appended to a final class' => [
            'final class Foo { public function bar(int $a) {} }',
            'final class Foo { public function bar(int $a, int $b = 0) {} }',
            [],
        ];

        yield 'optional argument appended to an overridable method' => [
            'class Foo { public function bar(int $a) {} }',
            'class Foo { public function bar(int $a, int $b = 0) {} }',
            [[Finding::BREAK, 'Add it as a virtual argument first.']],
        ];

        yield 'optional argument appended to a constructor' => [
            'class Foo { public function __construct(int $a) {} }',
            'class Foo { public function __construct(int $a, int $b = 0) {} }',
            [],
        ];

        yield 'required argument appended to a constructor' => [
            'class Foo { public function __construct(int $a) {} }',
            'class Foo { public function __construct(int $a, int $b) {} }',
            [[Finding::BREAK, 'The required argument $b is added.']],
        ];

        yield 'argument inserted in the middle' => [
            'final class Foo { public function __construct(int $a, int $c = 0) {} }',
            'final class Foo { public function __construct(int $a, int $b = 0, int $c = 0) {} }',
            [[Finding::BREAK, 'The arguments moved from ($a, $c) to ($a, $b, $c)']],
        ];

        yield 'virtual argument announced' => [
            'interface Foo { public function bar(int $a); }',
            'interface Foo { /** @param bool $strict */ public function bar(int $a /* , bool $strict = false */); }',
            [[Finding::DEPRECATION, 'The virtual argument $strict is announced.']],
        ];

        yield 'virtual argument gets declared' => [
            'interface Foo { /** @param bool $strict */ public function bar(int $a /* , bool $strict = false */); }',
            'interface Foo { public function bar(int $a, bool $strict = false); }',
            [[Finding::MAJOR_ONLY, 'The virtual argument $strict is now declared.']],
        ];

        yield 'last optional argument removed' => [
            'class Foo { public function bar(int $a, int $b = 0) {} }',
            'class Foo { public function bar(int $a) {} }',
            [],
        ];

        yield 'required argument removed' => [
            'class Foo { public function bar(int $a, int $b) {} }',
            'class Foo { public function bar(int $a) {} }',
            [[Finding::BREAK, 'The argument $b is removed.']],
        ];

        yield 'default value changed' => [
            'final class Foo { public function bar(bool $a = false) {} }',
            'final class Foo { public function bar(bool $a = true) {} }',
            [[Finding::BREAK, 'The default value of $a changed from false to true.']],
        ];

        yield 'default value moved into a constant' => [
            'final class Foo { public function bar(array $a = [\'php\']) {} }',
            'final class Foo { public const DEFAULT = [\'php\']; public function bar(array $a = self::DEFAULT) {} }',
            [],
        ];

        yield 'default value removed' => [
            'final class Foo { public function bar(int $a = 0) {} }',
            'final class Foo { public function bar(int $a) {} }',
            [[Finding::BREAK, 'no longer has a default value']],
        ];

        yield 'argument type widened in a final class' => [
            'final class Foo { public function bar(int $a) {} }',
            'final class Foo { public function bar(int|string $a) {} }',
            [],
        ];

        yield 'argument type widened in an interface' => [
            'interface Foo { public function bar(int $a); }',
            'interface Foo { public function bar(int|string $a); }',
            [[Finding::BREAK, 'widened']],
        ];

        yield 'argument type narrowed' => [
            'final class Foo { public function bar(?string $a) {} }',
            'final class Foo { public function bar(string $a) {} }',
            [[Finding::BREAK, 'narrowed from "null|string" to "string"']],
        ];

        yield 'argument type widened to a parent interface' => [
            'interface Animal {} interface Dog extends Animal {} final class Foo { public function bar(Dog $a) {} }',
            'interface Animal {} interface Dog extends Animal {} final class Foo { public function bar(Animal $a) {} }',
            [],
        ];

        yield 'return type narrowed in a final class' => [
            'final class Foo { public function bar(): ?string {} }',
            'final class Foo { public function bar(): string {} }',
            [],
        ];

        yield 'return type narrowed in an interface' => [
            'interface Foo { public function bar(): ?string; }',
            'interface Foo { public function bar(): string; }',
            [[Finding::BREAK, 'The return type changed']],
        ];

        yield 'return type added to an overridable method' => [
            'class Foo { public function bar() {} }',
            'class Foo { public function bar(): string {} }',
            [[Finding::BREAK, 'Declare it with @return first.']],
        ];

        yield 'void return type removed' => [
            'final class Foo { public function bar(): void {} }',
            'final class Foo { public function bar() {} }',
            [],
        ];

        yield 'method becomes final' => [
            'class Foo { public function bar() {} }',
            'class Foo { final public function bar() {} }',
            [[Finding::BREAK, 'The method is now final.']],
        ];

        yield 'public method becomes protected' => [
            'class Foo { public function bar() {} }',
            'class Foo { protected function bar() {} }',
            [[Finding::BREAK, 'The method is now protected.']],
        ];

        yield 'method becomes static' => [
            'final class Foo { public function bar() {} }',
            'final class Foo { public static function bar() {} }',
            [[Finding::BREAK, 'The method is now static.']],
        ];

        yield 'argument renamed' => [
            'final class Foo { public function bar(int $a) {} }',
            'final class Foo { public function bar(int $b) {} }',
            [[Finding::NOTICE, 'The argument $a is renamed to $b']],
        ];

        yield 'argument of an attribute renamed' => [
            '#[\Attribute] final class Foo { public function __construct(public int $a) {} }',
            '#[\Attribute] final class Foo { public function __construct(public int $b) {} }',
            [[Finding::BREAK, 'The property is removed.'], [Finding::BREAK, 'of an attribute is renamed']],
        ];

        yield 'constant removed' => [
            'class Foo { public const BAR = 1; }',
            'class Foo {}',
            [[Finding::BREAK, 'The constant is removed.']],
        ];

        yield 'property type changed' => [
            'class Foo { public int $bar; }',
            'class Foo { public string $bar; }',
            [[Finding::BREAK, 'from "int" to "string"']],
        ];

        yield 'property becomes readonly' => [
            'final class Foo { public int $bar; }',
            'final class Foo { public readonly int $bar; }',
            [[Finding::BREAK, 'The property is now readonly.']],
        ];

        yield 'enum case removed' => [
            'enum Foo { case A; case B; }',
            'enum Foo { case A; }',
            [[Finding::BREAK, 'The enum case is removed.']],
        ];
    }

    private static function api(string $code): Api
    {
        $classes = [];
        foreach ((new ApiExtractor())->extractCode('<?php '.$code, 'symfony/ai-test', 'Foo.php') as $class) {
            $classes[strtolower($class->name)] = $class;
        }

        return new Api($classes);
    }
}
