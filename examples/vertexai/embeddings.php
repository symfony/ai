<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\VertexAi\PlatformFactory;

require_once __DIR__.'/bootstrap.php';

$platform = PlatformFactory::create(env('GOOGLE_CLOUD_LOCATION'), env('GOOGLE_CLOUD_PROJECT'), httpClient: adc_aware_http_client());

$result = $platform->invoke('gemini-embedding-001', <<<TEXT
    Once upon a time, there was a country called Japan. It was a beautiful country with a lot of mountains and rivers.
    The people of Japan were very kind and hardworking. They loved their country very much and took care of it. The
    country was very peaceful and prosperous. The people lived happily ever after.
    TEXT);

print_vectors($result);
