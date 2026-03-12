<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenRouter\ModelApiCatalog;
use Symfony\AI\Platform\Bridge\OpenRouter\PlatformFactory;
use Symfony\AI\Platform\Bridge\OpenRouter\RegionEnum;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$modelCatalog = new ModelApiCatalog(http_client(), region: RegionEnum::EU, apiKey: env('OPENROUTER_KEY'));

$platform = PlatformFactory::create(env('OPENROUTER_KEY'), http_client(), $modelCatalog, region: RegionEnum::EU);

$messages = new MessageBag(
    Message::forSystem('You are a helpful assistant.'),
    Message::ofUser('Why should I use EU routing for my AI models?'),
);
$result = $platform->invoke('google/gemini-2.5-flash-lite', $messages);

echo $result->asText().\PHP_EOL;
