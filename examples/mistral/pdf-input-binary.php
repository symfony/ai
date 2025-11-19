<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Mistral\PlatformFactory;
use Symfony\AI\Platform\Message\Content\Document;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('MISTRAL_API_KEY'), httpClient: http_client());

$messages = new MessageBag(
    Message::ofUser(
        Document::fromFile(dirname(__DIR__, 2).'/fixtures/document.pdf'),
        'What is this document about?',
    ),
);
$result = $platform->invoke('mistral-small-latest', $messages);

echo $result->asText().\PHP_EOL;
