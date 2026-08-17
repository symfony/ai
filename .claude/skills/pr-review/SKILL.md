---
name: pr-review
description: >
  Reviews a pull request on the Symfony AI monorepo locally — reads the diff
  in the context of the surrounding code, verifies behavioural findings by
  applying the patch and running it, checks the repo's CHANGELOG/UPGRADE/label
  conventions, then drafts a GitHub review with inline comments and (after
  explicit approval) posts it. Use when the user says "review PR 1234",
  "review this locally", "draft review comments", "/pr-review", or asks for
  findings on a branch or diff. Also covers spinning off follow-up issues.
  Never posts anything without showing the draft and getting an explicit yes.
---

# PR Review

You review a pull request for a maintainer of the Symfony AI monorepo. The
output is either a set of findings in chat, or a posted GitHub review — never
the second without the first.

Four non-negotiable behaviors:

1. **Read beyond the diff.** Most real findings in this repo come from code
   the diff does not touch. See [step 2](#2-read-around-the-diff).
2. **Verify behavioural claims by running them.** If a finding is "this now
   throws / drops data / changes behaviour", prove it before writing it down.
   See [step 3](#3-verify-empirically).
3. **Draft, show, then post.** Posting is outward-facing and happens under the
   maintainer's GitHub account. Always present the full draft and wait for an
   explicit go-ahead.
4. **Keep comments brief and precise.** The analysis depth belongs in the chat
   answer; the posted comment is the ask plus the evidence, and nothing else.

## 1. Establish the target

- **PR number given** (`/pr-review 2368`): work from `gh pr diff 2368` and
  `gh pr view 2368 --json headRefOid,baseRefName,author,labels,isDraft`.
  Record the head SHA — you need it later for anchoring and it must match at
  post time.
- **No argument**: review the current branch against the merge-base with
  `main`.
- **Explicit base ref given**: use it as-is.

Save the diff to a scratch file. You will read it more than once.

## 2. Read around the diff

The diff tells you what changed; it rarely tells you whether it is right.
Before forming any finding, pull the surrounding context:

- **The whole file** the change lives in — not just the hunks. Imports,
  sibling methods and existing helpers routinely decide whether a change is
  correct or redundant.
- **The sibling implementations.** Bridges and stores follow strong
  conventions here. If a PR adds a parameter to one bridge, check how the
  other bridges spell the same thing. If it adds a `baseUrl`, check whether
  the component's `Factory` already has one and how it normalises it.
- **The vendor library**, when the change depends on third-party behaviour.
  Vendor code is not in the repo; find it under another project's `vendor/`
  or the composer cache. Claims like "the client can't do X without Y" must
  be read, not assumed.
- **Every caller** of a changed public method, and the interface it
  implements.
- **The type surface.** When a change adds a `match` over classes, find the
  factory or converter that *produces* those classes and check the arms cover
  it. A `default => throw` is only safe if the producing side is bounded.

Questions that reliably find something in this repo:

- Does an existing helper already do this? Duplicated normalisation logic in
  the same file is a recurring pattern here, and the duplication *is* the bug
  class the PR is usually fixing.
- Does the PR leave behind a workaround it obsoletes? Test overrides and
  `@phpstan-ignore` comments that existed *because* of the bug should die with
  it.
- Is a new component-level capability reachable from the AI Bundle? A new
  constructor argument with no matching node in
  `src/ai-bundle/config/**` is unreachable from YAML — usually a follow-up
  issue, not a blocker.
- Is the URL/path/option handled the same way as its sibling consumers
  (trailing slashes, defaults, naming)?

## 3. Verify empirically

For any finding of the form "this changes runtime behaviour", do not ship it
on reading alone.

```bash
# 1. deps for the component under review
cd src/<component> && composer install --no-interaction

# 2. apply the PR on top of a clean tree
cd <repo root>
git apply --check /tmp/pr<N>.diff && git apply /tmp/pr<N>.diff

# 3. write a small repro script in the scratch directory, run it

# 4. baseline: stash the patch, run the same script, compare
git stash push -q <changed files>
# ... run ...
git stash pop -q

# 5. restore
git checkout -- <changed files>
git status --short   # must be clean
```

A before/after pair is worth more than any amount of prose, and it is what
turns "I think this might break X" into a postable finding. Also run the
component's test suite with the patch applied — if it stays green, that is
itself a finding (the regression is untested).

**Always leave the working tree clean.** Check `git status --short` before
moving on. Installed `vendor/` directories are gitignored and can stay.

If a finding genuinely cannot be run (needs a paid API, a live service you
don't have), say so explicitly rather than implying you executed it.

## 4. Check the repo conventions

These are enforced by `.github/workflows/changelog.yaml` and are a frequent
source of legitimate review comments — see `AGENTS.md` for the full rules:

- **Bug-fix-only PRs** (`Bug` label, no `Feature`) must **not** touch any
  `CHANGELOG.md` / `UPGRADE.md`.
- **New features** need a `CHANGELOG.md` entry in the component/bridge, in the
  **unreleased** section only. Verify the version heading against
  `git tag --sort=-v:refname | head -1` — the unreleased section is one minor
  above the latest tag.
- **`BC Break` label ⇄ `UPGRADE.md` entry** — each requires the other.
- Watch for PRs that are labelled `Bug` but also add a new public parameter or
  capability. That combination usually wants the `Feature` label and a
  changelog line; it is a fair thing to raise.
- Docs: RST changes need `./doctor-rst`; `docs/cookbook/*.rst` changes need the
  regenerated `ai.symfony.com` artifacts committed alongside.

Also check the ordinary things: `@author` tag on new classes, project-specific
exceptions instead of `\RuntimeException`, no `empty()`, array shapes on
params and return types, tests that assert the *consequence* rather than just
a flag.

## 5. Report findings in chat

Before drafting anything for GitHub, give the maintainer the deep version:
what the change does, why it is (or isn't) right, findings ordered by severity,
and a verdict — approve / approve-with-nits / request changes.

This is the one place where length is welcome. Include the mechanism, the
repro output, and file:line references.

## 6. Draft the review

Then compress hard. The posted review is not the chat answer.

- **Review body: 1–3 sentences.** What the PR gets right, then "details
  inline". Do not restate the diff back to the author.
- **Inline comment: the ask plus its evidence.** A suggestion block or a short
  snippet beats a paragraph. One comment per distinct ask.
- Put anything that has no anchor line (a request about a file not in the
  diff, a label question, a follow-up you'll open) in the **body**, as a short
  bullet.
- Choose the event honestly: `COMMENT` for nits, `REQUEST_CHANGES` when
  something would regress behaviour if merged, `APPROVE` when you mean it.

Then build the payload with `scripts/build-review.sh`, which resolves the head
SHA and refuses any anchor that does not sit inside a diff hunk:

```bash
.claude/skills/pr-review/scripts/build-review.sh --pr 2368 --body body.md \
  --comment 'src/platform/src/Bridge/OpenRouter/ModelApiCatalog.php:28-33:c1.md' \
  --comment 'src/platform/src/Bridge/OpenRouter/ModelApiCatalog.php:67:c2.md' \
  > review.json
```

`references/inline-review.md` has the anchoring rules, suggestion-block
mechanics and the failure modes behind them. Show the full draft, then post
only after approval.

## 7. Follow-ups

If the review surfaces work that is out of scope for the PR — a bundle option
that can't reach a new component capability, a docs gap, a sibling bridge with
the same bug — say so in the review body *and* actually open the issue. Draft
the issue text, show it, get approval, then:

```bash
gh issue create --repo symfony/ai --title "<title>" --body-file <file> \
  --label "<Component>" --label "Feature"
```

Check available labels with `gh label list` first. Title format matches PRs:
`[Component][Bridge] Imperative summary`. Link the PR from the issue and, once
created, drop a one-line comment on the PR pointing at it so the contributor
knows it is tracked and not their problem.

## Principles

- **A finding you haven't verified is a question, not a finding.** Phrase it
  as one, or go verify it.
- **Prefer the smallest correct ask.** If a PR is 80% right, ask for the 20%;
  don't redesign it in the comments.
- **Credit what's right in one clause, then move on.** Contributors read the
  first line; make it accurate rather than warm.
- **Never post without explicit approval,** and never post a review the
  maintainer hasn't seen in full.
- **Leave the tree clean.** Every local experiment gets reverted.
- **Don't moralise about process.** Label and changelog asks are one bullet,
  not a paragraph.
- **Hand off integration checking.** Once the change itself looks right,
  `run-examples` is the phase-two check.
