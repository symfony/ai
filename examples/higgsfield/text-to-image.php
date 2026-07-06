<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Higgsfield\Factory;
use Symfony\AI\Platform\Message\Content\Text;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(
    apiKey: env('HIGGSFIELD_API_KEY'),
    apiSecret: env('HIGGSFIELD_API_SECRET'),
    httpClient: http_client(),
);

$result = $platform->invoke('flux-pro/kontext/max/text-to-image', new Text('A cat on a kitchen table'), [
    'aspect_ratio' => '9:16',
]);

$result->asFile(__DIR__.'/text-to-image.png');

echo 'Image saved to text-to-image.png'.\PHP_EOL;
