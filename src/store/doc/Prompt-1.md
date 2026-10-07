Implement PDF loading for the Symfony AI Store component, with reusable local-path-to-public-URL mapping.

Workspaces:

- Symfony AI monorepo: `/home/cqcj/NetBeansProjects/ai`
- Running demo: `/home/cqcj/NetBeansProjects/ai-demo`
- Store package: `ai/src/store`
- Existing PDF fixtures: `ai/src/store/tests/Fixtures/pdf`

Read and follow applicable AGENTS.md instructions. Preserve existing user changes. Keep library code and package tests in the monorepo; use ai-demo for integration testing. Use Symfony Search ( https://symfony.com/search?q= ) to look for and use existing Symfony components before writing new common routines from scratch. 

**1. PDF loader**

Add `Symfony\AI\Store\Document\Loader\PdfLoader`, implementing the existing `LoaderInterface`.

Use `smalot/pdfparser`. Review its current documentation and the existing TextFileLoader, RssFeedLoader, and DirectoryLoader before implementing.

- Accept one local file path through `load()`.
- Reject missing sources, directories, unreadable files, and remote URLs with appropriate Store exceptions.
- Leave directory traversal to DirectoryLoader; do not add wildcard expansion or HTTP fetching.
- Support optional injection of a Smalot Parser, with a usable default.
- Follow the optional-dependency convention: give a clear Composer installation message if the parser package is unavailable. Add the dependency to the package’s development dependencies and document installation for consumers.
- Parse once and yield one TextDocument per nonempty page, in document order.
- Trim surrounding whitespace without flattening internal paragraphs or attempting layout reconstruction.
- Skip empty pages without renumbering subsequent pages.
- If no page contains extractable text, throw a clear Store RuntimeException. Do not create a document containing only metadata.
- Wrap parser failures in a Store RuntimeException containing the source path and preserving the original exception.
- Do not claim that yielding pages makes the underlying parser memory-streaming.

Use stable UUIDv5 page IDs derived from a normalized absolute local path and the original 1-based page number. URL mapping must not change document IDs. Document that moving a file changes its identity and that existing chunk IDs prevent guaranteed idempotent re-indexing.

**2. Metadata contract**

Each page document should include:

- `Metadata::KEY_SOURCE`: the local source path supplied to the loader.
- `Metadata::KEY_TITLE`: embedded title when usable; otherwise the filename.
- `page_number`: original 1-based page number.
- `page_count`: total physical page count, including empty pages.
- Optional `pdf_author`, `pdf_subject`, `pdf_keywords`, `pdf_creation_date`, and `pdf_modification_date`.

Use a documented allowlist and stable scalar types. Omit unavailable values. Define and test normalization for PDF properties and relevant XMP alternatives; do not cast arrays to strings or dump arbitrary nested parser metadata into the store. Keep PDF dates as source strings initially and do not describe them as publication dates.

Create independent Metadata instances for each page. Do not use `_parent_id` for the PDF identity; the splitter already uses it.

Keep metadata separate from extracted text. Do not prepend URLs, local paths, or metadata headers in this first implementation.

**3. Generic URL transformer**

Add `SourceUrlTransformer` under the Store document transformers.

Constructor configuration:

- `pathPrefix`: absolute local directory prefix.
- `urlPrefix`: absolute HTTP or HTTPS URL prefix.

Read the document’s `_source` metadata and, when it lies beneath the configured directory, set `source_url` to the URL prefix plus its relative path.

Requirements:

- Preserve `_source`, document ID, content, and unrelated metadata.
- Match directory boundaries: `/files` must not match `/files-other`.
- Normalize filesystem paths consistently and do not map paths outside the configured prefix.
- Encode relative path segments correctly, including spaces, Unicode, `#`, and `?`, while preserving directory separators.
- Define trailing-slash handling.
- Reject URL prefixes containing query strings or fragments.
- Leave unmatched sources, missing sources, and already-remote sources unchanged.
- Preserve an existing `source_url`.
- Return a new document with copied metadata when adding the URL.
- Keep this transformer independent of PDFs and page numbers.

Document that DirectoryLoader currently supplies resolved file paths, which affects prefix selection for symlinked directories.

**4. Demo integration**

Configure ai-demo to use a Composer path repository pointing to `../ai/src/store`, with symlinking enabled.

Inspect installed package constraints before choosing the local development version or alias. Resolve only necessary dependencies and verify that the installed Store package resolves to the local checkout. Install smalot/pdfparser in the demo too; package development dependencies are not installed transitively.

Add a separate PDF indexing configuration using:

1. DirectoryLoader with PdfLoader registered for `pdf`.
2. SourceUrlTransformer.
3. TextSplitTransformer.
4. TextTrimTransformer.

Follow the existing service and indexer configuration conventions in `config/packages/ai.yaml`. Use a separate test collection/index so PDF testing does not alter the existing blog data.

Demonstrate that retrieved chunks retain text, title, public URL, and page number. Verify the installed similarity-search tool exposes that metadata to the model. A citation may use `source_url` with `#page=N`.

**5. Tests and scope**

Use real PDF fixtures for extraction tests and small controlled fixtures where exact metadata or edge cases are needed.

Cover:

- Single-page and multipage extraction.
- Page ordering, numbering, and page count.
- Optional metadata and title fallback.
- Stable page IDs.
- Empty pages and wholly empty extraction.
- Missing, unreadable, malformed, and unsupported encrypted files.
- URL mapping boundaries, encoding, existing URLs, and nonmatching sources.
- Metadata survival through splitting.
- DirectoryLoader delegation.

Treat tables, landscape pages, forms, and image-heavy PDFs as extraction smoke tests with limited assertions. Do not promise table reconstruction, form-field values, OCR, or image understanding.

The fixture README currently lists descriptions, their source, and redistribution terms. They may be included in the

Do not add OCR, alternate extraction backends, image indexing, layout analysis, automatic stale-chunk cleanup, or changes to TextSplitTransformer.

**6. Documentation and validation**

Document installation, page-based output, metadata, URL mapping, empty-extraction behavior, and parser limitations in the Store component documentation. Add an entry under the upcoming Store changelog version.

Keep package code and tests suitable for the subtree split. Do not depend on the monorepo root or demo at runtime.

Run the Store tests and static analysis, PHP-CS-Fixer from the repository root, and `./doctor-rst` after changing RST documentation. Run appropriate demo configuration and integration checks.

Report changed files, validation results, extraction limitations observed in the supplied PDFs, and any blockers. Do not commit, push, or open a PR.