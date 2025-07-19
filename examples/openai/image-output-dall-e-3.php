<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenAI\DallE;
use Symfony\AI\Platform\Bridge\OpenAI\DallE\ImageResult;
use Symfony\AI\Platform\Bridge\OpenAI\PlatformFactory;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__).'/vendor/autoload.php';
(new Dotenv())->loadEnv(dirname(__DIR__).'/.env');

if (!isset($_SERVER['OPENAI_API_KEY'])) {
    echo 'Please set the OPENAI_API_KEY environment variable.'.\PHP_EOL;
    exit(1);
}

$platform = PlatformFactory::create($_SERVER['OPENAI_API_KEY']);

$result = $platform->invoke(
    model: new DallE(name: DallE::DALL_E_3),
    input: 'A cartoon-style elephant with a long trunk and large ears.',
    options: [
        'response_format' => 'url', // Generate response as URL
    ],
)->getResult();

assert($result instanceof ImageResult);

echo 'Revised Prompt: '.$result->revisedPrompt.\PHP_EOL.\PHP_EOL;

foreach ($result->getContent() as $index => $image) {
    echo 'Image '.$index.': '.$image->url.\PHP_EOL;
}
