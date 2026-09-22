Higgsfield Platform
===================

Higgsfield platform bridge for Symfony AI. Generates images and videos from text prompts or
references. Generation is asynchronous, so an invocation returns a `Result\JobResult` carrying the
handle of that generation, which `HiggsfieldJobClient` resolves once the media is ready.

Model Aliases
-------------

The model catalog is fetched from the Higgsfield API, so `GET /models` decides which names are
available to an account. Endpoint names are long and carry a version
(`higgsfield-ai/soul/v2/standard`), which cannot be dropped - `higgsfield-ai/soul/standard` exists
alongside it. `CuratedModelCatalog`, used by default, therefore adds a short alias that keeps the
version visible, and names the operation whenever family and version alone would not identify one:

| Alias           | Model                                            |
| --------------- | ------------------------------------------------ |
| `soul-2`        | `higgsfield-ai/soul/v2/standard`                 |
| `kling-2.5-i2v` | `kling-video/v2.5-turbo/standard/image-to-video` |
| `wan-2.7-t2v`   | `wan/v2.7/text-to-video`                         |

Aliases are a shortcut, never a restriction: every name the API reports keeps working, and passing a
`ModelCatalogInterface` to `Factory::createPlatform()` replaces them.

Higgsfield Documentation
------------------------

 * [API documentation](https://docs.higgsfield.ai/docs)
 * [Model catalog](https://console.higgsfield.ai/)

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/ai/issues) and
   [send Pull Requests](https://github.com/symfony/ai/pulls)
   in the [main Symfony AI repository](https://github.com/symfony/ai)
