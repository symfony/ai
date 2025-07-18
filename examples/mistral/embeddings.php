<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Mistral\Embeddings;
use Symfony\AI\Platform\Bridge\Mistral\PlatformFactory;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('MISTRAL_API_KEY'), http_client());
$model = new Embeddings();

$response = $platform->request($model, <<<TEXT
    In the middle of the 20th century, food scientists began to understand the importance of vitamins and minerals in
    human health. They discovered that certain nutrients were essential for growth, development, and overall well-being.
    This led to the fortification of foods with vitamins and minerals, such as adding vitamin D to milk and iodine to
    salt. The goal was to prevent deficiencies and promote better health in the population.
    TEXT);

echo 'Dimensions: '.$response->asVectors()[0]->getDimensions().\PHP_EOL;
