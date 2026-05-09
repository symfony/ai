---
name: run-examples
description: >
  Picks and runs the examples under examples/ that exercise the code changed on
  the current branch / PR / diff, as a phase-two integration check after a code
  review. Use when the user says "run the relevant examples", "verify with
  examples", "test the changes with examples", "/run-examples", or asks to
  execute matching examples for a PR/branch. Always proposes the candidate
  list (with reasons + skip notes) and waits for approval before executing.
  Handles missing API keys via examples/.env.local and brings up the right
  Docker services when needed.
---

# Run Examples

You help the maintainer of the Symfony AI monorepo run the subset of
`examples/` scripts that actually exercise the code on the current branch / PR.
This is a *phase-two* check that runs **after** a code review (e.g. via
`/pr-review`) once the maintainer is comfortable with the change itself and
wants to see the examples still light up green.

Two non-negotiable behaviors:

1. **Propose first, then execute.** Always present the candidate examples and
   wait for approval before running anything. The point of this skill is to
   give the maintainer a thinking step, not to autorun.
2. **Don't pretend skipped examples passed.** If an example can't run because a
   required key is missing from `examples/.env.local`, list it as *skipped*
   with the reason. Don't quietly drop it.

## 1. Determine the changeset

Same approach as `pr-review`:

- **PR number given** (e.g. `/run-examples 1234`): `gh pr checkout 1234`
  if not already on the branch, then derive the diff against the PR's
  `baseRefName` (`gh pr view 1234 --json baseRefName`).
- **No argument**: review the current branch against the merge-base with `main`
  (fall back to `master`).
- **Explicit base ref given** (e.g. the maintainer says "base is `develop`" or
  "compare against `<sha>`"): use that ref as the base instead of detecting
  one. This also covers replaying a historical change from a worktree.

Get the changed files with `git diff --name-only <base>..HEAD`. Read the actual
diff (`git diff <base>..HEAD`) only if you need the contents to make better
mapping decisions — for picking *which* examples to run, the file list is
usually enough.

## 2. Map changes → relevant examples

The mapping is mostly mechanical because the monorepo has strong conventions.
Apply these in order; a single change can match multiple categories.

### 2a. Bridge changes (most common, most direct)

`src/<component>/src/Bridge/<Vendor>/...` → examples that import that namespace.

The reliable way to find them is to grep `examples/` for the namespace prefix
of the changed Bridge:

```
git diff --name-only <base>..HEAD | grep '^src/.*/src/Bridge/'
# for each Bridge directory, grep examples/ for its namespace
grep -rl "Symfony\\\\AI\\\\<Component>\\\\Bridge\\\\<Vendor>" examples/
```

This catches both the "obvious" matches (Bridge `OpenAi/Embeddings/` → uses in
`examples/openai/embeddings.php`) and the cross-cutting ones (a Platform
OpenAI change is also exercised by `examples/rag/postgres.php` because it uses
OpenAI for embeddings).

Common shortcuts that hold:

- `src/platform/src/Bridge/<Vendor>/` → `examples/<vendor>/*.php` plus any
  `examples/rag/`, `examples/store/`, `examples/indexer/`, `examples/retriever/`,
  `examples/chat/`, `examples/memory/` script that uses that vendor for
  embeddings or chat.
- `src/store/src/Bridge/<Backend>/` → `examples/store/<backend>.php`,
  `examples/rag/<backend>.php` (and variants like `<backend>-openai.php`),
  plus indexer/retriever/memory scripts that import the bridge.
- `src/chat/src/Bridge/<Backend>/` → `examples/chat/persistent-chat-<backend>.php`.
- `src/agent/src/Bridge/<Tool>/` → `examples/toolbox/<tool>.php` and any
  example that uses that tool.

When in doubt, trust the grep result over the path heuristic.

**Express runs at folder granularity when you can.** If most/all candidates
live in one subdirectory (typical for a Bridge change touching only
`<vendor>/` scripts), propose running that subdirectory via the runner —
`./runner <vendor>` — rather than enumerating each script. The runner is the
intended unit; it parallelises, captures output, and reports a summary table.
Use `./runner --filter=<pattern>` when you want a name-based subset
(e.g. `--filter=toolcall` to hit toolcall variants across vendors). Reserve
per-script enumeration for when the candidates are scattered across
directories or you genuinely want only a handful from a much larger set.

### 2b. Component-level changes (Platform, Agent, Store, Chat core)

Changes outside any Bridge folder (e.g. `src/platform/src/Result/`,
`src/agent/src/Toolbox/`, `src/store/src/Document/`) tend to affect many
examples. Don't enumerate hundreds of scripts. Instead:

- Identify which *capabilities* the change touches (streaming, structured
  output, tool calling, message bag, vectorizer, ...).
- Pick a small representative set of examples per affected capability —
  ideally **one per capability**, two if the capability has meaningfully
  different shapes (e.g. tool-call sync vs. tool-call streaming). The
  maintainer can always ask to broaden; reining in is harder.
