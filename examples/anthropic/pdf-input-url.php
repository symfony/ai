<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Anthropic\PlatformFactory;
use Symfony\AI\Platform\Message\Content\DocumentUrl;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('ANTHROPIC_API_KEY'), httpClient: http_client());

$messages = new MessageBag(
    Message::ofUser(
        new DocumentUrl('https://upload.wikimedia.org/wikipedia/commons/2/20/Re_example.pdf'),
        'What is this document about?',
    ),
);
$result = $platform->invoke('claude-3-5-sonnet-20241022', $messages);

echo $result->getResult()->getContent().\PHP_EOL;
