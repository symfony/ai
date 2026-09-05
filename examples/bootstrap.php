<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\Exception\ExceptionInterface as AgentException;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Platform\Exception\ExceptionInterface as PlatformException;
use Symfony\AI\Platform\FinishReason\FinishReason;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Test\Replay\CassetteHttpClient;
use Symfony\AI\Platform\Test\Replay\HttpCassette;
use Symfony\AI\Platform\TokenUsage\TokenUsageAggregation;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;
use Symfony\AI\Store\Exception\ExceptionInterface as StoreException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

require_once __DIR__.'/vendor/autoload.php';
(new Dotenv())->loadEnv(__DIR__.'/.env');

function env(string $var): string
{
    if (isset($_SERVER[$var]) && '' !== $_SERVER[$var]) {
        return $_SERVER[$var];
    }

    if (is_replay()) {
        return 'sk-replay-'.strtolower($var);
    }

    output()->writeln(sprintf('<error>Please set the "%s" environment variable to run this example.</error>', $var));
    exit(1);
}

function optional_env(string $var): ?string
{
    if (isset($_SERVER[$var]) && '' !== $_SERVER[$var]) {
        return $_SERVER[$var];
    }

    if (is_replay()) {
        return env($var);
    }

    return null;
}

function is_replay(): bool
{
    return 'replay' === ($_SERVER['CASSETTE'] ?? '');
}

function is_record(): bool
{
    return 'record' === ($_SERVER['CASSETTE'] ?? '');
}

function cassette_path(): string
{
    $script = (string) ($_SERVER['SCRIPT_FILENAME'] ?? ($_SERVER['argv'][0] ?? ''));
    $absolute = realpath($script) ?: $script;
    $base = __DIR__.\DIRECTORY_SEPARATOR;
    $relative = str_starts_with($absolute, $base) ? substr($absolute, strlen($base)) : basename($absolute);

    return __DIR__.'/tests/fixtures/'.preg_replace('/\.php$/', '', $relative).'.json';
}

function http_client(): HttpClientInterface
{
    // One cassette per example run requires one shared client: appends accumulate, replay consumes FIFO.
    static $cassetteClient = null;

    if (null !== $cassetteClient) {
        return $cassetteClient;
    }

    if (is_replay()) {
        return $cassetteClient = new CassetteHttpClient(new HttpCassette(cassette_path()), record: false);
    }

    $httpClient = HttpClient::create();

    if ($httpClient instanceof LoggerAwareInterface) {
        $httpClient->setLogger(logger());
    }

    if (is_record()) {
        // Re-recording replaces the previous take; HttpCassette appends otherwise.
        if (is_file($path = cassette_path())) {
            unlink($path);
        }

        return $cassetteClient = new CassetteHttpClient(new HttpCassette($path), $httpClient, record: true);
    }

    return $httpClient;
}

