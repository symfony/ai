<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Perplexity\Perplexity;
use Symfony\AI\Platform\Bridge\Perplexity\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('PERPLEXITY_API_KEY'), http_client());
$model = new Perplexity('sonar');

$messages = new MessageBag(Message::ofUser('What is the best French cheese?'));
$response = $platform->invoke($model, $messages);

echo $response->getResult()->getContent().\PHP_EOL;
