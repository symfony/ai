<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenAi\Embeddings;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$ada = new Embeddings(Embeddings::TEXT_ADA_002);
$small = new Embeddings(Embeddings::TEXT_3_SMALL);
$large = new Embeddings(Embeddings::TEXT_3_LARGE);

echo 'Initiating parallel embeddings calls to platform ...'.\PHP_EOL;
$results = [];
foreach (['ADA' => $ada, 'Small' => $small, 'Large' => $large] as $name => $model) {
    echo ' - Request for model '.$name.' initiated.'.\PHP_EOL;
    $results[] = $platform->invoke($model, 'Hello, world!');
}

echo 'Waiting for the responses ...'.\PHP_EOL;
foreach ($results as $result) {
    print_vectors($result);
}
