Cohere Platform
===============

Cohere platform bridge for Symfony AI.

Cohere Documentation
--------------------

 * [Chat](https://docs.cohere.com/reference/chat)
 * [Embed](https://docs.cohere.com/reference/embed)
 * [Rerank](https://docs.cohere.com/reference/rerank)
 * [Audio transcription](https://docs.cohere.com/reference/create-audio-transcription)

Embeddings
----------

Cohere embeds documents and queries differently. The `input_type` option defaults to
`search_document`. Set it to `search_query` for the model that embeds search queries,
for example `embed-v4.0?input_type=search_query`. Accepted values are listed in the
`InputType` enum.

With the AI bundle, configure one vectorizer for indexing and one for the retriever:

```yaml
ai:
    vectorizer:
        documents:
            platform: 'ai.platform.cohere'
            model: 'embed-v4.0'
        queries:
            platform: 'ai.platform.cohere'
            model: 'embed-v4.0?input_type=search_query'
    retriever:
        default:
            vectorizer: 'ai.vectorizer.queries'
            store: 'ai.store.memory.default'
```

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/ai/issues) and
   [send Pull Requests](https://github.com/symfony/ai/pulls)
   in the [main Symfony AI repository](https://github.com/symfony/ai)
