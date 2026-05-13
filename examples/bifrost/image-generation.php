<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Bifrost\Factory;
use Symfony\AI\Platform\Bridge\Bifrost\Image\ImageResult;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('BIFROST_API_KEY'), env('BIFROST_ENDPOINT'), http_client());

$result = $platform->invoke(
    model: 'openai/dall-e-3',
    input: 'A friendly red panda holding a Symfony logo, digital illustration.',
    options: [
        'size' => '1024x1024',
        'response_format' => 'url',
    ],
)->getResult();

assert($result instanceof ImageResult);

if (null !== $result->getRevisedPrompt()) {
    echo 'Revised prompt: '.$result->getRevisedPrompt().\PHP_EOL.\PHP_EOL;
}

foreach ($result->getContent() as $index => $image) {
    echo 'Image '.$index.': '.$image->url.\PHP_EOL;
}
