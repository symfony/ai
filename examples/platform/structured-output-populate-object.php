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
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\City;
use Symfony\Component\EventDispatcher\EventDispatcher;

require_once dirname(__DIR__).'/bootstrap.php';

$dispatcher = new EventDispatcher();
$dispatcher->addSubscriber(new PlatformSubscriber());

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client(), eventDispatcher: $dispatcher);

// Create a partially populated object with only the city name
$city = new City(name: 'Berlin');

echo "Initial object state:\n";
dump($city);

$messages = new MessageBag(
    Message::forSystem('You are a helpful assistant that provides information about cities.'),
    Message::ofUser('Please research the missing data attributes for this city', $city),
);

$result = $platform->invoke('gpt-5-mini', $messages, [
    'response_format' => $city,
]);

echo "\nPopulated object state:\n";
dump($result->asObject());

// Verify that the same object instance was populated
echo "\nObject identity preserved: ";
dump($city === $result->asObject());
