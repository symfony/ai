## Knowledge Bridge

Structured access to official documentation through MCP tools. Browse the
table of contents, read specific pages, and run substring search across
cached content — instead of guessing from training data.

This is **not** semantic / RAG search. Matches are case-insensitive substring
hits.

| Tool               | Purpose                                                                                       |
|--------------------|-----------------------------------------------------------------------------------------------|
| `knowledge-toc`    | Without arguments: list providers. With a provider: browse its table of contents at a path.   |
| `knowledge-read`   | Read a documentation page split into RST sections (chunks).                                   |
| `knowledge-search` | Case-insensitive substring search across a provider's chunks.                                 |

### How to use

1. Call `knowledge-toc` with no arguments to discover available providers (e.g. `symfony`).
2. Call `knowledge-toc` with a `provider` to browse its table of contents. Pass a `path` to drill into a subsection.
3. Use `knowledge-read` for a specific page once you have its `path`. The page comes back as ordered sections; pick the relevant one.
4. Use `knowledge-search` when you don't know where to start — it returns matching pages with snippets.

### Behavior

- The first call for a provider clones the source repository into the local Mate cache. Expect a delay on cold start.
- Subsequent calls are served from the cache.
- The cache is automatically refreshed (re-pulled and re-indexed) once it is older than the configured TTL (default: 24 hours).
- `knowledge-search` caps results at 50 regardless of the requested `limit`.
- `knowledge-read` truncates very large pages and reports `truncated: true` in the response.