- **Prefer Docker-free examples.** Unless the change is specifically about a
  store / persistence backend, pick representatives that don't need
  `docker compose up`. Persistent-chat → prefer `cache` / `doctrine-dbal` /
  `session` / `static` over `redis` / `mongodb` / `meilisearch`. RAG → prefer
  `in-memory.php` over `postgres.php`. Skip the Docker spin-up cost when it
  doesn't add coverage for the diff.
- If the change is broad and uncontroversial (refactor, type tightening,
  internal rename), suggest running `./runner` against one or two vendor
  subdirectories the maintainer is most familiar with rather than hand-picking.

When uncertain, propose fewer examples and explicitly say so — the maintainer
can ask for more.

### 2c. When nothing in `examples/` covers the diff

Some diffs are simply not within scope of this skill. The clear cases:

- **Bundle-only diffs** (`src/ai-bundle/`, `src/mcp-bundle/`) — `examples/`
  scripts instantiate factories directly and never boot the Symfony kernel,
  so they don't exercise bundle wiring at all.
- **Fixtures, docs, infra, CI** — nothing to run.
- **Tests-only diffs** — the change *is* the test; running examples adds no
  signal.

In these cases the output is short and final:

1. State that nothing in `examples/` applies, with a one-line reason.
2. Optionally add a "Gap check" line *only* if the diff adds a new public
   capability that plausibly should have a demonstrating example (a new
   bridge, a new content type, a new public API surface) — but not for
   internal refactors, tests, or wiring fixes.
3. Stop.

**Do not** suggest running phpunit, the demo app, CI workflows, or any other
tool — those have their own coverage and are out of this skill's scope.
**Do not** propose a "smoke test of a random vendor anyway" as a fallback —
if the diff doesn't touch that vendor's code, running it gives no signal.
**Do not** add a "Recommendation" section pointing at non-`examples/`
tooling. Saying "nothing applies" with a reason *is* the complete answer —
trust the maintainer to know what their other tools are.

## 3. Filter by runnability

For every candidate example produced in step 2, decide whether it can actually
run **on this machine right now**. Each example reaches this state by passing
through:

### 3a. Required env vars

`examples/bootstrap.php` defines `env(string $var)` that prints an error and
`exit(1)` if the var is empty. So the gate is: every `env('FOO')` call inside
the example file must have `FOO` non-empty in `examples/.env.local` (or, as a
fallback, in the shell environment / `examples/.env` defaults).

To check:

```
grep -oE "env\('[^']+'\)" path/to/example.php | sort -u
```

Then look up each var in `examples/.env.local` (parse it: `KEY=VALUE`, ignore
blank lines and `#` comments, treat empty `VALUE` as missing). `examples/.env`
holds defaults for *infrastructure* env vars (hosts, ports for local Docker
services); `.env.local` is where the maintainer keeps API keys. A var is
"available" if it's filled in either file.

### 3b. Docker-backed services

Some examples talk to a service from `examples/compose.yaml` rather than (or
in addition to) a third-party API. Detection heuristic: if the example
references any of these env vars, it needs Docker:

| Env var(s) used                                  | Compose service name |
|--------------------------------------------------|----------------------|
| `MARIADB_URI`                                    | `mariadb`            |
| `POSTGRES_URI`                                   | `postgres`           |
| `MONGODB_URI`                                    | `mongodb`            |
| `REDIS_HOST`                                     | `redis`              |
| `MEILISEARCH_HOST`                               | `meilisearch`        |
| `QDRANT_HOST`                                    | `qdrant`             |
| `WEAVIATE_HOST`                                  | `weaviate`           |
| `MILVUS_HOST`                                    | `milvus` (+ `etcd`, `minio`) |
| `ELASTICSEARCH_ENDPOINT`                         | `elasticsearch`      |
| `OPENSEARCH_ENDPOINT`                            | `opensearch`         |
| `CHROMADB_HOST` / `CHROMADB_PORT`                | `chromadb`           |
| `TYPESENSE_HOST`                                 | `typesense`          |
| `NEO4J_HOST` / `NEO4J_PASSWORD`                  | `neo4j`              |
| `SURREALDB_HOST`                                 | `surrealdb`          |
| `MANTICORESEARCH_HOST`                           | `manticore`          |
| `CLICKHOUSE_HOST`                                | `clickhouse`         |
| `POGOCACHE_HOST`                                 | `pogocache`          |
| `PINECONE_HOST` (when set to `127.0.0.1`/local)  | `pinecone`           |
| `LITELLM_HOST_URL`                               | `litellm` (+ `litellm-db`) |

