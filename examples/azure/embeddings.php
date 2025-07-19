<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Azure\OpenAI\PlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAI\Embeddings;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__).'/vendor/autoload.php';
(new Dotenv())->loadEnv(dirname(__DIR__).'/.env');

if (!isset($_SERVER['AZURE_OPENAI_BASEURL'], $_SERVER['AZURE_OPENAI_EMBEDDINGS_DEPLOYMENT'], $_SERVER['AZURE_OPENAI_EMBEDDINGS_API_VERSION'], $_SERVER['AZURE_OPENAI_KEY'])) {
    echo 'Please set the AZURE_OPENAI_BASEURL, AZURE_OPENAI_EMBEDDINGS_DEPLOYMENT, AZURE_OPENAI_EMBEDDINGS_API_VERSION, and AZURE_OPENAI_KEY environment variables.'.\PHP_EOL;
    exit(1);
}

$platform = PlatformFactory::create(
    $_SERVER['AZURE_OPENAI_BASEURL'],
    $_SERVER['AZURE_OPENAI_EMBEDDINGS_DEPLOYMENT'],
    $_SERVER['AZURE_OPENAI_EMBEDDINGS_API_VERSION'],
    $_SERVER['AZURE_OPENAI_KEY'],
);
$embeddings = new Embeddings();

$result = $platform->invoke($embeddings, <<<TEXT
    Once upon a time, there was a country called Japan. It was a beautiful country with a lot of mountains and rivers.
    The people of Japan were very kind and hardworking. They loved their country very much and took care of it. The
    country was very peaceful and prosperous. The people lived happily ever after.
    TEXT);

echo 'Dimensions: '.$result->asVectors()[0]->getDimensions().\PHP_EOL;
