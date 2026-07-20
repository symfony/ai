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
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessor\SystemPromptInputProcessor;
use Symfony\AI\Agent\InputProcessorInterface;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Exception\InterruptedException;
use Symfony\AI\Platform\Result\InterruptionSignal;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$signal = new InterruptionSignal();

// Any input processor can consult an external state to decide if the pipeline
// should keep going. Here we interrupt right after the first processor runs —
// in a real application this would be driven by a fresh user input received
// on a WebSocket, a timer, or a CI budget check.
$budgetGuard = new class($signal) implements InputProcessorInterface {
    public function __construct(private readonly InterruptionSignal $signal)
    {
    }

    public function processInput(Input $input): void
    {
        // Simulate an external condition firing the signal.
        $this->signal->interrupt();
    }
};

$agent = new Agent($platform, 'gpt-5-mini', [
    new SystemPromptInputProcessor('Be brief.'),
    $budgetGuard,
]);

$messages = new MessageBag(Message::ofUser('What is the meaning of life?'));

try {
    $result = $agent->call($messages, ['interruption_signal' => $signal]);
    output()->writeln($result->getContent());
} catch (InterruptedException) {
    output()->writeln('<comment>Pipeline interrupted by external signal — no platform call was made.</comment>');
}
