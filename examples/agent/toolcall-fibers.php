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
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\FiberToolExecutor;
use Symfony\AI\Agent\Toolbox\SequentialToolExecutor;
use Symfony\AI\Agent\Toolbox\SuspendableTrait;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Console\Helper\Table;

require_once dirname(__DIR__).'/bootstrap.php';

/**
 * Records when each tool call fires its request and when its response arrives, relative to the
 * first recorded event, so both executors can be compared on the same scale.
 */
final class Timeline
{
    private ?float $start = null;

    /**
     * @var list<array{float, string}>
     */
    private array $events = [];

    public function record(string $event): void
    {
        $this->start ??= microtime(true);
        $this->events[] = [(microtime(true) - $this->start) * 1000, $event];
    }

    public function render(string $title): void
    {
        $table = new Table(output());
        $table->setHeaderTitle($title);
        $table->setHeaders(['Time', 'Event']);
        $table->setColumnWidth(1, 44);
        foreach ($this->events as [$offset, $event]) {
            $table->addRow([sprintf('%5d ms', $offset), $event]);
        }
        $table->render();

        $this->start = null;
        $this->events = [];
    }
}

/**
 * A tool that is I/O bound: it delegates the actual work to a model and waits for its answer.
 */
#[AsTool('research_topic', 'Researches a single topic and returns a short briefing about it')]
final class Researcher
{
    use SuspendableTrait;

    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly Timeline $timeline,
    ) {
    }

    /**
     * @param string $topic The topic to research
     */
    public function __invoke(string $topic): string
    {
        // Invoking the platform is non-blocking: the request is on the wire, but reading its result
        // blocks - so suspending in between lets the sibling tool calls send theirs as well.
        $result = $this->platform->invoke('gpt-5-mini', new MessageBag(
            Message::forSystem('Describe the topic the user names in two sentences.'),
            Message::ofUser($topic),
        ));
        $this->timeline->record(sprintf('<comment>%s</comment>: request sent', $topic));

        $this->suspend();

        $briefing = $result->asText();
        $this->timeline->record(sprintf('<info>%s</info>: answer received', $topic));

        return $briefing;
    }
}

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$timeline = new Timeline();
$toolbox = new Toolbox([new Researcher($platform, $timeline)], logger: logger());

// The fiber executor is a drop-in replacement for the default SequentialToolExecutor: it starts
// every requested tool call as a fiber before collecting the first result.
$agent = new Agent($platform, 'gpt-5-mini', toolbox: $toolbox, toolExecutor: new FiberToolExecutor($toolbox));

$messages = new MessageBag(
    Message::forSystem('Research every topic the user mentions with the research_topic tool, requesting all of them within the same turn, and report back with one short paragraph per topic.'),
    Message::ofUser('Please brief me on Symfony, Laravel and Drupal.'),
);

echo 'Asking the agent, which researches the topics as parallel tool calls ...'.\PHP_EOL.\PHP_EOL;

// Iterating the execution exposes every update the agent produces, here to collect the tool calls
// the model requested, so the very same calls can be replayed with the other executor.
$execution = $agent->call($messages);
$toolCalls = [];
foreach ($execution as $update) {
    if ($update instanceof Progress && 'tool_call' === $update->getStage() && $update->getPayload() instanceof ToolCall) {
        $toolCalls[] = $update->getPayload();
    }
}

echo $execution->asText().\PHP_EOL.\PHP_EOL;

if (count($toolCalls) < 2) {
    output()->writeln('<comment>The model requested less than two tool calls, so there was nothing to interleave.</comment>');

    exit(0);
}

$timeline->render(sprintf('%d tool calls with the FiberToolExecutor', count($toolCalls)));

// Replaying the identical tool calls with the default executor shows what the fibers changed: it
// waits for each answer before the next request is even sent.
iterator_to_array((new SequentialToolExecutor($toolbox))->execute($toolCalls));

$timeline->render(sprintf('The same %d tool calls with the SequentialToolExecutor', count($toolCalls)));
