<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Ollama\PlatformFactory;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OLLAMA_HOST_URL'), httpClient: http_client());

$result = $platform->invoke(env('OLLAMA_LLM'), 'Tina has one brother and one sister. How many sisters do Tina\'s siblings have?');

echo $result->asText().\PHP_EOL;
