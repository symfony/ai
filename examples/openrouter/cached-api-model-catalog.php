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
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\HttpClient\CachingHttpClient;

require_once dirname(__DIR__).'/bootstrap.php';

$cache = new FilesystemTagAwareAdapter(
    namespace: 'model_catalog',
    defaultLifetime: 60 * 60 * 24 * 7, // One week
    directory: dirname(__DIR__).'/var/',
);

$cachedHttpClient = new CachingHttpClient(
    client: http_client(),
    cache: $cache,
    maxTtl: 60 * 60 * 24 * 7 // One week
);

$modelCatalog = new ModelApiCatalog($cachedHttpClient);

$platform = PlatformFactory::create(env('OPENROUTER_KEY'), http_client(), $modelCatalog);

$messages = new MessageBag(
    Message::forSystem('You are a helpful assistant.'),
    Message::ofUser('Tina has one brother and one sister. How many sisters do Tina\'s siblings have?'),
);
$result = $platform->invoke('google/gemini-2.5-flash-lite', $messages);

echo $result->asText().\PHP_EOL;
