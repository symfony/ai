<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\AiMlApi\PlatformFactory;
use Symfony\AI\Store\Document\Vectorizer;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('AIMLAPI_API_KEY'), http_client());

$vectorizer = new Vectorizer($platform, 'text-embedding-3-small');

$string = 'Hello World';
$vector = $vectorizer->vectorize($string);

printf(
    "String: %s\nVector dimensions: %d\nFirst 5 values: [%s]\n",
    $string,
    $vector->getDimensions(),
    implode(', ', array_map(fn ($val) => number_format($val, 6), array_slice($vector->getData(), 0, 5)))
);
