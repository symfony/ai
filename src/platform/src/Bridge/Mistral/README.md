Mistral Platform
================

Mistral platform bridge for Symfony AI.

Mistral Documentation
---------------------

 * [API reference](https://docs.mistral.ai/api/)
 * [Chat completions](https://docs.mistral.ai/api/endpoint/chat)
 * [OCR](https://docs.mistral.ai/api/endpoint/ocr)

OCR
---

The `mistral-ocr-latest` model extracts text (as markdown), layout images and
per-page annotations from a document or image. Pass a ``DocumentUrl``,
``Document`` (binary PDF) or ``ImageUrl`` to ``invoke()``:

.. code-block:: php

    use Symfony\AI\Platform\Bridge\Mistral\Factory;
    use Symfony\AI\Platform\Bridge\Mistral\Ocr\Result\OcrResult;
    use Symfony\AI\Platform\Message\Content\DocumentUrl;

    $platform = Factory::createPlatform($apiKey);

    $result = $platform->invoke('mistral-ocr-latest', new DocumentUrl('https://example.com/document.pdf'));

    $ocr = $result->asObject();
    \assert($ocr instanceof OcrResult);

    echo $ocr->getMarkdown();

Test Fixtures
-------------

The test fixtures in `Tests/Fixtures/` contain binary media content with the following owners and licenses:

 * `document.pdf`: Chem8240ja, Public Domain, see [Wikipedia](https://en.m.wikipedia.org/wiki/File:Re_example.pdf)

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/ai/issues) and
   [send Pull Requests](https://github.com/symfony/ai/pulls)
   in the [main Symfony AI repository](https://github.com/symfony/ai)
