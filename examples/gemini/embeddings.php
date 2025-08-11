<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Gemini\Embeddings;
use Symfony\AI\Platform\Bridge\Gemini\PlatformFactory;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('GEMINI_API_KEY'), http_client());
$embeddings = Embeddings::create();

$result = $platform->invoke($embeddings, <<<TEXT
    Once upon a time, there was a country called Japan. It was a beautiful country with a lot of mountains and rivers.
    The people of Japan were very kind and hardworking. They loved their country very much and took care of it. The
    country was very peaceful and prosperous. The people lived happily ever after.
    TEXT);

echo 'Dimensions: '.$result->asVectors()[0]->getDimensions().\PHP_EOL;
