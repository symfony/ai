---
name: symfony-dotenv-diagnostics
description: Diagnose a misconfigured .env setup, an environment variable that seems missing or wrong, or a suspicious secret found in ambient shell/CI environment rather than a project file. Use instead of `bin/console debug:dotenv`, which prints raw, unmasked secret values. Not for DI/container wiring bugs that are present but wrong (service inspection) or a missing PHP extension breaking a tool itself (php environment check).
---

# Dotenv diagnostics

`symfony-dotenv-check` (opt `key`, `limit`) reports, for each environment variable declared across
the project's `.env*` files: which file(s) declare it, whether it resolves to a non-empty value in
Mate's own CLI process right now, and where that value actually comes from. It never returns a raw
value — only `length` (character count) and `preview` (a fixed `x***y` first/last-character mask,
or `**` for anything 2 characters or shorter), plus a best-effort `looks_like_placeholder` guess.

Without a `key`, it lists every variable found across the discovered files (`{app_env, files,
variables, count, truncated}`). With `key`, it checks exactly that one variable name, even if no
file declares it — the way to confirm a name you only saw elsewhere (a leaked log line, a
`%env(FOO)%` reference in config) is real, without asking Mate to print it.

## Why not `debug:dotenv`

`bin/console debug:dotenv` prints the fully resolved, unmasked value of every variable it finds,
including ones that only resolve because the ambient shell or CI environment happens to carry a
real secret rather than any file in the project. That is exactly the failure mode this tool
removes: it gives the same diagnostic value (declared where, resolves or not) without ever putting
a real secret value in the output or an agent's transcript.

## Reading `state`

- `file`: resolves, and the resolved value matches what a project file declares. The expected,
  healthy case.
- `ambient_override`: resolves, declared in a file, but the resolved value does **not** match any
  declared file — the real value is coming from outside the project (shell export, CI secret,
  Docker env, a global profile). Worth a second look if you did not expect an override here.
- `ambient_only`: resolves, but is declared in **no** project file at all. Only reachable via the
  `key` parameter (the default listing only enumerates file-declared keys). This is the case that
  caused the original leak this tool replaces: a real secret sitting only in the ambient
  environment, invisible to the project's own `.env*` files.
- `declared_empty_in_file`: declared in a file, but the winning file's own value is the empty
  string (e.g. `KEY=` left blank, expecting an override that never happened) — a fact about file
  content, not about the current process.
- `declared_not_resolved_in_this_process`: declared with a non-empty value in a file, but does not
  resolve in Mate's own CLI process. This is not necessarily broken: Mate's `bin/mate` process does
  not boot the target application's own Dotenv, so this state simply means the file value was not
  independently exported into the shell Mate is running in. Cross-check by running the app (or
  `bin/console debug:dotenv`, mindful that it prints raw values) if this surprises you.
- `not_set`: only reachable via `key` — the name resolves nowhere and is declared in no file.

## Workflow

1. `vendor/bin/mate tools:call symfony-dotenv-check` to list everything declared across the
   project's `.env*` files.
2. Scan `state`. `ambient_override` and `ambient_only` are the two states worth investigating first
   — they mean the real value is not what the project's own files say.
3. For a specific name you already suspect (from a log, a config reference, or a report), check it
   directly: `vendor/bin/mate tools:call symfony-dotenv-check --key=HUGGINGFACE_API_KEY`.
4. Use `length` and `preview` only to sanity-check shape (empty vs. placeholder-looking vs.
   real-looking), never to reconstruct the value.

## Failure paths

- The tool is absent entirely: `symfony/dotenv` is not installed in this project. It is an optional
  dependency of the Symfony bridge, only registered when the class exists.
- `files` shows a candidate file `exists: false`: normal for `.env.local` and `.env.$APP_ENV.local`
  in most setups; only `.env` (or `.env.dist`) missing entirely means no base configuration exists.
- `parseable: false` on an existing file: the file has invalid Dotenv syntax; fix that file before
  trusting any `declared_in` result derived from it.
