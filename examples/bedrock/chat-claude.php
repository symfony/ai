<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Agent\Agent;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Bedrock\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use validator\EnvValidator;

require_once dirname(__DIR__).'/bootstrap.php';

EnvValidator::validateAwsCredentials();

$platform = PlatformFactory::create();
$model = new Claude('claude-3-7-sonnet-20250219');

$agent = new Agent($platform, $model, logger: logger());
$messages = new MessageBag(
    Message::forSystem('You answer questions in short and concise manner.'),
    Message::ofUser('What is the Symfony framework?'),
);
$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;
