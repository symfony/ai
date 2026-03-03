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
use Symfony\AI\Platform\EventListener\TemplateRendererListener;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Template;
use Symfony\AI\Platform\Message\TemplateRenderer\StringTemplateRenderer;
use Symfony\AI\Platform\Message\TemplateRenderer\TemplateRendererRegistry;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\City;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

require_once dirname(__DIR__).'/bootstrap.php';

$dispatcher = new EventDispatcher();
$dispatcher->addSubscriber(new PlatformSubscriber());

$normalizer = new ObjectNormalizer();
$registry = new TemplateRendererRegistry([new StringTemplateRenderer()]);
$dispatcher->addSubscriber(new TemplateRendererListener($registry, $normalizer));

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client(), eventDispatcher: $dispatcher);

$city = new City(name: 'Berlin');

echo "Initial object state:\n";
dump($city);

$messages = new MessageBag(
    Message::forSystem('You are a helpful assistant that provides information about cities.'),
    Message::ofUser(Template::string('Please research the missing data attributes for this city: {city.name}')),
);

$result = $platform->invoke('gpt-4o-mini', $messages, [
    'template_vars' => ['city' => $city],
    'response_format' => $city,
]);

echo "\nPopulated object state:\n";
dump($result->asObject());

echo "\nObject identity preserved: ";
dump($city === $result->asObject());
