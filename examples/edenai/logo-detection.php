<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\EdenAi\Factory;
use Symfony\AI\Platform\Bridge\EdenAi\ImageAnalysis\Result\ImageAnalysisResult;
use Symfony\AI\Platform\Bridge\EdenAi\ModelApiCatalog;
use Symfony\AI\Platform\Message\Content\ImageUrl;

require_once dirname(__DIR__).'/bootstrap.php';

$httpClient = http_client();

// ModelApiCatalog resolves any model Eden AI serves, without curating it by hand.
$platform = Factory::createPlatform(env('EDENAI_API_KEY'), $httpClient, new ModelApiCatalog($httpClient));

$result = $platform->invoke('image/logo_detection/google', new ImageUrl('https://raw.githubusercontent.com/symfony/ai/main/fixtures/accordion.jpg'));

$analysis = $result->asObject();
assert($analysis instanceof ImageAnalysisResult);

foreach ($analysis->getItems() as $item) {
    printf('%s (%d%%)'.\PHP_EOL, $item['description'] ?? '?', (int) (100 * ($item['score'] ?? 0)));
}

printf('Provider: %s, cost: %s'.\PHP_EOL, $analysis->getProvider() ?? '?', $analysis->getCost() ?? '?');
