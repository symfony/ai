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
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Result\BinaryResult;

require_once dirname(__DIR__).'/bootstrap.php';

// Image edits regularly take longer than the default idle timeout of 60 seconds
$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client()->withOptions(['timeout' => 300]));

// Pass the source image to edit via the "image" option; the prompt describes the change.
$result = $platform->invoke(
    model: 'gpt-image-1',
    input: 'Colorize the elephant in bright red and add a party hat.',
    options: [
        'image' => Image::fromFile(dirname(__DIR__, 2).'/fixtures/image.jpg'),
    ],
)->getResult();

assert($result instanceof BinaryResult);

$file = __DIR__.'/gpt-image-1-edit.png';
$result->asFile($file);

echo 'Edited image saved to '.$file.\PHP_EOL;
