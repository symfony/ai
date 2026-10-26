CHANGELOG
=========

1.0
---

 * Add `Factory::createModelCatalog()` to build the API-based catalog on its own, e.g. to wrap it in a `CachedModelCatalog`

0.15
----

 * Add support for tool calling in chat completion results
 * Add a model class per task, e.g. `Completions`, `Embeddings` or `Reranker`, with its own result converter
 * Add `ModelCatalog` with the chat models of the inference providers and the most downloaded models of each task, generated from the Hugging Face Hub API
 * Add `ModelApiCatalog` to resolve models and the capabilities of an inference provider through the Hugging Face Hub API
 * Add `ModelMapper` to map Hub API models to their model class and capabilities
 * Add streaming, token usage and finish reasons to chat completion
 * Add `ApiClient::findModel()` and `ApiClient::listModels()`, and an optional access token to `ApiClient`
 * [BC BREAK] Remove the `Task` interface and the `task` option, the model catalog resolves the task of a model
 * [BC BREAK] Remove `ModelClient`, `ResultConverter` and `Contract\MessageBagNormalizer`
 * [BC BREAK] Remove the `fromArray()` methods of the `Output` result classes, the result converters create them
 * [BC BREAK] Change the default `$provider` of `Factory` to `null`, the provider only applies to chat completion
 * [BC BREAK] Return embeddings for sentence similarity models and remove `Output\SentenceSimilarityResult`
 * [BC BREAK] Rerank with `text-ranking` models only
 * [BC BREAK] Remove the `text-generation`, `image-to-text` and `audio-classification` tasks

0.11
----

 * Add a `baseUrl` argument to the model client and the factory to target a custom HuggingFace inference router

0.8
---

 * [BC BREAK] `HuggingFaceContract::create()` no longer accepts variadic `NormalizerInterface` arguments; pass an array instead
 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods
 * [BC Break] Change all Output class properties from public to private readonly with getter methods

0.7
---

 * Add text-ranking task support for cross-encoder reranking models

0.1
---

 * Add the bridge
