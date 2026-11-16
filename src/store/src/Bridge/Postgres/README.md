PostgreSQL pgvector Store
=========================

Provides [PostgreSQL with pgvector](https://github.com/pgvector/pgvector) vector store integration for Symfony AI Store.

`Store::setup()` creates the table with a stored generated `search_tsv` column:

```sql
to_tsvector('<lang>', COALESCE(metadata->>'_text', ''))
```

It also creates the vector index and a GIN index on `"search_tsv"`. `TextQuery`
and keyword-only hybrid filtering (`semanticRatio = 0.0`) use that column.
Default mixed hybrid ranking still scores the whole candidate set; the stored
column avoids rebuilding the text vector on every search, while the GIN index
accelerates lexical matching.

Writes keep inserting only `id`, `metadata`, and the vector field. PostgreSQL
computes `search_tsv` automatically. The vector field name cannot be
`search_tsv`.

Existing stores must run `setup()` or an equivalent schema upgrade before
text or hybrid queries against this version. Queries now read `"search_tsv"`;
tables without that column fail at query time.

Existing tables created before this change get the column through:

```sql
ALTER TABLE "<table>"
ADD COLUMN IF NOT EXISTS "search_tsv" tsvector
GENERATED ALWAYS AS (to_tsvector('<lang>', COALESCE(metadata->>'_text', ''))) STORED;
```

That backfills existing rows and takes an `ACCESS EXCLUSIVE` lock. Concurrent
replacement of live tables is not performed by `setup()`.

`ADD COLUMN IF NOT EXISTS` keeps an existing `search_tsv` column unchanged,
including its generation language. Changing language later needs an explicit
rebuild of that column and its GIN index; it is not automated by `setup()`.

The GIN index uses `<table>_search_tsv_gin` when that fits PostgreSQL's 63-byte
identifier limit. Longer table names use a deterministic SHA-256 prefix of the
full table name plus `_search_tsv_gin`. That name is distinct from the earlier
expression-index name (`*_text_gin`), so `CREATE INDEX IF NOT EXISTS` cannot
silently keep an old expression index under the new name.

A prior expression GIN (`*_text_gin`) is retained after upgrade. Remove it
separately once the column-backed index is in place if you want to avoid
duplicate index maintenance and write costs.

pgvector Documentation
----------------------

 * [pgvector (operators, indexes, vector/halfvec types)](https://github.com/pgvector/pgvector)
 * [PostgreSQL full-text search](https://www.postgresql.org/docs/current/textsearch.html)
 * [Stored generated text-search columns](https://www.postgresql.org/docs/current/textsearch-tables.html#TEXTSEARCH-TABLES-INDEX)
 * [Preferred full-text index types (GIN)](https://www.postgresql.org/docs/current/textsearch-indexes.html)

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/ai/issues) and
   [send Pull Requests](https://github.com/symfony/ai/pulls)
   in the [main Symfony AI repository](https://github.com/symfony/ai)
