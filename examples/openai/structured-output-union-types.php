<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UnionType\UnionTypeDto;
use Symfony\Component\EventDispatcher\EventDispatcher;

require_once dirname(__DIR__).'/bootstrap.php';

$dispatcher = new EventDispatcher();
$dispatcher->addSubscriber(new PlatformSubscriber());

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client(), eventDispatcher: $dispatcher);
$messages = new MessageBag(
    Message::forSystem(<<<PROMPT
        You are a time assistant! You can provide time either as a unix timestamp or as a human readable time format.
        If you don't know the time, return null.
    PROMPT),
    Message::ofUser('What is the current time?'),
);
$result = $platform->invoke('gpt-4o-mini', $messages, ['response_format' => UnionTypeDto::class]);

dump($result->asObject());
