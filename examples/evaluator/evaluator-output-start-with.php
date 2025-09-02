<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Evaluator\Evaluator;
use Symfony\AI\Evaluator\Scorer\StartWith;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

$evaluator = new Evaluator([
    new StartWith('Your name is'),
]);

$result = $platform->invoke('gpt-4o-mini', new MessageBag(
    Message::forSystem('You are a helpful assistant. You only answer with short sentences.'),
    Message::ofUser('My name is Christopher.'),
    Message::ofUser('What is my name?')
));

$score = $evaluator->evaluate($result);

assert(1.0 === $score);

echo $result->asText().\PHP_EOL;
