<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\ModelsLab\ModelCatalog;
use Symfony\AI\Platform\Bridge\ModelsLab\PlatformFactory;

require_once __DIR__.'/../vendor/autoload.php';

$platform = PlatformFactory::create((string) getenv('MODELSLAB_API_KEY'));
$catalog = new ModelCatalog();

// Generate an image with Flux
$model = $catalog->get('flux');
$result = $platform->request($model, 'A futuristic city at night, neon lights reflecting on wet streets')->getResult();

file_put_contents('/tmp/modelslab-output.jpg', $result->asBinary());
echo 'Image saved to /tmp/modelslab-output.jpg'.PHP_EOL;
