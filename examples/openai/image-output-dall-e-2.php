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
use Symfony\AI\Platform\Bridge\OpenAI\PlatformFactory;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__).'/vendor/autoload.php';
(new Dotenv())->loadEnv(dirname(__DIR__).'/.env');

if (!$_ENV['OPENAI_API_KEY']) {
    echo 'Please set the OPENAI_API_KEY environment variable.'.\PHP_EOL;
    exit(1);
}

$platform = PlatformFactory::create($_ENV['OPENAI_API_KEY']);

$response = $platform->request(
    model: new DallE(), // Utilize Dall-E 2 version in default
    input: 'A cartoon-style elephant with a long trunk and large ears.',
    options: [
        'response_format' => 'url', // Generate response as URL
        'n' => 2, // Generate multiple images for example
    ],
);

foreach ($response->getContent() as $index => $image) {
    echo 'Image '.$index.': '.$image->url.\PHP_EOL;
}
