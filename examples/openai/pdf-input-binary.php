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
use Symfony\AI\Platform\Message\Content\Document;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

$messages = new MessageBag(
    Message::ofUser(
        'What is this document about?',
        Document::fromFile(dirname(__DIR__, 2).'/fixtures/document.pdf'),
    ),
);
$result = $platform->invoke('gpt-4o-mini', $messages);

echo $result->getResult()->getContent().\PHP_EOL;
