<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Replicate\Factory;
use Symfony\AI\Platform\Job\JobRunner;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('REPLICATE_API_KEY'), http_client());

$messages = new MessageBag(
    Message::forSystem('You are a helpful assistant.'),
    Message::ofUser('Tina has one brother and one sister. How many sisters do Tina\'s siblings have?'),
);

// Replicate runs every model as a prediction, so the invocation returns a handle instead of an answer.
$handle = $platform->invoke('llama-3-8b-instruct', $messages)->asJob();

// Waiting is explicit. Replaying a cassette serves the polls instantly, so skip the real waiting.
$jobClient = Factory::createJobClient(env('REPLICATE_API_KEY'), http_client());
$result = (new JobRunner(clock()))->wait($jobClient, $handle);

echo $result->asText().\PHP_EOL;
