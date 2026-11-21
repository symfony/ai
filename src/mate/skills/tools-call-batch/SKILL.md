---
name: tools-call-batch
description: Run several Mate tools and resource reads as one command call, and chain them so a later call uses a value from an earlier result (for example the token of the latest failed request). Reach for it when one investigation needs several results together, since each round trip costs about the same regardless of response size. Copy a ready JSON chain from references/recipes.md and change only the filter values. Not for a single tool call.
---

# tools:call-batch

`tools:call-batch --json='[...]'` runs several tools and resource reads in one command call. A round trip back to Mate costs about the same however small the answer is, because the whole conversation is resent every turn. One batch replaces N separate calls.

## Start from a recipe

Do not write a chain from scratch. Open `references/recipes.md` next to this file, copy the recipe that matches your question and change the filter values (status code, URL, search term). The paths inside the recipes are already correct.

`references/result-shapes.md` lists what each tool and resource returns, in case you need a field the recipes do not use.

## The four building blocks

```json
[
  {"id": "list", "tool": "symfony-profiler-list", "params": {"statusCode": 500, "limit": 1}, "output": false},
  {"id": "p", "tool": "symfony-profiler-get", "params": {"token": "{{list.profiles[0].token}}"}, "pick": ["url", "time_formatted"]},
  {"tool": "monolog-search", "params": {"term": "{{p.url}}", "limit": 5}, "pick": ["entries[*].message"]}
]
```

- **`id`** names a call so later calls can read its result.
- **`{{id.path}}`** inside `params` or a `resource` URI is replaced by a value from the earlier call `id`. Write only the path inside the result. The `untrusted_data` wrapper is skipped for you. A reference may only point to an earlier call.
- **`output: false`** hides a step you only need for its value.
- **`pick`** keeps only the listed paths of the result. Use it on large results: profile details, log searches, collectors.

Paths: `field.field` for fields, `[0]` for one list element, `[*]` for all elements, `[:5]` for the first five. A `{{ }}` around a whole value keeps its type, so a number stays a number.

## Reading the answer

One entry per call, in order. `ok: false` carries an `error`. When a reference cannot be resolved, that call does not run and the error names the path, says why (for example "profiles is empty") and lists the fields that exist there. Fix the path from that list. Calls that depend on it fail with "Depends on failed call". Paths that `pick` could not find are listed in `missing_paths`.

## Limits

- Calls run one after the other, not in parallel.
- One failing call does not stop the others. The exit code is non-zero when any call failed.
- Nothing checks whether a tool only reads. Batch only tools you would also call one by one.
- A call is exactly one of `tool` or `resource`. A resource read takes no `params`.
