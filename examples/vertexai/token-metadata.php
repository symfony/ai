<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Agent\Agent;
use Symfony\AI\Platform\Bridge\VertexAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once __DIR__.'/bootstrap.php';

$platform = PlatformFactory::create(env('GOOGLE_CLOUD_LOCATION'), env('GOOGLE_CLOUD_PROJECT'), adc_aware_http_client());

$agent = new Agent($platform, 'gemini-2.0-flash-lite');
$messages = new MessageBag(
    Message::forSystem('You are an expert assistant in animal study.'),
    Message::ofUser('What does a cat usually eat?'),
);
$result = $agent->call($messages);

$metadata = $result->getMetadata();
$tokenUsage = $metadata->get('token_usage');

print_token_usage($result->getMetadata()->get('token_usage'));