function logger(): LoggerInterface
{
    $output = output();

    return new class($output) extends ConsoleLogger {
        private ConsoleOutput $output;

        public function __construct(ConsoleOutput $output)
        {
            parent::__construct($output);
            $this->output = $output;
        }

        /**
         * @param Stringable|string $message
         */
        public function log($level, $message, array $context = []): void
        {
            // Call parent to handle the base logging
            parent::log($level, $message, $context);

            // Add context display for debug verbosity
            if ($this->output->getVerbosity() >= ConsoleOutput::VERBOSITY_DEBUG && [] !== $context) {
                // Filter out special keys that are already handled
                $displayContext = array_filter($context, static function ($key) {
                    return !in_array($key, ['exception', 'error', 'object'], true);
                }, \ARRAY_FILTER_USE_KEY);

                if ([] !== $displayContext) {
                    $contextMessage = '  '.json_encode($displayContext, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
                    $this->output->writeln(sprintf('<comment>%s</comment>', $contextMessage));
                }
            }
        }
    };
}

/**
 * Whether the example writes to an interactive terminal.
 *
 * Live re-rendering with escape sequences like a clear screen only works there: when the
 * output is captured - by the example runner, a pipe or a replay golden - those sequences
 * would garble it, so examples fall back to printing the final state once.
 */
function is_interactive(): bool
{
    return defined('STDOUT') && stream_isatty(\STDOUT);
}

function output(): ConsoleOutput
{
    $verbosity = match ($_SERVER['argv'][1] ?? null) {
        '-v', '--verbose' => ConsoleOutput::VERBOSITY_VERBOSE,
        '-vv', '--very-verbose' => ConsoleOutput::VERBOSITY_VERY_VERBOSE,
        '-vvv', '--debug' => ConsoleOutput::VERBOSITY_DEBUG,
        default => ConsoleOutput::VERBOSITY_NORMAL,
    };

    return new ConsoleOutput($verbosity);
}

function print_sources(?SourceCollection $sources): void
{
    if (null === $sources || 0 === $sources->count()) {
        output()->writeln('<error>No sources available.</error>');

        return;
    }

    $table = new Table(output());
    $table->setHeaderTitle('Tool Sources');
    $table->setHeaders(['Name (Reference)', 'Content']);
    foreach ($sources as $source) {
        $name = $source->getName();
        $reference = $source->getReference();
        $content = $source->getContent();
        $table->addRow([
            '<comment>'.(strlen($name) <= 50 ? $name : substr($name, 0, 50).'...').'</comment>'.\PHP_EOL.
            '<fg=gray>'.(strlen($reference) <= 50 ? $reference : substr($reference, 0, 50).'...').'</>',
            strlen($content) <= 100 ? $content : substr($content, 0, 100).'...',
        ]);
        $table->addRow(new TableSeparator());
    }
    $table->render();
}

function print_token_usage(?TokenUsageInterface $tokenUsage): void
{
    if (null === $tokenUsage) {
        output()->writeln('<error>No token usage information available.</error>');
        exit(1);
    }

    $na = '<comment>n/a</comment>';
    $table = new Table(output());
    $table->setHeaderTitle('Token Usage');
    $table->setRows([
        ['Model', $tokenUsage->getModel() ?? $na],
        ['Prompt tokens', $tokenUsage->getPromptTokens() ?? $na],
        ['Completion tokens', $tokenUsage->getCompletionTokens() ?? $na],
        ['Thinking tokens', $tokenUsage->getThinkingTokens() ?? $na],
        ['Tool tokens', $tokenUsage->getToolTokens() ?? $na],
        ['Cached tokens', $tokenUsage->getCachedTokens() ?? $na],
        ['Remaining tokens minute', $tokenUsage->getRemainingTokensMinute() ?? $na],
        ['Remaining tokens month', $tokenUsage->getRemainingTokensMonth() ?? $na],
        ['Remaining tokens', $tokenUsage->getRemainingTokens() ?? $na],
        ['Total tokens', $tokenUsage->getTotalTokens() ?? $na],
    ]);
    $table->render();

    if ($tokenUsage instanceof TokenUsageAggregation) {
        output()->writeln(sprintf('<comment>Aggregated token usage from %d calls.</comment>', $tokenUsage->count()));
    }
}

/**
 * Renders what each message is made of rather than what it says, which keeps the recorded
 * golden stable across re-records.
 */
function print_conversation_shape(MessageBag $messages): void
{
    $table = new Table(output());
    $table->setHeaderTitle('Conversation Shape');
    $table->setHeaders(['#', 'Role', 'Content parts']);

    foreach ($messages->getMessages() as $index => $message) {
        $table->addRow([$index, $message->getRole()->value, implode(' + ', describe_message_parts($message))]);
    }

    $table->render();
}

/**
 * @return list<string>
 */
function describe_message_parts(MessageInterface $message): array
{
    if ($message instanceof ToolCallMessage) {
        return [sprintf('ToolResult(%s)', $message->getToolCall()->getName())];
    }

    if (!$message instanceof AssistantMessage) {
        return ['Text'];
    }

    $parts = [];
    foreach ($message->getContent() as $part) {
        $parts[] = match (true) {
            $part instanceof Thinking => sprintf(
                'Thinking(%s)',
                null === $part->getSignature() ? '<error>unsigned</error>' : '<info>signed</info>',
            ),
            $part instanceof ToolCall => sprintf('ToolCall(%s)', $part->getName()),
            $part instanceof Text => 'Text',
            default => (new ReflectionClass($part))->getShortName(),
        };
    }

    return [] === $parts ? ['<error>empty</error>'] : $parts;
}

function print_finish_reason(?FinishReason $finishReason): void
{
    if (null === $finishReason) {
        output()->writeln('<error>No finish reason information available.</error>');
        exit(1);
    }

    $table = new Table(output());
    $table->setHeaderTitle('Finish Reason');
    $table->setRows([
        ['Normalized case', $finishReason->getCase()->value],
        ['Raw provider value', $finishReason->getRaw()],
        ['Truncated', $finishReason->is(FinishReasonCase::LENGTH) ? '<error>yes</error>' : 'no'],
    ]);
    $table->render();
}

function print_vectors(DeferredResult $result): void
{
    assert([] !== $result->asVectors());
    assert(array_key_exists(0, $result->asVectors()));

    output()->writeln(sprintf('Dimensions: %d', $result->asVectors()[0]->getDimensions()));
}

set_exception_handler(static function ($exception) {
    if ($exception instanceof AgentException || $exception instanceof PlatformException || $exception instanceof StoreException) {
        output()->writeln(sprintf('<error>%s</error>', $exception->getMessage()));

        if (output()->isVerbose()) {
            output()->writeln($exception->getTraceAsString());
        }

        exit(1);
    }

    throw $exception;
});
