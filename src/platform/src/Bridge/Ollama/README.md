Ollama Platform
===============

Ollama platform bridge for Symfony AI.

Decision models
---------------

Models declaring the `decision` capability (Ollama >= 0.35.0) are served through `POST /v1/systemone`.
This requires `symfony/ai-decision-platform`.
They are queried with the `Evaluation` of the `Decision` bridge and answer with its `Answers`.

Clef and Clef Flash accept images on Ollama >= 0.35.1. Pass platform `Image` objects in
the optional `images` argument of `Evaluation`. PNG, JPEG, and WebP images are sent as
raw base64 data, in order, and shared by all questions. The state remains required.
For [Ollama cloud requests](https://docs.ollama.com/capabilities/decision#use-a-cloud-model),
the documented limits are at most **4 images**, **4 MiB and 16 megapixels per image**,
and **8 MiB in total**. Ollama's server enforces image formats and request limits;
this bridge does not inspect image bytes or enforce those cloud limits locally.

The model catalog maps `vision` to `Capability::INPUT_IMAGE` for decision models.
Images sent to a decision model without that capability are rejected by the bridge
before the decision request is sent. Custom model catalogs must declare
`Capability::INPUT_IMAGE` for image-capable decision models.

```php
use Symfony\AI\Platform\Bridge\Decision\Evaluation;
use Symfony\AI\Platform\Bridge\Decision\Question\NoulQuestion;
use Symfony\AI\Platform\Message\Content\Image;

$result = $platform->invoke('clef-flash', new Evaluation(
    'A scanned yearbook photograph.',
    ['portrait' => new NoulQuestion('Is this a posed individual portrait?')],
    images: [Image::fromFile('/path/to/photo.jpg')],
));
```

Image URLs must be fetched by the application and converted to `Image` objects; the
decision endpoint does not accept URLs or data URLs.

Ollama Documentation
--------------------

 * [API reference (chat, embed, NDJSON streaming)](https://github.com/ollama/ollama/blob/main/docs/api.md)
 * [Modelfile / capabilities](https://github.com/ollama/ollama/blob/main/docs/modelfile.mdx)
 * [Vision (image input)](https://docs.ollama.com/capabilities/vision)
 * [Decision models](https://docs.ollama.com/capabilities/decision)

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/ai/issues) and
   [send Pull Requests](https://github.com/symfony/ai/pulls)
   in the [main Symfony AI repository](https://github.com/symfony/ai)
