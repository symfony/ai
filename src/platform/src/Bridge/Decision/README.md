Decision Platform
=================

Decision platform bridge for Symfony AI.

It holds what providers of decision models share: the `Evaluation` input, the question and answer
types, and the `AnswersConverter` turning a response body into answers. It does not talk to any
provider on its own. Bridges like TypeSafe and Ollama build on it.

`Evaluation` accepts an optional `images` list of platform `Image` objects, shared by
all questions. Empty lists are omitted from the payload. The provider must support
image decisions: Ollama supports Clef images, while TypeSafe rejects image inputs.

Decision Documentation
----------------------

 * [TypeSafe System One](https://docs.typesafe.ai/concepts/system-one)
 * [TypeSafe Questions](https://docs.typesafe.ai/primitives)

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/ai/issues) and
   [send Pull Requests](https://github.com/symfony/ai/pulls)
   in the [main Symfony AI repository](https://github.com/symfony/ai)
