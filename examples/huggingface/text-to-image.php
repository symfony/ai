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
use Symfony\AI\Platform\Bridge\HuggingFace\Task;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('HUGGINGFACE_KEY'), httpClient: http_client());

$result = $platform->invoke('black-forest-labs/FLUX.1-schnell', 'Astronaut riding a horse', [
    'task' => Task::TEXT_TO_IMAGE,
]);

echo $result->asDataUri().\PHP_EOL;
