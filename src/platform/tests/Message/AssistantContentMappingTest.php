<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Message;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Result\BatchResult;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\CodeExecutionResult;
use Symfony\AI\Platform\Result\ComputerCallResult;
use Symfony\AI\Platform\Result\CustomToolCallResult;
use Symfony\AI\Platform\Result\ExecutableCodeResult;
use Symfony\AI\Platform\Result\FileSearchResult;
use Symfony\AI\Platform\Result\JobResult;
use Symfony\AI\Platform\Result\LocalShellCallResult;
use Symfony\AI\Platform\Result\McpApprovalRequestResult;
use Symfony\AI\Platform\Result\McpCallResult;
use Symfony\AI\Platform\Result\McpListToolsResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RealtimeSessionResult;
use Symfony\AI\Platform\Result\RerankingResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Result\WebSearchResult;

final class AssistantContentMappingTest extends TestCase
{
    // Results that Message::ofAssistant() rejects on purpose, as they are not a conversation turn
    private const NOT_ASSISTANT_CONTENT = [
        BatchResult::class,
        ChoiceResult::class,
        JobResult::class,
        ObjectResult::class,
        RealtimeSessionResult::class,
        RerankingResult::class,
        VectorResult::class,
    ];

    public function testEveryResultTypeIsClassified()
    {
        $classified = array_merge(array_keys(iterator_to_array(self::provideAssistantContentResults())), self::NOT_ASSISTANT_CONTENT);

        foreach (self::findResultClasses() as $class) {
            $this->assertContains($class, $classified, \sprintf('Result type "%s" is neither mapped to assistant content in "%s::toContent()" nor listed in "%s::NOT_ASSISTANT_CONTENT". Add a Message\Content class and a mapping for it, or list it as not assistant content.', $class, Message::class, self::class));
        }
    }

    public function testNoResultTypeIsClassifiedTwice()
    {
        $this->assertSame([], array_intersect(array_keys(iterator_to_array(self::provideAssistantContentResults())), self::NOT_ASSISTANT_CONTENT));
    }

    #[DataProvider('provideAssistantContentResults')]
    public function testResultTypeCanBecomeAssistantContent(ResultInterface $result)
    {
        $message = Message::ofAssistant($result);

        $this->assertInstanceOf(AssistantMessage::class, $message);
        $this->assertNotSame([], $message->getContent());
    }

    /**
     * @return iterable<class-string<ResultInterface>, array{ResultInterface}>
     */
    public static function provideAssistantContentResults(): iterable
    {
        yield TextResult::class => [new TextResult('Hello')];
        yield ThinkingResult::class => [new ThinkingResult('Let me think', 'signature')];
        yield ToolCallResult::class => [new ToolCallResult([new ToolCall('call_1', 'tool')])];
        yield ExecutableCodeResult::class => [new ExecutableCodeResult('print(1)', 'python', 'code_1')];
        yield CodeExecutionResult::class => [new CodeExecutionResult(true, '1', 'code_1')];
        yield WebSearchResult::class => [new WebSearchResult('symfony', 'ws_1', 'completed')];
        yield FileSearchResult::class => [new FileSearchResult(['symfony'], [], 'fs_1', 'completed')];
        yield McpCallResult::class => [new McpCallResult('server', 'tool', '{}', 'output', null, 'mcp_1', 'completed')];
        yield CustomToolCallResult::class => [new CustomToolCallResult('tool', 'input', 'ctc_1', 'completed')];
        yield McpListToolsResult::class => [new McpListToolsResult('server', [], 'mcpl_1')];
        yield McpApprovalRequestResult::class => [new McpApprovalRequestResult('server', 'tool', '{}', 'mcpr_1')];
        yield ComputerCallResult::class => [new ComputerCallResult(['type' => 'screenshot'], 'call_1', [], 'cc_1', 'completed')];
        yield LocalShellCallResult::class => [new LocalShellCallResult(['ls'], 'call_1', 'lsc_1', 'completed')];
        yield BinaryResult::class => [new BinaryResult('data', 'image/png')];
        yield StreamResult::class => [new StreamResult((static function (): \Generator {
            yield new TextDelta('Hello');
        })())];
        yield MultiPartResult::class => [new MultiPartResult([new TextResult('Hello')])];
    }

    /**
     * @return list<class-string<ResultInterface>>
     */
    private static function findResultClasses(): array
    {
        $classes = [];
        foreach (glob(\dirname(__DIR__, 2).'/src/Result/*.php') ?: [] as $file) {
            $class = 'Symfony\\AI\\Platform\\Result\\'.basename($file, '.php');
            if (!class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);
            if ($reflection->isAbstract() || !$reflection->implementsInterface(ResultInterface::class)) {
                continue;
            }

            $classes[] = $class;
        }

        return $classes;
    }
}
