CREATE EXTENSION IF NOT EXISTS vector;

CREATE ROLE authenticator NOINHERIT LOGIN PASSWORD 'postgres';
CREATE ROLE anon NOLOGIN;

GRANT anon TO authenticator;
GRANT USAGE ON SCHEMA public TO anon;

CREATE TABLE documents (
    id TEXT PRIMARY KEY,
    embedding vector(3),
    metadata JSONB DEFAULT '{}'::JSONB
);

GRANT ALL ON documents TO anon;

CREATE OR REPLACE FUNCTION match_documents(
    query_embedding vector(3),
    match_count INT DEFAULT 10,
    match_threshold FLOAT DEFAULT 0.0
)
RETURNS TABLE (
    id TEXT,
    embedding vector(3),
    metadata JSONB,
    score FLOAT
)
LANGUAGE plpgsql
AS $$
BEGIN
    RETURN QUERY
    SELECT
        d.id,
        d.embedding,
        d.metadata,
        (1 - (d.embedding <=> query_embedding))::FLOAT AS score
    FROM documents d
    WHERE (1 - (d.embedding <=> query_embedding)) >= match_threshold
    ORDER BY d.embedding <=> query_embedding
    LIMIT match_count;
END;
$$;

GRANT EXECUTE ON FUNCTION match_documents(vector(3), INT, FLOAT) TO anon;
