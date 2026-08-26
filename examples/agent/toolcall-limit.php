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
use Symfony\AI\Agent\Exception\MaxIterationsExceededException;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

#[AsTool('process_next_message', 'Processes the next message of the queue and reports the remaining ones')]
final class QueueProcessor
{
    private int $processed = 0;

    public function __invoke(): string
    {
        ++$this->processed;

        return sprintf('Message %d processed, 100 messages left in the queue.', $this->processed);
    }
}

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$toolbox = new Toolbox([new QueueProcessor()], logger: logger());
$agent = new Agent($platform, 'gpt-5-mini', toolbox: $toolbox, maxToolCalls: 3);

$messages = new MessageBag(
    Message::forSystem('You work off message queues. Keep processing messages until the queue is empty.'),
    Message::ofUser('Please empty the message queue.'),
);

try {
    $result = $agent->call($messages)->getResult();
} catch (MaxIterationsExceededException $e) {
    echo $e->getMessage().\PHP_EOL;

    exit(0);
}

output()->writeln('<error>Expected the tool call limit to stop the agent, but it returned a result.</error>');
echo $result->getContent().\PHP_EOL;

exit(1);