Examples that hit a *remote* API (`OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, `GEMINI_API_KEY`,
`AZURE_*`, `AWS_*`, `BEDROCK`, `OPENROUTER_KEY`, ...) do not need Docker —
they only need the key.

The local model runners (`OLLAMA_HOST_URL`, `LMSTUDIO_HOST_URL`,
`DOCKER_MODEL_RUNNER_HOST_URL`) point at a *separately running* local server
that the maintainer manages outside `compose.yaml`. The defaults in
`examples/.env` already point them at sensible local URLs, so treat them
like any other env-gated example: if the host var is set, the example is
runnable. If the local server isn't actually up, the example will exit
early and the runner will report it as Skipped — that's fine. Don't
preemptively mark these as unrunnable just because they need a local
runner; the maintainer often has one running.

### 3c. Runnability outcome

For each candidate, classify into one of:

- **runnable** — all required env vars are set; any Docker services it needs
  are listed for confirmation.
- **skipped (missing keys)** — list which env vars are empty so the maintainer
  can decide whether to fill them in.
- **needs Docker** — runnable in principle but requires `docker compose up -d --wait <services>`
  first. Group these so we can start everything in one command.

## 4. Propose the plan

Output a structured proposal. Don't run anything yet. Express the run as
**runner invocations** when possible — `./runner <subdir>` or `./runner
--filter=<pattern>` — and only fall back to per-script enumeration when the
candidates don't sit naturally inside one folder.

```
## Proposed example runs for <branch / PR #>

Changeset: <one-line summary — e.g., "Adds streaming support to
src/platform/src/Bridge/OpenAi/Embeddings/">

### Will run
`cd examples && ./runner openai`  — all 17 OpenAI scripts, exercises the
   modified `OpenAi\Embeddings\ResultConverter`

If multiple subdirs are involved, list each on its own line. If the right
unit is "a few specific scripts", list those instead (with a one-line "why"
each).

### Docker services to start first
`docker compose up -d --wait postgres`  — needed by `rag/postgres.php` only

(Omit the Docker section entirely if no service is required.)

### Skipped (M)
- `examples/anthropic/*` — `ANTHROPIC_API_KEY` is empty in `.env.local`

Reply with "go" to run, or tell me which to keep / drop.
```

Keep it tight. Be selective rather than maximalist — propose the *minimum*
that meaningfully exercises the diff, not the maximum that touches the
namespace. If a folder has 14 scripts and only 2 are relevant to the change,
list those 2 — don't propose `./runner <vendor>` just because you can. The
runner shortcut is for when running the whole folder is genuinely the right
unit (e.g. a Bridge-internal change that could affect any of its examples).

## 5. Execute (after approval)

Once the maintainer says go (or edits the list):

1. **Start Docker services** if any are needed:
   ```
   docker compose up -d --wait <service> <service> ...
   ```
   Run this from `examples/`. `--wait` blocks until healthchecks pass.
   Don't blanket-up the entire `compose.yaml` — only the services we need.
   If services fail to come up, stop and surface the error; don't try to
   run examples against a half-broken stack.

2. **Run the examples** via `./runner` by default — it parallelises and
   reports a Finished / Failed / Skipped summary table:
   ```
   cd examples && ./runner <subdir>           # whole subdir, e.g. ./runner openai
   cd examples && ./runner <a> <b> <c>        # multiple subdirs
   cd examples && ./runner --filter=<pattern> # by name pattern, e.g. --filter=toolcall
   ```
   "Skipped" rows mean the example exited early — usually a missing env
   var; cross-reference with the step-3 skip list. Only fall back to a
   bare `php <path>` invocation when you specifically need the verbose
   output of one example (e.g. to debug a failure with `-vvv`).

3. **Report results**. Group by outcome:
   - Failures: show the example path, exit code, and a short snippet of the
     error output (stderr if available, otherwise stdout). Don't paste
     hundreds of lines — quote the first few lines that show the actual
     error.
   - Successes: just count them.
   - Skipped: pass through what was already known.

   If anything failed, suggest re-running with `-vvv` for full HTTP logs:
   `php <path> -vvv`.

4. **Don't tear down Docker.** Leave services up — the maintainer probably
   wants to iterate. Only suggest `docker compose down` if they ask.

## Principles

- **Be selective, not maximalist.** Better to propose 5 highly-relevant
  examples than 50 vaguely-related ones. "It imports the namespace" alone
  isn't a reason to run something — the diff has to plausibly affect what
  the example exercises. Reining in is harder than expanding.
- **Prefer the runner over per-script lists** when the unit of work is
  "a folder" or "a name pattern". `./runner openai` reads better than 17
  bullet points. Only enumerate scripts when the candidates are scattered
  or genuinely a small subset.
- **Prefer Docker-free representatives** unless the diff is specifically
  about a store / persistence backend. Don't make the maintainer spin up
  containers just to verify a change in core platform/agent code.
- **Explain your reasoning per example.** A one-line "why" next to each
  candidate (which file/namespace it ties to) lets the maintainer veto
  quickly.
- **Surface skips, don't hide them.** If half the candidates can't run,
  that's information — say so up front so the maintainer can decide whether
  to fill in keys before running.
- **Don't auto-fix env vars.** Never write to `.env.local` or suggest
  values for missing API keys. Just report what's missing.
- **Stay in scope.** This skill is *only* about `examples/`. If the diff
  isn't covered by any example, say so and stop — don't pivot to phpunit,
  demo runs, or other tools that have their own CI coverage.
