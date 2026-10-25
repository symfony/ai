---
status: accepted
date: 2026-10-25
---

# 0003. Extensions are discovered from Composer `extra.ai-mate`

## Context and Problem Statement

Tools live in many packages (the core package, its Symfony and Monolog bridges, third-party
packages) and in the project itself. Mate has to find them without a plugin registry and without
booting the application, and the project owner must stay in control of which ones are active.

## Decision

A package declares itself a Mate extension under `extra.ai-mate` in its `composer.json`, with the
keys `scan-dirs`, `includes` (service configuration files), `instructions` (an `INSTRUCTIONS.md`)
and `skills`. `Discovery\ComposerExtensionDiscovery::discover()` reads
`vendor/composer/installed.json`, skips packages without the key or with `"extension": false`,
drops paths containing traversal (`PathGuard::hasTraversal()`) or pointing to missing
directories, and logs a warning for each rejected entry. `discoverRootProject()` reads the same
keys from the root `composer.json`; its result is registered as the `_custom` extension.
`mate init` seeds that root entry with `scan-dirs: ["mate/src"]`, `includes: ["mate/config.php"]`
and `"extension": false`.

Activation is a separate, project-owned step: `ContainerFactory::getEnabledExtensions()` only
loads packages whose entry in `mate/extensions.php` has `enabled => true`. The root project is
always loaded. `mate discover` refreshes that file, and the `symfony/ai-mate-composer-plugin`
(`MatePlugin`) runs `discover --composer` after `composer install` and `composer update` once
the project is initialized. The `"extension": false` written by `mate init` keeps a package that
uses Mate for internal tooling from being picked up as an extension by its consumers.

## Consequences

- **Good:** installing a package is enough to make its tools available for enabling; no
  registration code, and discovery needs only Composer metadata, not the application kernel.
- **Good:** the core package uses the same mechanism (`scan-dirs: ["src/Capability"]`,
  `instructions`, `skills` in `composer.json`), so there is one path for every extension.
- **Trade-off:** discovery depends on `installed.json`; without `composer install` no
  extension is found (a warning is logged).
- **Trade-off:** enabling is a second step in `mate/extensions.php`; a freshly required
  extension is not active until it is enabled there.

## Alternatives Considered

- **A Composer package type or a dedicated plugin registry**: not chosen; an `extra` key
  works on any package type, including the root project.
- **Enable every discovered extension automatically**: not chosen; `mate/extensions.php` keeps
  the project owner in control of what runs.

## References

- `src/Discovery/ComposerExtensionDiscovery.php`: `extra.ai-mate` parsing and validation.
- `src/Discovery/PathGuard.php`: path traversal check.
- `src/Container/ContainerFactory.php`: `mate/extensions.php` filter, `_custom` root extension.
- `src/Command/InitCommand.php`: the root `extra.ai-mate` entry written on init.
- `composer.json`: the core package's own `extra.ai-mate` declaration.
- `composer-plugin/src/MatePlugin.php`: automatic `discover` after install and update.
- `CHANGELOG.md` (0.3): `instructions` and `extension: false`; (0.12): `skills`.
