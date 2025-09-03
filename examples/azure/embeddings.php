<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Azure\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(
    env('AZURE_OPENAI_BASEURL'),
    env('AZURE_OPENAI_EMBEDDINGS_DEPLOYMENT'),
    env('AZURE_OPENAI_EMBEDDINGS_API_VERSION'),
    env('AZURE_OPENAI_KEY'),
    http_client(),
);
$embeddings = new Embeddings();

$result = $platform->invoke($embeddings, <<<TEXT
    Once upon a time, there was a country called Japan. It was a beautiful country with a lot of mountains and rivers.
    The people of Japan were very kind and hardworking. They loved their country very much and took care of it. The
    country was very peaceful and prosperous. The people lived happily ever after.
    TEXT);

print_vectors($result);
