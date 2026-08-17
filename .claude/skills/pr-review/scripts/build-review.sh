#!/usr/bin/env bash
#
# Build a GitHub review payload with validated inline-comment anchors.
#
# Every anchor is checked against the PR's actual diff hunks before the payload
# is emitted, so a bad line number fails here with a readable message instead of
# as a 422 from the API.
#
# Usage:
#   build-review.sh --pr 2368 --body body.md [--event COMMENT] \
#     --comment 'src/path/File.php:28-33:c1.md' \
#     --comment 'src/path/File.php:67:c2.md'
#
# Writes the payload to stdout. Post it with:
#   gh api repos/<owner>/<repo>/pulls/<N>/reviews --method POST --input review.json
#
set -euo pipefail

PR=""
BODY_FILE=""
EVENT="COMMENT"
REPO=""
declare -a ANCHORS=()

die() { echo "error: $*" >&2; exit 1; }

while [[ $# -gt 0 ]]; do
    case "$1" in
        --pr)      PR="$2"; shift 2 ;;
        --body)    BODY_FILE="$2"; shift 2 ;;
        --event)   EVENT="$2"; shift 2 ;;
        --repo)    REPO="$2"; shift 2 ;;
        --comment) ANCHORS+=("$2"); shift 2 ;;
        -h|--help) sed -n '2,18p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *)         die "unknown argument: $1" ;;
    esac
done

[[ -n "$PR" ]] || die "--pr is required"
[[ -n "$BODY_FILE" ]] || die "--body is required"
[[ -f "$BODY_FILE" ]] || die "body file not found: $BODY_FILE"

case "$EVENT" in
    COMMENT|REQUEST_CHANGES|APPROVE) ;;
    *) die "--event must be COMMENT, REQUEST_CHANGES or APPROVE (got: $EVENT)" ;;
esac

[[ -n "$REPO" ]] || REPO=$(gh repo view --json nameWithOwner --jq .nameWithOwner)

SHA=$(gh pr view "$PR" --repo "$REPO" --json headRefOid --jq .headRefOid)
[[ -n "$SHA" ]] || die "could not resolve head SHA for PR #$PR"

FILES_JSON=$(mktemp)
trap 'rm -f "$FILES_JSON"' EXIT
gh api "repos/$REPO/pulls/$PR/files" --paginate > "$FILES_JSON"

# Emit "start end" for every hunk of $1 in the file's post-image coordinates.
hunk_ranges() {
    local path="$1" patch
    patch=$(jq -r --arg p "$path" '.[] | select(.filename==$p) | .patch // ""' "$FILES_JSON")
    [[ -n "$patch" ]] || return 1
    # @@ -oldStart,oldLen +newStart,newLen @@   (lengths are optional, default 1)
    printf '%s\n' "$patch" | grep -oE '^@@ -[0-9]+(,[0-9]+)? \+[0-9]+(,[0-9]+)?' | while read -r hunk; do
        local new start len
        new=${hunk##*+}
        start=${new%%,*}
        if [[ "$new" == *,* ]]; then len=${new##*,}; else len=1; fi
        echo "$start $((start + len - 1))"
    done
}

COMMENTS_JSON="[]"

for anchor in "${ANCHORS[@]}"; do
    # path:range:file — the path may not contain ':', the range is N or N-M
    IFS=':' read -r path range cfile <<< "$anchor"
    [[ -n "$path" && -n "$range" && -n "$cfile" ]] \
        || die "malformed --comment (want path:line[-line]:file): $anchor"
    [[ -f "$cfile" ]] || die "comment file not found: $cfile"

    if [[ "$range" == *-* ]]; then
        start=${range%%-*}
        end=${range##*-}
    else
        start="$range"
        end="$range"
    fi
    [[ "$start" =~ ^[0-9]+$ && "$end" =~ ^[0-9]+$ ]] || die "non-numeric range: $range"
    (( start <= end )) || die "inverted range: $range"

    ranges=$(hunk_ranges "$path") || die "file is not part of PR #$PR's diff: $path
  (asks about untouched files belong in the review body)"

    ok=0
    while read -r hs he; do
        if (( start >= hs && end <= he )); then ok=1; break; fi
    done <<< "$ranges"

    if (( ok == 0 )); then
        die "anchor $path:$range is not inside a single diff hunk.
  valid ranges for this file:
$(echo "$ranges" | sed 's/^/    /')"
    fi

    if (( start == end )); then
        entry=$(jq -n --arg path "$path" --argjson line "$start" --rawfile body "$cfile" \
            '{path:$path, line:$line, side:"RIGHT", body:$body}')
    else
        entry=$(jq -n --arg path "$path" --argjson sl "$start" --argjson line "$end" --rawfile body "$cfile" \
            '{path:$path, start_line:$sl, start_side:"RIGHT", line:$line, side:"RIGHT", body:$body}')
    fi
    COMMENTS_JSON=$(jq -n --argjson acc "$COMMENTS_JSON" --argjson e "$entry" '$acc + [$e]')

    echo "ok  $path:$range  <- $cfile" >&2
done

echo "sha $SHA  event $EVENT  comments ${#ANCHORS[@]}" >&2

jq -n \
    --arg sha "$SHA" \
    --arg event "$EVENT" \
    --rawfile body "$BODY_FILE" \
    --argjson comments "$COMMENTS_JSON" \
    '{commit_id:$sha, event:$event, body:$body, comments:$comments}'
