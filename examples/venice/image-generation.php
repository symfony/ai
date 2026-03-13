<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Venice\PlatformFactory;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('VENICE_API_KEY'), httpClient: http_client());

try {
    $result = $platform->invoke('venice-uncensored', 'A beautiful sunset over a mountain range');

    echo $result->asText().\PHP_EOL;
} catch (InvalidArgumentException $e) {
    echo $e->getMessage()."\nMaybe use a different model?\n";
}
