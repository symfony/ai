---
name: issue-triage
description: >
  Surveys the open GitHub issues on the Symfony AI monorepo and finds which
  ones are answerable or fixable right now. Distinct from pr-triage/pr-review,
  which cover pull requests. Buckets issues by whether the maintainer can
  resolve them by reading current code: already fixed by a merged PR or an
  independent rewrite, a factual question the code can answer directly, a
  real unfixed bug, or a design decision needing the maintainer's opinion.
  For issues already fixed elsewhere, drafts a short, friendly closing
  comment linking the PR or commit that fixed it, and closes the issue after
  explicit approval. Use when the user asks to triage issues, "check the
  issues", "any issues we could close or answer", or "/issue-triage". Never
  comments, closes or answers without showing the draft first and getting an
  explicit yes, one issue at a time.
---

# Issue Triage

You help a maintainer of the Symfony AI monorepo find which open issues can
actually be moved right now, either closed because the code has already
moved past them, or answered because the current code settles the question,
or fixed with a small correct change. Most open issues are none of those:
they're RFCs waiting on a contributor, or discussions waiting on the
maintainer's own opinion. The value here is the same as `pr-triage`:
subtraction first, then a short, concrete list of things worth actually
doing.

Three non-negotiable behaviors:

1. **Verify against current code before recommending anything.** An issue
   filed months ago may point at a class, method or behavior that no longer
   exists. "This looks stale" is a guess; grep the file and confirm before it
   becomes a finding.
2. **One draft at a time, explicit yes per issue.** Never batch-post replies
   or closes. Show the exact comment text (and note whether it also closes
   the issue), wait for an explicit yes, then act, then move to the next
   candidate.
3. **Read-only until approved.** Fetching, bucketing and drafting never touch
   GitHub. Only a comment or close the maintainer has explicitly approved
   does.

## 1. Fetch the data

```bash
gh issue list --repo symfony/ai --limit 200 \
  --json number,title,author,createdAt,updatedAt,labels,comments,assignees \
  > /tmp/issues.json
```

Write to a file rather than piping into `jq` inline, you'll re-query the same
data while classifying and want to see a partial failure.

## 2. Bucket everything

Classify every issue into one bucket. The first four are where the value is;
the rest exist so nothing gets silently dropped from consideration.

| Bucket | Test | What it means |
|---|---|---|
| **Already fixed elsewhere** | The behavior it describes traces to code that has since been rewritten, or a PR closing it already exists | Close candidate |
| **Answerable now** | A factual question ("does X support Y", "why does Z happen") the current code settles directly | Reply candidate |
| **Fixable now** | A small, well-scoped, still-reproducible bug with no PR yet | Worth drafting a fix, hand off to actual coding, not this skill |
| **Needs a maintainer decision** | A direct, unanswered question to a specific maintainer, or a design fork with no consensus | Ping candidate, not an answer you can give |
| RFC / feature discussion | Multi-comment thread proposing a feature, no concrete question pending | Wait for a contributor |
| No comments, fresh | Filed recently, nobody has responded yet | Too young to call stale |
| Stalled | Bot-flagged for inactivity (e.g. `carsonbot`) | Close-or-keep call for the maintainer, not a triage output |
| Duplicate | Same root cause as another open issue | Note the pairing, don't triage both separately |
| Maintainer's own roadmap | Filed and driven by a maintainer for their own planning | Not yours to answer unilaterally |

## 3. Verify before recommending

For anything landing in "already fixed elsewhere" or "answerable now", check
it against the repository, not against memory of the issue thread:

- **Grep for the class, method or file the issue names** on `main`. If it's
  gone or rewritten, read the commit or PR that changed it and confirm the
  new code actually addresses what the issue described, don't assume a
  rewrite in the same area fixed this specific complaint.
- **Search merged PRs and recent commit messages** for the issue number
  (`Fix #1744`) or the same symptom. `gh pr list --search "1744 in:body"` and
  `git log --oneline --grep=<keyword>` both find things a linked-PR search
  misses.
- **For a factual question**, answer from the code you can currently read,
  cite the file:line. Don't answer from what the issue's comments assumed at
  the time, that assumption may itself be the thing that's since changed.
- **Check the issue's current state before drafting anything.** Another
  maintainer may have already closed or answered it since you last looked;
  re-fetch the specific issue (`gh issue view <N>`) right before drafting, not
  just from the bulk list pulled in step 1.

A recommendation that skips this step is a guess wearing a finding's clothes.

## 4. Draft the close-with-comment, when confident

This is the concrete output for "already fixed elsewhere": don't just note it
in a report, draft the actual GitHub action and offer it.

- **Comment: short, friendly, names the fix.** Link the PR or commit that
  fixed it, in one or two sentences. No inventory of what changed, that
  belongs in the PR, not the closing comment.
- **Always leave a reopen door.** Mirror the tone maintainers here already
  use: *"This looks fixed by #1730. Feel free to reopen if it's not."* Never
  close a report with an unverifiable "should be fine now", closing is a
  claim, not a shrug.
- **When you're not fully certain the fix covers it**, don't close: comment
  asking the reporter to retest on current `main`, and leave the issue open.
  Closing on a guess forces the reporter to notice and reopen; asking first
  costs them one comment and avoids that.
- **Show both the comment text and whether the action also closes the issue**
  before asking for the go-ahead, the maintainer needs to approve the close
  as a distinct action from the comment.

```bash
gh issue comment <N> --repo symfony/ai --body-file comment.md
gh issue close <N> --repo symfony/ai --reason completed   # only after explicit approval, only once confident
```

## 5. Draft the answer, for factual questions

Same shape as a close, minus the close: a short reply citing what the current
code actually does, with a file:line reference in the chat answer (the
GitHub comment itself stays brief, per [[pr-review-comments-brief]]-style
brevity). If the answer resolves the issue outright, ask the maintainer
whether to close it too, don't assume.

## 6. Output

1. **Landscape**, a bucket-count table, one-sentence headline (usually: how
   much of the backlog is RFCs nobody's picked up vs. genuinely actionable).
2. **Already fixed elsewhere**, one line per issue: number, what it traced
   to, the PR/commit that fixed it, confidence (close now vs. ask to
   retest first).
3. **Answerable now**, one line per issue: number, the question, the answer
   and its file:line evidence.
4. **Fixable now**, one line per issue: number, what's actually broken, why
   it's still real (checked against current code).
5. **Needs a decision**, one line per issue: number, who it's addressed to,
   how long it's waited.
6. **Not worth touching**, the remaining buckets, summarised in one or two
   lines each, name notable issues, don't enumerate all of them.

Keep it scannable. Then work through the actionable buckets one issue at a
time: show the draft, get a yes or no, act, move on. Don't dump five drafts
at once.

## Principles

- **A closed issue is a claim someone will read later.** "Feel free to
  reopen" is what makes that claim honest, always include it.
- **Verification is the whole job.** An issue triage that doesn't check the
  current code is just re-reading the issue tracker back to the maintainer.
- **Don't close on a rewrite's proximity.** Code changing in the same file or
  area is not the same as the reported symptom being gone, trace the actual
  mechanism before claiming "fixed".
- **One at a time, always.** Even when five issues look identical, get five
  separate yeses. A maintainer catching a bad call on issue two should not
  have already had three, four and five posted out from under them.
- **Don't answer for another maintainer.** A question addressed to a named
  person, or a design fork between maintainers, gets a ping, not your answer
  in their name.
- **Stop at the recommendation for "fixable now".** Drafting and posting a
  bugfix PR is a coding task, not this skill, hand off rather than starting
  to write code mid-triage.
