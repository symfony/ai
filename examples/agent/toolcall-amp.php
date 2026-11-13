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
use Symfony\AI\Agent\Toolbox\AmpToolExecutor;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\SequentialToolExecutor;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\HttpClient\AmpHttpClient;

require_once dirname(__DIR__).'/bootstrap.php';

if (is_record() || is_replay()) {
    output()->writeln('<comment>This live concurrency example does not support FIFO cassette recording or replay.</comment>');

    exit(Command::INVALID);
}

/**
 * @author Illia Vasylevskyi <ineersa@gmail.com>
 */
final class AmpTimeline
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
        foreach ($this->events as [$offset, $event]) {
            $table->addRow([sprintf('%5d ms', $offset), $event]);
        }
        $table->render();

        $this->start = null;
        $this->events = [];
    }
}

/**
 * @author Illia Vasylevskyi <ineersa@gmail.com>
 */
#[AsTool('research_topic', 'Researches a single topic and returns a short briefing about it')]
final class AmpResearcher
{
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly AmpTimeline $timeline,
    ) {
    }

    public function __invoke(string $topic): string
    {
        $this->timeline->record(sprintf('<comment>%s</comment>: started', $topic));
        $briefing = $this->platform->invoke('gpt-5-mini', new MessageBag(
            Message::forSystem('Describe the topic the user names in two sentences.'),
            Message::ofUser($topic),
        ))->asText();
        $this->timeline->record(sprintf('<info>%s</info>: finished', $topic));

        return $briefing;
    }
}

$httpClient = new AmpHttpClient();
$httpClient->setLogger(logger());
$platform = Factory::createPlatform(env('OPENAI_API_KEY'), $httpClient);

$timeline = new AmpTimeline();
$toolbox = new Toolbox([new AmpResearcher($platform, $timeline)], logger: logger());
$agent = new Agent($platform, 'gpt-5-mini', toolbox: $toolbox, toolExecutor: new AmpToolExecutor($toolbox));

$messages = new MessageBag(
    Message::forSystem('Research every topic the user mentions with the research_topic tool, requesting all of them within the same turn, and report back with one short paragraph per topic.'),
    Message::ofUser('Please brief me on Symfony, Laravel and Drupal.'),
);

output()->writeln('Asking the agent with Amp-aware HTTP for both the agent and its research tools ...');

$execution = $agent->call($messages);
$toolCalls = [];
foreach ($execution as $update) {
    if ($update instanceof Progress && Progress::STAGE_TOOL_CALL === $update->getStage() && $update->getPayload() instanceof ToolCall) {
        $toolCalls[] = $update->getPayload();
    }
}

output()->writeln($execution->asText());

if (count($toolCalls) < 2) {
    output()->writeln('<comment>The model requested fewer than two tool calls, so there was nothing to overlap.</comment>');

    exit(Command::SUCCESS);
}

$timeline->render(sprintf('%d tool calls with the AmpToolExecutor', count($toolCalls)));

iterator_to_array((new SequentialToolExecutor($toolbox))->execute($toolCalls));

$timeline->render(sprintf('The same %d tool calls with the SequentialToolExecutor', count($toolCalls)));
