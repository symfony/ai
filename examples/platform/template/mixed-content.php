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
use Symfony\Component\EventDispatcher\EventDispatcher;

require_once dirname(__DIR__, 2).'/bootstrap.php';

$eventDispatcher = new EventDispatcher();
$rendererRegistry = new TemplateRendererRegistry([
    new StringTemplateRenderer(),
]);
$templateListener = new TemplateRendererListener($rendererRegistry);
$eventDispatcher->addSubscriber($templateListener);

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client(), eventDispatcher: $eventDispatcher);

echo "UserMessage with mixed content\n";
echo "==============================\n\n";

$messages = new MessageBag(
    Message::forSystem('You are a helpful assistant.'),
    Message::ofUser('I need help with', Template::string(' {task}'))
);

$result = $platform->invoke('gpt-4o-mini', $messages, [
    'template_vars' => ['task' => 'debugging'],
]);

echo "UserMessage: 'Plain text' + Template('{task}')\n";
echo "Variables: ['task' => 'debugging']\n";
echo 'Response: '.$result->asText()."\n";
