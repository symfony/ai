---
name: mate-system-information
description: >-
  Resolve which dependency versions are actually installed, from composer show and
  composer.lock, when diagnosing version-specific behavior such as a method, argument or
  class that does not exist in the release that is really running. For the PHP runtime
  itself (version, OS, loaded extensions) use the php-environment-check skill instead.
---

# System Information

Use this skill when a problem might depend on the **installed dependency versions**
rather than the code: a bug that looks like a version mismatch, a method, argument, or
class that does not exist in the release that is actually installed.

## Capabilities

| Need | Use |
|---|---|
| PHP version, OS, OS family, loaded extensions | `php-environment-check` skill |
| Installed version of a package | `composer show <vendor/package>` |
| Installed versions of a family of packages | `composer show '<vendor>/<prefix>*'` |
| Authoritative record of every installed version | `composer.lock` (`packages` / `packages-dev`) |

The `php-environment-check` skill reports the runtime only, it does **not** return
package versions. Use Composer for those.

## Resolving package versions

`composer.json` declares *constraints* (what is allowed); `composer.lock` records what is
*actually installed*. For "what is really running", trust the lock, not the constraint.

- **One package:** `composer show symfony/console` → installed version plus details.
- **A family:** `composer show 'symfony/ai-*'` → every matching package and its version.
- **Machine-readable:** `composer show --format=json symfony/console`.
- **No Composer binary, or you want the raw record:** look the package up by `name` under
  `packages` in `composer.lock` and read its `version` field, but do not load the whole lock
  file into context.

Monorepo / path-repo note: locally linked packages (for example symlinked `symfony/ai-*`
in this repository) report `dev-main` or a path reference instead of a semver tag, and that
is the working copy, not a released version.

## Diagnosing a version mismatch

1. Identify the package behind the failing API; the namespace usually maps to it
   (`Symfony\Component\Console\…` → `symfony/console`).
2. Read the **installed** version with `composer show <package>` (or from `composer.lock`).
3. Compare it against the version the code expects: a method or argument added in a newer
   minor, or removed/renamed in a newer major, is the classic signature.
4. Report the gap precisely: name the package, the installed version, and the required
   one: *"symfony/console is 6.4 (installed), but the code calls a 7.1 API."*

## Rules

- Reach for `composer show` first; it is cheaper and more structured than reading
  `composer.lock` wholesale.
- Report only the *one or two* facts that change the diagnosis (e.g. "symfony/console is
  6.4, the code uses a 7.1 API"), never the entire lock file.
- Never run state-changing commands. `composer show` is read-only; `composer require`,
  `composer update`, and `composer install` are not. This skill is read-only inspection.
