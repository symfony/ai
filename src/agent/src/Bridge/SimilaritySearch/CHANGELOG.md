CHANGELOG
=========

1.0
---

 * Add `SimilaritySearchResult`, returned by the tool and passed to the model as a JSON list of the documents with their id
 * Add the retrieved documents as sources of the tool, exposed in the agent result with `includeSources`
 * Add `DocumentSource`, giving access to the retrieved document with `getDocument()`
 * [BC BREAK] Remove `SimilaritySearch::getUsedDocuments()`, use `DocumentSource::getDocument()` of the sources instead
 * [BC BREAK] Remove the `$promptTemplate` argument of `SimilaritySearch`, register a normalizer for `SimilaritySearchResult` to change what the model gets

0.1
---

 * Add the bridge
