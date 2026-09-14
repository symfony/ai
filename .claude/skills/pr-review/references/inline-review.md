# Posting an inline review

Mechanics for turning drafted comments into a GitHub review with correctly
anchored inline comments. This is the fiddly part, get the anchors wrong and
the API answers `422 Unprocessable Entity` with little explanation.

## The anchoring rules

A review comment anchors to a **line number in the file as it exists at the
head commit**, on the `RIGHT` side of the diff, and that line **must fall
inside one of the PR's diff hunks**. Consequences:

- Line numbers from your local checkout are only valid if you are exactly at
  the PR head. Read them from the head SHA instead.
- You cannot anchor to a file the PR does not touch. Those asks go in the
  review **body**.
- You cannot anchor to an unchanged line far from any hunk, even in a changed
  file.

## 1. Resolve the head SHA

```bash
gh pr view <N> --json headRefOid --jq .headRefOid
```

Use this same SHA as `commit_id` in the payload. If the author pushes between
drafting and posting, re-resolve and re-check the anchors, line numbers move.

## 2. Read line numbers at that SHA

```bash
gh api "repos/<owner>/<repo>/contents/<path>?ref=<sha>" --jq '.content' \
  | base64 -d | grep -n "" | sed -n '<from>,<to>p'
```

Quote the URL, it contains `?`, and zsh will otherwise try to glob it and
fail with "no matches found".

`grep -n ""` numbers every line including blanks, which is what you want;
`cat -n` and `nl` skip or renumber blank lines in some configurations.

## 3. Confirm the anchor is inside a hunk

```bash
gh api repos/<owner>/<repo>/pulls/<N>/files --jq '.[] | {filename, patch}'
```

Each hunk header reads `@@ -<oldStart>,<oldLen> +<newStart>,<newLen> @@`. The
valid anchor range for that hunk is `newStart` to `newStart + newLen - 1`.
A multi-line comment must have **both** `start_line` and `line` inside the
*same* hunk.

`scripts/build-review.sh` does this check for you and refuses to emit a
payload with a bad anchor.

## 4. Assemble the payload

Always build the JSON with `jq --rawfile` from markdown files on disk. Comment
bodies contain backticks, newlines and fenced code, hand-escaping them into a
JSON string is how suggestion blocks get mangled.

```bash
jq -n \
  --rawfile body body.md \
  --rawfile c1 c1.md \
  '{
    commit_id: "<sha>",
    event: "COMMENT",
    body: $body,
    comments: [
      {path:"<path>", start_line:28, start_side:"RIGHT", line:33, side:"RIGHT", body:$c1}
    ]
  }' > review.json
```

Single-line comments take `line` and `side` only, omit `start_line` and
`start_side` entirely rather than setting them equal to `line`.

`event` is one of `COMMENT`, `REQUEST_CHANGES`, `APPROVE`. Omitting it creates
a *pending* review that is not visible until submitted separately, always set
it explicitly.

## 5. Suggestion blocks

A ` ```suggestion ` block replaces exactly the lines the comment is anchored
to. So the anchor range and the replacement must correspond:

- Anchoring to lines 28–33 means the block must contain the full replacement
  for those six lines.
- **Indentation is literal.** Include the leading spaces exactly as they
  appear in the file; the suggestion is applied verbatim.
- If the replacement contains a fenced code block itself, open the suggestion
  fence with four backticks.

## 6. Show the draft, then post

Never post before the maintainer has seen the body and every inline comment in
full, and said yes.

```bash
gh api repos/<owner>/<repo>/pulls/<N>/reviews --method POST --input review.json \
  --jq '{id, state, html_url, user: .user.login}'
```

## 7. Verify

```bash
gh api repos/<owner>/<repo>/pulls/<N>/comments \
  --jq '.[] | select(.pull_request_review_id==<id>) | "\(.path):\(.start_line)-\(.line)"'
```

A comment whose anchor GitHub could not place is silently relocated to the top
of the file, so confirm rather than assume.

## Common 422 causes

| Symptom | Cause |
|---|---|
| `line must be part of the diff` | Anchor outside every hunk, or file not in the diff |
| `start_line must precede line` | Range inverted |
| `commit_id is not a valid commit` | Stale SHA, author pushed since you drafted |
| Comment lands at line 1 | Anchor was unplaceable and got relocated |
| Suggestion renders as plain text | Body was hand-escaped into JSON and lost its newlines |
