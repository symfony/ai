Higgsfield Platform
===================

Higgsfield platform bridge for Symfony AI.

Higgsfield is an AI-native creative suite for generating images and videos from text prompts or
references. The bridge submits a generation request, polls the Higgsfield API until the media is
ready and returns the result as a `BinaryResult`.

Usage
-----

```php
use Symfony\AI\Platform\Bridge\Higgsfield\Factory;
use Symfony\AI\Platform\Message\Content\Text;

$platform = Factory::createPlatform(
    apiKey: 'YOUR_KEY_ID',
    apiSecret: 'YOUR_KEY_SECRET',
);

$result = $platform->invoke('flux-pro/kontext/max/text-to-image', new Text('A cat on a kitchen table'), [
    'aspect_ratio' => '9:16',
]);

$result->asFile(__DIR__.'/cat.png');
```

The model name maps directly to a Higgsfield generation endpoint. Any extra input parameters are
passed as the third `invoke()` argument.

Higgsfield Documentation
------------------------

 * [Higgsfield Cloud](https://cloud.higgsfield.ai/)

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/ai/issues) and
   [send Pull Requests](https://github.com/symfony/ai/pulls)
   in the [main Symfony AI repository](https://github.com/symfony/ai)
