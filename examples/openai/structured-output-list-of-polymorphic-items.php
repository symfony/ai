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
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType\ListOfPolymorphicTypesDto;
use Symfony\Component\EventDispatcher\EventDispatcher;

require_once dirname(__DIR__).'/bootstrap.php';

$dispatcher = new EventDispatcher();
$dispatcher->addSubscriber(new PlatformSubscriber());

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client(), eventDispatcher: $dispatcher);
$messages = new MessageBag(
    Message::forSystem('You are a persona data collector! Return all the data you can gather from the user input.'),
    Message::ofUser('Hi! My name is John Doe, I am 30 years old and I live in Paris.'),
);
$result = $platform->invoke('gpt-4o-mini', $messages, ['response_format' => ListOfPolymorphicTypesDto::class]);

dump($result->asObject());
