<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Bridge\Gemini\PlatformFactory;
use Symfony\AI\Platform\Message\Content\Document;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('GEMINI_API_KEY'), http_client());
$model = new Gemini(Gemini::GEMINI_1_5_FLASH);

$messages = new MessageBag(
    Message::ofUser(
        Document::fromFile(dirname(__DIR__, 2).'/fixtures/document.pdf'),
        'What is this document about?',
    ),
);
$result = $platform->invoke($model, $messages);

echo $result->getResult()->getContent().\PHP_EOL;
