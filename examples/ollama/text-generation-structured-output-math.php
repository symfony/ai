<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Ollama\PlatformFactory;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\MathReasoning;
use Symfony\Component\EventDispatcher\EventDispatcher;

require_once dirname(__DIR__).'/bootstrap.php';

$dispatcher = new EventDispatcher();
$dispatcher->addSubscriber(new PlatformSubscriber());

$platform = PlatformFactory::create(env('OLLAMA_HOST_URL'), env('OLLAMA_API_KEY'), httpClient: http_client(), eventDispatcher: $dispatcher);
$result = $platform->invoke(
    env('OLLAMA_LLM'),
    new Text('You are a helpful math tutor. Guide the user through the solution step by step, how can I solve 8x + 7 = -23?'),
    ['response_format' => MathReasoning::class],
);

dump($result->asObject());
