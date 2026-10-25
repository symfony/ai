---
status: accepted
date: 2026-10-25
---

# 0003. Extensions are discovered from Composer `extra.ai-mate`

## Context and Problem Statement

Tools live in many packages (the core package, its Symfony and Monolog bridges, third-party
packages) and in the project itself. Mate must find them without a plugin registry and without
booting the application, and the project owner must decide which ones are active.

## Decision

A package declares itself a Mate extension under `extra.ai-mate` in its `composer.json`, with
the keys `scan-dirs`, `includes` (service configuration files), `instructions` (an
`INSTRUCTIONS.md`) and `skills`. `Discovery\ComposerExtensionDiscovery::discover()` reads
`vendor/composer/installed.json`. It skips packages without the key or with
`"extension": false`, and it drops paths with a `..` segment (`PathGuard::hasTraversal()`) or
pointing to missing directories, logging a warning for each. `discoverRootProject()` reads the
same keys from the root `composer.json` and registers them as the `_custom` extension.
`mate init` seeds that root entry with `scan-dirs: ["mate/src"]`,
`includes: ["mate/config.php"]` and `"extension": false`.

Turning an extension on is a separate step owned by the project:
`ContainerFactory::getEnabledExtensions()` only loads packages marked `enabled => true` in
`mate/extensions.php`. The root project is always loaded. `mate discover` refreshes that file,
and the `symfony/ai-mate-composer-plugin` (`MatePlugin`) runs `discover --composer` after
`composer install` and `composer update` once the project is initialized. `"extension": false`
lets a package use Mate for its own development without being offered as an extension to the
projects that install it.

## Consequences

- **Good:** installing a package is enough to make its tools available. No registration code,
  and discovery needs only Composer metadata, not the application kernel.
- **Good:** the core package uses the same mechanism (`scan-dirs: ["src/Capability"]`,
  `instructions`, `skills`), so every extension takes one path.
- **Trade-off:** discovery depends on `installed.json`; without `composer install` no extension
  is found (a warning is logged).
- **Trade-off:** a newly required extension is not active until it is enabled in
  `mate/extensions.php`.
- **Trade-off:** the traversal check matches path segments, not `realpath()`, because the
  `./link` workflow symlinks `vendor/symfony/ai-*` outside the project (#2245).

## Alternatives Considered

- **A Composer package type or a dedicated plugin registry**: not chosen; an `extra` key works
  on any package type, including the root project.
- **Enable every discovered extension automatically**: not chosen; `mate/extensions.php` keeps
  the project owner in control.
- **Call them "bridges"** (`bridges.php`): used briefly at the start, then reverted to
  "extension" in #1239 as the more common, user-facing term (as in PHPStan or VS Code).

## History

- 2025-12-21, #1146 by @wachterjohannes (Johannes Wachter): extensions are found via `extra.ai-mate` and turned on in a project-owned file.
- 2025-12-22, #1239 by @wachterjohannes (Johannes Wachter): the term "extension" and `extensions.php` are restored after a short switch to "bridge".
- 2026-01-14, #1392 by @wachterjohannes (Johannes Wachter): `"extension": false` excludes packages that use Mate only for their own tooling.
- 2026-04-05, #1833 by @wachterjohannes (Johannes Wachter): a Composer plugin runs `discover` after install and update (fixes #1832).
- 2026-06-28, #2245 by @wachterjohannes (Johannes Wachter): `PathGuard` blocks package paths that point outside the package.
- 2026-07-22, #2213 by @wachterjohannes (Johannes Wachter): adds the `skills` key; #2457 (2026-08-29) ships each skill with the extension whose tools it uses.

## References

- `src/Discovery/ComposerExtensionDiscovery.php`: `extra.ai-mate` parsing and validation.
- `src/Discovery/PathGuard.php`: path traversal check.
- `src/Container/ContainerFactory.php`: `mate/extensions.php` filter, `_custom` root extension.
- `src/Command/InitCommand.php`: the root `extra.ai-mate` entry written on init.
- `composer.json`: the core package's own `extra.ai-mate` declaration.
- `composer-plugin/src/MatePlugin.php`: automatic `discover` after install and update.
- `CHANGELOG.md` (0.3): `instructions` and `extension: false`; (0.12): `skills`.
