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
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once __DIR__.'/bootstrap.php';

$platform = PlatformFactory::create(env('GOOGLE_CLOUD_LOCATION'), env('GOOGLE_CLOUD_PROJECT'), httpClient: adc_aware_http_client());

$messages = new MessageBag(
    Message::ofUser(
        <<<'PROMPT'
            What's the latest 12-month Euribor rate based on https://www.euribor-rates.eu/en/current-euribor-rates/4/euribor-rate-12-months/
            PROMPT,
    ),
);

$result = $platform->invoke('gemini-2.5-pro', $messages, ['server_tools' => ['url_context' => true]]);

echo $result->asText().\PHP_EOL;
