<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\HuggingFace\Factory;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('HUGGINGFACE_KEY'), httpClient: http_client());

$result = $platform->invoke('Helsinki-NLP/opus-mt-ru-en', 'Меня зовут Вольфганг и я живу в Берлине');

echo $result->asText().\PHP_EOL;
