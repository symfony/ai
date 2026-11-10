---
name: pr-review
description: >
  Reviews a pull request on the Symfony AI monorepo locally, reads the diff
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
output is either a set of findings in chat, or a posted GitHub review, never
the second without the first.

Five non-negotiable behaviors:

1. **Read the existing discussion before you form an opinion.** It frequently
   decides what the review should say. See [step 2](#2-read-what-has-already-been-said).
2. **Read beyond the diff.** Most real findings in this repo come from code
   the diff does not touch. See [step 3](#3-read-around-the-diff).
3. **Verify behavioural claims by running them.** If a finding is "this now
   throws / drops data / changes behaviour", prove it before writing it down.
   See [step 4](#4-verify-empirically).
4. **Draft, show, then post.** Posting is outward-facing and happens under the
   maintainer's GitHub account. Always present the full draft and wait for an
   explicit go-ahead.
5. **Keep comments brief and precise.** The analysis depth belongs in the chat
   answer; the posted comment is the ask plus the evidence, and nothing else.

## 1. Establish the target

- **PR number given** (`/pr-review 2368`): work from `gh pr diff 2368` and
  `gh pr view 2368 --json headRefOid,baseRefName,author,labels,isDraft`.
  Record the head SHA, you need it later for anchoring and it must match at
  post time.
- **No argument**: review the current branch against the merge-base with
  `main`.
- **Explicit base ref given**: use it as-is.

Save the diff to a scratch file. You will read it more than once.

## 2. Read what has already been said

Do this **before** forming any opinion, not after drafting one. A PR in this repo
often carries months of discussion, and it routinely decides what the review
should say:

```bash
gh api repos/<owner>/<repo>/pulls/<N>/reviews          # formal reviews
gh api repos/<owner>/<repo>/pulls/<N>/comments         # inline review comments
gh api repos/<owner>/<repo>/issues/<N>/comments        # the discussion thread
gh pr view <N> --json body                             # the description and its template table
```

Note that the discussion thread is a separate endpoint from the reviews, and on
older PRs it is usually where the substance lives. A PR can show zero reviews and
still have a settled design decision in its comments.

What to extract:

- **Decisions already taken.** If maintainers and contributors agreed on an
  approach, a finding that re-litigates it is noise. Something you were about to
  call a blocker may already be an accepted trade-off with a documentation task
  attached.
- **Requests still outstanding.** An unanswered ask from another maintainer is
  worth restating with your review; it carries more weight than a fresh one.
- **Things already explained.** Contributors often diagnose their own CI failures
  or justify a decision in a comment. Repeating the question wastes their time and
  signals the review was not read.
- **How long they have waited, and who they pinged.** If the ball sat with the
  maintainers for months, say so first.
- **The description's template table**, checked against what the diff actually
  does (a `Bug fix? no` / `New feature? no` PR that edits a `CHANGELOG.md` is
  inconsistent).
- **The description's prose, not only its table.** Symfony's merge workflow
  embeds the PR body verbatim as the `Discussion` section of the merge commit,
  so a wrong justification becomes permanent history. When the body explains
  *why* a change was needed ("this used to emit a warning", "the counter was
  reset every round"), reproduce that claim before accepting it. Twice it has
  described behaviour that no longer existed, or never did, while the change
  itself was fine. The finding is then "the change is right, the stated reason
  is not", which is a one-line reword, not a code change.

### Re-checking a PR you already reviewed

"Has the author responded?" is not the same query. Four signals, three of which
are invisible in the issue-comment thread:

```bash
gh pr view <N> --json headRefOid,commits    # a push leaves no comment at all
gh api repos/<owner>/<repo>/pulls/<N>/comments   # replies to inline comments live here
gh api repos/<owner>/<repo>/pulls/<N>/reviews    # another maintainer weighing in
gh api repos/<owner>/<repo>/issues/<N>/comments  # the thread
```

Checking only the last one reports "no activity" for a PR whose author pushed a
fix and replied inline. Compare the head SHA against the one your review was
pinned to: if it moved, re-verify before saying anything, and diff the two SHAs
rather than re-reading the whole PR.

Checking only GitHub also misses a fifth signal: **what you already did in this
session.** Before proposing an action, confirm it is not one you have taken
already. Suggesting a reminder for a review you posted hours earlier reads as
not having followed your own thread.

Say explicitly in the review which existing points you are agreeing with,
extending, or reversing. Reversing another maintainer's call is fine, but do it
openly and give the reason.

### When the author reports a fix

A reply saying "fixed in <sha>" is a claim, not a result. Re-verify it rather
than reading the diff and agreeing, especially when the author says they could
not run the suite locally, or when the fix was suggested by you: a correctly
applied suggestion can still be wrong in a way neither of you looked at.

Check that the new test actually guards the fix by breaking the fix and watching
it fail. A test asserting the shape of a string can pass while the behaviour it
describes is broken.

## 3. Read around the diff

The diff tells you what changed; it rarely tells you whether it is right.
Before forming any finding, pull the surrounding context:

- **The whole file** the change lives in, not just the hunks. Imports,
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
  `src/ai-bundle/config/**` is unreachable from YAML, usually a follow-up
  issue, not a blocker.
- Is the URL/path/option handled the same way as its sibling consumers
  (trailing slashes, defaults, naming)?

## 4. Verify empirically

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
component's test suite with the patch applied, if it stays green, that is
itself a finding (the regression is untested).

**Always leave the working tree clean.** Check `git status --short` before
moving on. Installed `vendor/` directories are gitignored and can stay.

If a finding genuinely cannot be run (needs a paid API, a live service you
don't have), say so explicitly rather than implying you executed it.

## 5. Check the repo conventions

These are enforced by `.github/workflows/changelog.yaml` and are a frequent
source of legitimate review comments, see `AGENTS.md` for the full rules:

- **Bug-fix-only PRs** (`Bug` label, no `Feature`) must **not** touch any
  `CHANGELOG.md` / `UPGRADE.md`.
- **New features** need a `CHANGELOG.md` entry in the component/bridge, in the
  **unreleased** section only. Verify the version heading against
  `git tag --sort=-v:refname | head -1`, the unreleased section is one minor
  above the latest tag.
- **`BC Break` label ⇄ `UPGRADE.md` entry**, each requires the other.
- Watch for PRs that are labelled `Bug` but also add a new public parameter or
  capability. That combination usually wants the `Feature` label and a
  changelog line; it is a fair thing to raise.
- Docs: RST changes need `./doctor-rst`; `docs/cookbook/*.rst` changes need the
  regenerated `ai.symfony.com` artifacts committed alongside.

Also check the ordinary things: `@author` tag on new classes, project-specific
exceptions instead of `\RuntimeException`, no `empty()`, array shapes on
params and return types, tests that assert the *consequence* rather than just
a flag.

### Read the nearest AGENTS.md

`AGENTS.md` exists per component as well as at the root, and the component one
carries the conventions that actually matter for the code under review. For
`src/platform` that includes the record-and-replay scaffolding: a PR adding or
changing a result converter should bring a cassette so `ExamplesReplayTest`
pins it against the provider's real shapes. That test iterates over the
cassettes rather than the examples, so a bridge without one is silently
uncovered and the suite still goes green.

When raising something from there, give the reason rather than citing the file.
"This is the direction we want bridges to go, and here is why" lands; "AGENTS.md
requires it" reads as bureaucracy to a contributor who has never opened it.

### Rule out the false positives first

Most things that look wrong in an unfamiliar bridge or component are not. Each
of these has produced a wrong finding at least once:

- **Artefacts your own commands created.** `composer.lock`, `.phpunit.result.cache`
  and `vendor/` appear after you run anything. Check `git ls-files <path>` before
  reporting a file as committed.
- **`self::assert*` inside a static closure**, where `$this` is not bound. That
  is required, not a convention breach.
- **Ordering in a file that is not ordered.** Confirm the surrounding list is
  alphabetical before calling an insertion misplaced.
- **A missing option in the bundle config.** Compare against the siblings: if
  every comparable bridge omits it too, the omission is the convention.
- **A dependency that looks unused.** Grep the whole package, including traits
  and factories, before calling it unjustified.
- **A local install that will not resolve.** A brand-new bridge is not on
  Packagist yet; `php .github/build-packages.php` is what CI uses to wire the
  path repositories, and without it `composer install` fails for reasons that
  have nothing to do with the PR.

## 6. Report findings in chat

Before drafting anything for GitHub, give the maintainer the deep version:
what the change does, why it is (or isn't) right, findings ordered by severity,
and a verdict, approve / approve-with-nits / request changes.

This is the one place where length is welcome. Include the mechanism, the
repro output, and file:line references.

## 7. Draft the review

Then compress hard. The posted review is not the chat answer.

- **Review body: 1 to 3 sentences.** What the PR gets right, then "details
  inline". Do not restate the diff back to the author.
- **Inline comment: the ask plus its evidence.** A suggestion block or a short
  snippet beats a paragraph. One comment per distinct ask.
- Put anything that has no anchor line (a request about a file not in the
  diff, a label question, a follow-up you'll open) in the **body**, as a short
  bullet.
- **Never `APPROVE` a review that asks for anything.** If any comment requests a
  change, however small, the event is `COMMENT` for nits or `REQUEST_CHANGES`
  when merging as-is would be wrong. `APPROVE` is only for a review with no
  open ask. An approval plus a request tells the contributor both that the PR
  is done and that it is not, and lets it merge with the ask unaddressed.
- **Verification is one phrase: "Verified locally, all green."** No test or
  assertion counts, and no inventory of what ran; even "platform and agent
  suites, PHPStan, cs-fixer" is too much. What you ran is evidence for the chat
  answer, not for the contributor. A named failing test is fine when the
  failure is itself the finding.

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

## 8. Follow-ups

If the review surfaces work that is out of scope for the PR, a bundle option
that can't reach a new component capability, a docs gap, a sibling bridge with
the same bug, say so in the review body *and* actually open the issue. Draft
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

- **Assume the PR has a history.** Zero reviews does not mean nobody has
  weighed in; the discussion thread is a different endpoint. A review that
  re-asks a settled question reads as not having been read.
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
- **Say what you did not review.** On a large PR, or a new bridge, separate the
  structural verdict you can give from the scope decision you cannot. Naming
  who should weigh in is more useful than an opinion you are not in a position
  to hold.
- **Hand off integration checking.** Once the change itself looks right,
  `run-examples` is the phase-two check.
