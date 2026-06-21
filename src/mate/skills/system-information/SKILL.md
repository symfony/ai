---
name: system-information
description: >-
  Inspect the runtime and dependency context of the PHP application Mate is attached to —
  PHP version, OS, and loaded extensions via the server-info tool, plus installed package
  versions from composer.lock — when diagnosing environment- or version-specific behavior
  such as a missing extension or a version mismatch (an API that does not exist in the
  release that is actually installed).
---

# System Information

Use this skill when a problem might depend on the **environment or the installed
dependency versions** rather than the code: a missing or misconfigured extension,
behavior that only reproduces on a specific PHP/OS version, or a bug that looks like a
version mismatch — a method, argument, or class that does not exist in the release that
is actually installed.

## Capabilities

| Need | Use |
|---|---|
| PHP version, OS, OS family, loaded extensions | `server-info` MCP tool (one call) |
| Installed version of a package | `composer show <vendor/package>` |
| Installed versions of a family of packages | `composer show '<vendor>/<prefix>*'` |
| Authoritative record of every installed version | `composer.lock` (`packages` / `packages-dev`) |

`server-info` reports the runtime only — it does **not** return package versions. Use
Composer for those.

## Runtime checks

1. **Start with the `server-info` tool** for PHP version, OS, OS family, and loaded
   extensions — prefer it over `php -v`, `php -m`, or `uname`.
2. **Confirm a required extension is loaded** before blaming the code. If it is absent
   from `server-info`, the fix is an environment/configuration change, not a code change.

## Resolving package versions

`composer.json` declares *constraints* (what is allowed); `composer.lock` records what is
*actually installed*. For "what is really running", trust the lock, not the constraint.

- **One package:** `composer show symfony/console` → installed version plus details.
- **A family:** `composer show 'symfony/ai-*'` → every matching package and its version.
- **Machine-readable:** `composer show --format=json symfony/console`.
- **No Composer binary, or you want the raw record:** look the package up by `name` under
  `packages` in `composer.lock` and read its `version` field — do not load the whole lock
  file into context.

Monorepo / path-repo note: locally linked packages (for example symlinked `symfony/ai-*`
in this repository) report `dev-main` or a path reference instead of a semver tag — that
is the working copy, not a released version.

## Diagnosing a version mismatch

1. Identify the package behind the failing API — the namespace usually maps to it
   (`Symfony\Component\Console\…` → `symfony/console`).
2. Read the **installed** version with `composer show <package>` (or from `composer.lock`).
3. Compare it against the version the code expects: a method or argument added in a newer
   minor, or removed/renamed in a newer major, is the classic signature.
4. Report the gap precisely — name the package, the installed version, and the required
   one: *"symfony/console is 6.4 (installed), but the code calls a 7.1 API."*

## Rules

- Reach for `server-info` and `composer show` first; they are cheaper and more structured
  than reading `composer.lock` or `php -m` wholesale.
- Report only the *one or two* facts that change the diagnosis (e.g. "ext-intl is not
  loaded", "symfony/console is 6.4, the code uses a 7.1 API") — never the full extension
  dump or the entire lock file.
- Never run state-changing commands. `composer show` is read-only; `composer require`,
  `composer update`, and `composer install` are not. This skill is read-only inspection.
