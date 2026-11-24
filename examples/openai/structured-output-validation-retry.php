<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Fixtures\ProductListing;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\AI\Platform\StructuredOutput\Validator\ValidatorSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcher;

require_once dirname(__DIR__).'/bootstrap.php';

$dispatcher = new EventDispatcher();
$dispatcher->addSubscriber(new PlatformSubscriber());
$dispatcher->addSubscriber(new ValidatorSubscriber());

// prints one line per request, so you can see the retries
$attempt = 0;
$dispatcher->addListener(InvocationEvent::class, static function () use (&$attempt): void {
    echo 'Attempt '.++$attempt.\PHP_EOL;
});

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client(), eventDispatcher: $dispatcher);
$messages = new MessageBag(
    Message::forSystem('You write product listings for an online shop.'),
    Message::ofUser('Create a listing for a reusable steel water bottle.'),
);

$result = $platform->invoke('gpt-5-mini', $messages, [
    'response_format' => ProductListing::class,
    // the tag order is only checked by the validator, so the first answer often fails
    'validation_retries' => 2,
]);

dump($result->asObject(ProductListing::class));
