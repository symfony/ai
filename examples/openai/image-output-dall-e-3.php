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
use Symfony\AI\Platform\Bridge\OpenAI\DallE\ImageResponse;
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
    model: new DallE(name: DallE::DALL_E_3),
    input: 'A cartoon-style elephant with a long trunk and large ears.',
    options: [
        'response_format' => 'url', // Generate response as URL
    ],
);

assert($response instanceof ImageResponse);

echo 'Revised Prompt: '.$response->revisedPrompt.\PHP_EOL.\PHP_EOL;

foreach ($response->getContent() as $index => $image) {
    echo 'Image '.$index.': '.$image->url.\PHP_EOL;
}
