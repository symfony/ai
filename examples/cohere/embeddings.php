<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Cohere\Factory;
use Symfony\AI\Platform\Bridge\Cohere\InputType;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('COHERE_API_KEY'), http_client());

$result = $platform->invoke('embed-english-v3.0', <<<TEXT
    Once upon a time, there was a country called Japan. It was a beautiful country with a lot of mountains and rivers.
    The people of Japan were very kind and hardworking. They loved their country very much and took care of it. The
    country was very peaceful and prosperous. The people lived happily ever after.
    TEXT, [
    'input_type' => InputType::SearchDocument,
]);

print_vectors($result);

print_token_usage($result->getMetadata()->get('token_usage'));
