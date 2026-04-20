<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Failover\FailoverPlatform;
use Symfony\AI\Platform\Bridge\Ollama\Factory as OllamaFactory;
use Symfony\AI\Platform\Bridge\OpenAi\Factory as OpenAiFactory;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt\ResultConverter;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

require_once dirname(__DIR__).'/bootstrap.php';

$rateLimiter = new RateLimiterFactory([
    'policy' => 'sliding_window',
    'id' => 'failover',
    'interval' => '3 seconds',
    'limit' => 1,
], new InMemoryStorage());

// # Ollama will fail as 'gpt-5.2' is not available in the catalog
$platform = new FailoverPlatform([
    OllamaFactory::createPlatform(env('OLLAMA_HOST_URL'), httpClient: http_client()),
    OpenAiFactory::createPlatform(env('OPENAI_API_KEY'), http_client()),
], $rateLimiter);

$result = $platform->invoke('gpt-5.2', new MessageBag(
    Message::forSystem('You are a helpful assistant.'),
    Message::ofUser('Tina has one brother and one sister. How many sisters do Tina\'s siblings have?'),
));

assert($result->getResultConverter() instanceof ResultConverter);

echo $result->asText().\PHP_EOL;
