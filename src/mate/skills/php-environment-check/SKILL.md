---
name: php-environment-check
description: Check the PHP runtime with server-info, its version, OS, and loaded extensions. Reach for it when a Mate tool itself fails or a capability is missing and you suspect the environment, a missing extension or an old PHP, rather than the app or its config. Not for diagnosing app behavior (profiler, log investigation) or DI wiring that is present but wrong (service inspection).
---

# PHP environment check

`server-info` takes no arguments and returns `php_version`, `operating_system`, `operating_system_family`, and `extensions` (the extensions loaded for this runtime). Calling it is trivial; the judgment is knowing when a failure is the runtime, not the code, and reading the result against what a feature needs.

`server-info` accepts `--format`: `json` to parse the result, `toon` (when `helgesverre/toon` is installed) for the smallest context footprint. The loaded-extension list is long, so pass `--format=json` when you only need to test for one extension.

## When to reach for it

Reach for `server-info` when a Mate tool fails or behaves oddly while the application under inspection looks fine. That gap usually means the runtime running Mate, not the app.

- **Container inspection returns nothing or errors.** `symfony-services` / `symfony-service-detail` parse the dumped container XML with SimpleXML. If `simplexml` is absent from `extensions`, that parse fails and the service map comes back empty regardless of the app. Check for `simplexml` before assuming the container was never compiled.
- **Mate itself refuses to run or a feature is missing.** Mate requires PHP >= 8.2. Read `php_version` before blaming config; an old CLI PHP on the box is a common cause.
- **Path or behavior differs from expectation.** `operating_system_family` (`Windows` vs `Linux`/`Darwin`) explains divergent path handling or line endings.

## Reading

- `extensions` is the loaded list for this SAPI, not the installed-but-disabled list. Absent means not loaded for the runtime Mate is using, which is enough on its own to explain a dependent tool failing.
- The PHP running the Mate CLI may differ from the one serving the app (different SAPI, different version). `server-info` reports the CLI runtime, so a mismatch here versus what the app runs is itself a finding.

Do not use this to diagnose application behavior. It answers one question: is the environment the reason a tool did not work.
