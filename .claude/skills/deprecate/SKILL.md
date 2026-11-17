---
name: deprecate
description: How to change public API of Symfony AI without breaking users - deprecate the old way in a minor version, remove it in the next major one. Use whenever a change removes, renames, moves or retypes a public class, interface, method, argument, constant, property, config option or service, adds a method to an interface or an argument to a non-final method, changes a default value, or makes something final or internal - also when the user just says "deprecate X" or "replace X with Y". Covers the upgrade path patterns from symfony/symfony, the required trigger, tag, CHANGELOG and UPGRADE conventions, and the checks that enforce them.
---

# Deprecate instead of break

Symfony AI follows the [Symfony Backward Compatibility Promise](https://symfony.com/doc/current/contributing/code/bc.html)
from `1.0` on: a minor version never breaks code that **calls, implements or extends** the public API. Every change to
that API goes in two steps:

1. **Minor version:** add the new way, deprecate the old way, keep the old way working unchanged.
2. **Next major version:** remove the old way and its shim.

Before `1.0`, a break is allowed with an `UPGRADE.md` entry and the `BC Break` label - still prefer a deprecation when
the old API is widely used and the shim is cheap.

## First: does it need an upgrade path?

No upgrade path is needed for code outside the promise:

- `final` classes and methods can't be extended or overridden, so they may gain optional arguments or narrow return
  types in a minor version. Only callers count.
- `@internal` code and anything in `Tests/` can change freely.
- New code can't be introduced already deprecated.

Otherwise find the change below, apply the pattern, then do the [bookkeeping](#bookkeeping).

## Conventions

- **Version:** the upcoming minor, e.g. `1.1`. `php .github/bc/bc.php compare upstream/main` prints it as "upcoming
  version". It is the top section of `UPGRADE.md`, or the latest tag plus one minor.
- **Tag:** `@deprecated since Symfony AI 1.1, use Foo instead` or `@deprecated since Symfony AI 1.1` with no replacement.
  Same format for `@final since Symfony AI 1.1` and `@internal since Symfony AI 1.1`.
- **Trigger:** `trigger_deprecation('symfony/ai-platform', '1.1', 'The "%s" class is deprecated, use "%s" instead.', Old::class, New::class);`
  - The first argument is the Composer name of the package that holds the code.
  - The message names the replacement, by FQCN if it lives in another namespace, or says "without replacement".
  - Use the fixed phrasings: `The "%s()" method is deprecated, use "%s" instead.`, `Passing ... to "%s()" is deprecated, ...`,
    `Not passing ... is deprecated, ...`, `Implementing "%s" is deprecated, ...`.
- **Dependency:** add `"symfony/deprecation-contracts": "^3.5"` to `require` in the package's `composer.json` when it
  calls `trigger_deprecation()` for the first time.
- **Tag plus trigger, not `#[\Deprecated]`:** the packages support PHP 8.2, where the native attribute does nothing.
  If you use it anyway (methods, functions and constants only), pass `since: 'symfony/ai-platform 1.1'`.
- **Keep the old behavior exactly.** The deprecated path must keep returning what it returned before.

## Patterns

### Remove a class, method, constant or property

```php
/**
 * @deprecated since Symfony AI 1.1, use "getModel()" instead
 */
public function getModelName(): string
{
    trigger_deprecation('symfony/ai-platform', '1.1', 'The "%s()" method is deprecated, use "%s::getModel()" instead.', __METHOD__, self::class);

    return $this->model->getName();
}
```

For a whole class, interface or trait, put the trigger at file level right after the `use` statements, so it fires when
the file is autoloaded. An interface needs no trigger, only the tag - trigger in the code that consumes implementations.

**Major:** delete it.

### Move or rename a class

Move the code to the new class. The old name becomes an alias, so `instanceof` and type declarations accept both:

```php
// src/platform/src/Bridge/Foo/OldName.php
namespace Symfony\AI\Platform\Bridge\Foo;

use Symfony\AI\Platform\Bridge\Bar\NewName;

trigger_deprecation('symfony/ai-platform', '1.1', 'The "%s" class is deprecated, use "%s" instead.', OldName::class, NewName::class);

class_alias(NewName::class, OldName::class);

if (false) {
    /** @deprecated since Symfony AI 1.1, use {@see NewName} instead */
    final class OldName extends NewName {}
}
```

The `if (false)` stub only exists for IDEs and static analysis. Don't use a real subclass shim: it would be a different
type and break `instanceof OldName` checks on new instances.

**Major:** delete the file.

### Add a method to an interface

Users implement the Platform, Agent and Store contracts, so a new interface method breaks them. Announce it with
`@method`, and guard every call that only knows the interface:

```php
/**
 * @method string getProvider() Returns the name of the provider - not implementing it is deprecated since Symfony AI 1.1
 */
interface ModelClientInterface
{
}

if (method_exists($client, 'getProvider')) {
    $provider = $client->getProvider();
}
```

Implement the method in every class of the repository right away. PHPStan reports calls without the `method_exists()`
guard (`symfonyAi.deprecation.announcedMethodCall`).

**Major:** declare the method, drop the guards.

### Replace an interface method

Announce the new method with `@method`, deprecate the old one, and let the consumer use whichever exists:

```php
/**
 * @method ResultInterface convert(RawResultInterface $result, array $options = [])
 */
interface ResultConverterInterface
{
    /**
     * @deprecated since Symfony AI 1.1, use "convert()" instead
     */
    public function toResult(RawResultInterface $result): ResultInterface;
}

// consumer
if (!method_exists($converter, 'convert')) {
    trigger_deprecation('symfony/ai-platform', '1.1', 'Implementing "%s::toResult()" is deprecated, implement "convert()" instead.', get_debug_type($converter));

    return $converter->toResult($result);
}

return $converter->convert($result, $options);
```

**Major:** declare the new method, remove the old one and the fallback.

### Drop a method from an interface

The method stays in the interface with `@deprecated`. Implementations opt out by marking their method with
`#[\Deprecated]`, and the consumer stops calling those and warns about the others:

```php
$method = new \ReflectionMethod($store, 'setup');
if (!$method->getAttributes(\Deprecated::class)) {
    trigger_deprecation('symfony/ai-store', '1.1', 'Implementing "%s::setup()" is deprecated, add the #[\Deprecated] attribute once it is no longer needed.', get_debug_type($store));
    $store->setup();
}
```

**Major:** remove the method from the interface.

### Add an argument to a non-final or interface method

Overriding classes would fail with an incompatible signature. Add it as a comment plus `@param`, and read it with
`func_get_arg()` - callers can pass it immediately:

```php
/**
 * @param array<string, mixed> $options
 */
public function request(Model $model, array|string $payload /* , array $options = [] */): RawResultInterface
{
    $options = 2 < \func_num_args() ? func_get_arg(2) : [];
}
```

On an interface, callers pass the new argument and old implementations ignore it. If the argument becomes required in
the next major version, also trigger a deprecation when `\func_num_args()` is too low.

**Major:** turn the comment into a real argument.

### Change a default value

Make the default `null` to tell "not passed" from "passed", keep the old default, and warn only those who rely on it:

```php
public function __construct(?bool $stream = null)
{
    if (null === $stream) {
        trigger_deprecation('symfony/ai-agent', '1.1', 'Not passing a value for "$stream" to "%s()" is deprecated, its default will change to "true".', __METHOD__);
    }
    $this->stream = $stream ?? false;
}
```

**Major:** `bool $stream = true`, remove the check.

### Narrow a parameter type

Keep the wide type, warn on the input that goes away, and normalize it:

```php
public function setTimeout(int|float|null $timeout): void
{
    if (null === $timeout) {
        trigger_deprecation('symfony/ai-platform', '1.1', 'Passing null to "%s()" is deprecated, pass 0 to disable the timeout.', __METHOD__);
        $timeout = 0;
    }
}
```

Widening a parameter type is always allowed and needs no shim.

**Major:** narrow the signature.

### Switch an options array to named arguments

Make the first parameter a union with `array`, detect the old form and merge it:

```php
public function __construct(array|string $model, ?float $temperature = null)
{
    if (\is_array($model)) {
        trigger_deprecation('symfony/ai-platform', '1.1', 'Passing an array of options to "%s()" is deprecated, use named arguments instead.', __METHOD__);
        $temperature ??= $model['temperature'] ?? null;
        $model = $model['model'];
    }
}
```

**Major:** drop `array` from the union and the merge code.

### Add a native return type to a non-final method

Overrides without the type would break. Document it with `@return` first and add the native type in the next major:

```php
/**
 * @return list<Document>
 */
public function load()
```

### Make a class or method final

```php
/**
 * @final since Symfony AI 1.1
 */
class Toolbox
```

The `bc.php compare` check accepts the native `final` keyword only in a major version after this announcement. Never
add a plain `@final` without "since" in a minor version.

### Make code internal

```php
/**
 * @internal since Symfony AI 1.1
 */
```

**Major:** turn it into a plain `@internal`.

### Stop users from overriding a method

Check which class declares it, honor the override, and warn:

```php
if (self::class !== (new \ReflectionMethod($this, 'getName'))->class) {
    trigger_deprecation('symfony/ai-agent', '1.1', 'Overriding "%s::getName()" is deprecated, use the "#[AsTool]" attribute instead.', static::class);
}
```

### Replace a hook method that subclasses implement

The base class implements the new hook and calls the old one when a subclass still has it:

```php
public function normalize(mixed $data): array
{
    if (method_exists($this, 'toArray')) {
        trigger_deprecation('symfony/ai-platform', '1.1', 'Implementing "%s::toArray()" is deprecated, implement "normalize()" instead.', get_debug_type($this));

        return $this->toArray($data);
    }
}
```

### Deprecate an AI Bundle config option, service or parameter

Use the built-in APIs - they fire only when the option, service or parameter is used:

```php
->scalarNode('api_version')
    ->setDeprecated('symfony/ai-bundle', '1.1', 'The "%path%.%node%" option is deprecated, use "version" instead.')
->end()

$container->services()->alias('ai.old_id', 'ai.new_id')
    ->deprecate('symfony/ai-bundle', '1.1', 'The "%alias_id%" service alias is deprecated, use "ai.new_id" instead.');

$container->deprecateParameter('ai.old_parameter', 'symfony/ai-bundle', '1.1');
```

## Bookkeeping

In the same change:

1. **CHANGELOG.md** of the package, under the upcoming version: `* Deprecate \`Foo::bar()\`, use \`Foo::baz()\` instead`
   (or `* Mark \`Foo\` as \`@internal\``). CI fails without it.
2. **No UPGRADE.md entry** for the deprecation itself - `UPGRADE.md` is only for BC breaks with the `BC Break` label.
   The entry comes with the removal in the next major version.
3. **Use the replacement everywhere in the repository:** examples, demo, docs, other components and bridges. Only the
   declaring file, other deprecated code and tests marked as legacy may still use the deprecated API; PHPStan reports
   every other use (`symfonyAi.deprecation.usage`).
4. **Test the old path:** a test that still calls the deprecated API with `#[IgnoreDeprecations]` or `#[Group('legacy')]`
   and asserts both the old behavior and the deprecation.
5. **Snapshot:** `php .github/bc/bc.php dump` and commit the updated files in `.github/bc/api/`.
6. **Check:**
   - `php .github/bc/bc.php compare upstream/main` reports the change as a deprecation, not a break.
   - PHPStan of the package passes; the rules check the tag format, the trigger arguments and that every `@deprecated`
     symbol triggers.

## Removing in a major version

Delete the deprecated code and its shims, drop the `method_exists()` guards and `func_get_arg()` reads, add a
`* Remove ...` line to the CHANGELOG and an entry with a before/after `diff` to `UPGRADE.md`, then dump the snapshots.
