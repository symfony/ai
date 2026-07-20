// Canonical schema for the Symfony AI HelixDB store bridge.
//
// Deploy this file together with queries.hx via `helix compile` + `helix deploy`
// BEFORE using the Store. See the bridge README for details.
//
// NOTE: HelixQL has no stable published grammar. The definition below reflects the
// documented HelixDB pattern (a typed vector node holding the embedding and a
// JSON-encoded metadata string) but MUST be validated with `helix compile` against
// your installed HelixDB version.

V::Document {
    doc_id: String,
    vector: [F64],
    metadata: String
}
