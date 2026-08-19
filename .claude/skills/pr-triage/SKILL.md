---
name: pr-triage
description: >
  Surveys the open pull requests on the Symfony AI monorepo and produces a
  ranked review queue with status, size and complexity per PR, so the
  maintainer can decide what to open next. Use when the user asks for an
  overview of open PRs, "what should I review", "PR status", "triage the
  PRs", or "/pr-triage". Buckets PRs by mergeability, draft state, review
  state and CI, separates genuine CI failures from known-unrelated jobs,
  and rates review complexity rather than just diff size. Read-only,
  never comments, labels or closes anything.
---

# PR Triage

You help a maintainer of the Symfony AI monorepo decide **which pull request
to open next**. The repo carries a large open-PR backlog (typically 90+), most
of it not actionable at any given moment, so the value of this skill is
subtraction: throw away everything that isn't reviewable right now, then rank
what's left.

Three non-negotiable behaviors:

1. **Rank, don't dump.** Never print all open PRs as one flat table. The output
   is a recommended queue plus summarised buckets for everything else.
2. **Rate complexity, not just size.** Diff size is the weakest signal in this
   repo, see [Complexity](#4-rate-complexity). A 70-line serialization change
   can cost more review time than a 1000-line docs PR.
3. **Read-only.** This skill never posts, labels, closes or merges. Reviewing
   is `pr-review`'s job.

## 1. Fetch the data

`gh`'s GraphQL layer is unreliable on this repo when asked for too much at
once, requesting `statusCheckRollup` inside a 100-PR `gh pr list` reliably
returns `HTTP 502` or `HTTP 504`. Split the work:

```bash
gh pr list --limit 100 \
  --json number,title,author,createdAt,updatedAt,isDraft,additions,deletions,changedFiles,labels,reviewDecision,mergeable \
  > /tmp/prs.json
```

Write to a file rather than piping into `jq` inline, if the call fails
halfway you want to see it, and you will re-query the same data several times
while classifying.

Note `gh` must run with the repo as cwd; it resolves the remote from git, so a
`cd` into a scratch directory breaks it with "no git repository".

## 2. Bucket everything

Classify every PR into exactly one bucket, in this order (first match wins):

| Bucket | Test | Why it's out |
|---|---|---|
| **Draft** | `isDraft` | Author isn't asking yet |
| **Changes requested** | `reviewDecision == "CHANGES_REQUESTED"` | Ball is with the author |
| **Approved** | `reviewDecision == "APPROVED"` | Needs a merge decision, not a review |
| **Needs rebase** | `mergeable == "CONFLICTING"` | Reviewing a conflicted diff wastes effort |
| **Yours** | author is the maintainer | Can't self-review |
| **Reviewable** | everything else | ← the queue |

Report the bucket sizes as a short table. The `CONFLICTING` count is usually
the headline number and worth calling out explicitly, it is normally over
half the backlog.

`mergeable` is computed lazily by GitHub and can come back `UNKNOWN` on a PR
that was just pushed to. Treat `UNKNOWN` as reviewable rather than dropping it,
and say so.

## 3. Get CI status for the reviewable set only

Per-PR, not in the list query:

```bash
gh pr checks <number> --json bucket,name
```

That is one API call per PR, so run it **only** for the reviewable bucket
(typically 15–25 PRs), never for all 90+.

### Failures that are usually not the PR's fault

This repo has a set of jobs that go red for reasons unrelated to the diff
under review. Classify a red check before reporting it:

| Check | Usual meaning |
|---|---|
| `Validate Changelog & Upgrade Entries` | Missing CHANGELOG entry or label mismatch, a process fix, often a one-line comment |
| `PHPStan / Demo` | Demo app drift, rarely caused by a component PR |
| `Integration / *` | Needs live services / API keys; flaky by nature |
| `Unit / Store / S3Vectors` | Known-flaky bridge suite |
| `Fabbot / Checks` | Code style, real, but trivially fixable |

A failure in the component's own `Unit / <Component>` or `PHPStan / <Component>`
job is a genuine signal and should be reported as such. When in doubt, name the
failing job rather than summarising it as "CI red", the job name is what tells
the maintainer whether to care.

## 4. Rate complexity

This is the part that earns the skill. Size sets a floor on review cost;
these signals set the real number.

Score each reviewable PR on:

- **Blast radius.** Bridge-local (one vendor, one component) < component core
  (`src/platform/src/Result/`, `src/agent/src/Toolbox/`) < cross-component or
  bundle wiring < contracts/interfaces every bridge implements.
- **BC surface.** `BC Break` label, changes to public constructors, interfaces
  or serialized formats, anything touching `UPGRADE.md`. A new *optional*
  constructor argument is cheap; a changed interface is not.
- **Semantic depth.** Streaming, generators, async/polling, serialization
  round-trips, DI compiler passes, protocol conversion and anything with a
  wide *type surface* are expensive to review because correctness depends on
  cases not visible in the diff. Config plumbing, catalog data updates,
  docs and test-only changes are cheap.
- **Verification cost.** Can you settle it by reading, or does confirming the
  behaviour need the patch applied and code run, possibly against a live
  service or Docker? Reading-only is cheap; "must run it" is not.
- **Test signal.** Meaningful tests for the changed behaviour lower cost.
  A behavioural change with no test, or a test that only asserts a flag
  rather than its consequence, raises it.

Collapse into **Low / Medium / High**, and always give a one-line reason
naming the dominant signal. Also give a rough review-time estimate, that is
what the maintainer actually schedules against.

### Calibration

Size and complexity genuinely diverge here; these are real examples:

- ~10 lines, config plumbing, mirrors an existing pattern in a sibling
  class → **Low**, minutes.
- ~1000 lines of new cookbook prose → **Low** despite the size; prose review,
  no runtime risk.
- ~200 lines of regenerated model-catalog data → **Low**; mechanical, generated.
- ~40 lines fixing a store's `supports()` guard → **Medium**; small, but you
  must read the vendor library to confirm the premise.
- ~70 lines adding serialization arms for message content → **High** despite
  the size; the type surface is far wider than the diff shows and the failure
  mode only appears at runtime.
- ~500 lines changing streamed tool calls across bridges, `BC Break` →
  **High**; broad blast radius plus BC surface.

When two signals disagree, the higher one wins. It is better to over-estimate
complexity than to have the maintainer open a PR expecting ten minutes.

## 5. Output

Structure, in this order:

1. **Landscape**, a small bucket-count table, plus the one-sentence headline
   (usually: how much of the backlog needs a rebase).
2. **The queue**, reviewable PRs grouped by review cost (quick wins /
   medium / large), each row: number, size, files, area, CI, complexity,
   title. Sort within a group by ascending cost.
3. **Picks**, two or three concrete recommendations with a reason each:
   the cheapest wins, the one that matters most, the ones that only need a
   process nudge (e.g. red only on the changelog check).
4. **Not worth opening yet**, the other buckets, summarised in one or two
   lines each. Name notable PRs; do not enumerate all of them.

Keep it scannable. The maintainer reads this to make one decision.

## Principles

- **Subtraction is the product.** Getting from 94 PRs to a queue of 5 is the
  work; the tables are just presentation.
- **Never report a bare "CI red".** Name the job and say whether it implicates
  the diff.
- **Exclude the maintainer's own PRs from the queue,** but count them, they
  may want to chase reviewers instead.
- **Don't infer quality from the author.** Rank on state, cost and impact only.
- **Say when a cluster needs a decision, not a review.** A stack of large PRs
  from one contributor exploring one direction is a roadmap conversation;
  flag it as such rather than queueing each one.
- **Stop at the recommendation.** Do not start reviewing a PR in the same
  breath, hand off to `pr-review`.
