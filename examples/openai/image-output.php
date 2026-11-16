<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Result\BinaryResult;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$result = $platform->invoke(
    model: 'gpt-image-2',
    input: 'A cartoon-style elephant with a long trunk and large ears.',
)->getResult();

assert($result instanceof BinaryResult);

$file = __DIR__.'/image-output.png';
$result->asFile($file);

echo 'Image saved to '.$file.\PHP_EOL;
