<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Voyage\PlatformFactory;
use Symfony\AI\Platform\Message\Content\Collection;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('VOYAGE_API_KEY'), http_client());

$image = Image::fromFile(dirname(__DIR__, 2).'/fixtures/image.jpg');

// Single value
$result1 = $platform->invoke('voyage-multimodal-3',
    new Text('Lorem ipsum dolor sit amet, consectetur adipiscing elit.'),
);

// Multiple values
$result2 = $platform->invoke('voyage-multimodal-3', [
    new Collection(new Text('Photo of a sunrise'), $image),
    new Collection(new Text('Photo of a sunset'), $image),
]);

echo 'Dimensions for text: '.$result1->asVectors()[0]->getDimensions().\PHP_EOL;

echo 'Dimensions for sunrise image and description: '.$result2->asVectors()[0]->getDimensions().\PHP_EOL;
echo 'Dimensions for sunset image and description: '.$result2->asVectors()[1]->getDimensions().\PHP_EOL;
