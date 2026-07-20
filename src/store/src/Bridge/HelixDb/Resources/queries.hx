// Canonical named queries for the Symfony AI HelixDB store bridge.
//
// Each QUERY becomes an HTTP endpoint (POST /<queryName>) once compiled and deployed.
// The Store class calls them by these exact names — do not rename them.
//
// NOTE: HelixQL has no stable published grammar. Parameter declarations, the
// AddV/SearchV step syntax and the RETURN results naming are version-sensitive and
// MUST be validated with `helix compile` against your installed HelixDB version.

// Insert a document. Endpoint: POST /addDocument
QUERY addDocument(doc_id: String, vector: [F64], metadata: String) =>
    document <- AddV<Document>({ doc_id: doc_id, vector: vector, metadata: metadata })
    RETURN document

// Vector similarity search. Endpoint: POST /searchDocuments
// The Store reads the matched documents from the "documents" result key.
QUERY searchDocuments(query_vector: [F64], k: I64) =>
    documents <- SearchV<Document>(query_vector, k)
    RETURN documents

// Delete a document by its identifier. Endpoint: POST /removeDocument
QUERY removeDocument(doc_id: String) =>
    target <- V<Document>::WHERE(_::{doc_id}::EQ(doc_id))
    DROP target
    RETURN "removed"

// Delete every document. Endpoint: POST /dropDocuments
QUERY dropDocuments() =>
    target <- V<Document>
    DROP target
    RETURN "dropped"
