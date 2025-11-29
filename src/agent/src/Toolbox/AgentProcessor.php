<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox;

use Symfony\AI\Agent\AgentAwareInterface;
use Symfony\AI\Agent\AgentAwareTrait;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;
use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\OutputProcessorInterface;
use Symfony\AI\Agent\Toolbox\Event\ToolCallsExecuted;
use Symfony\AI\Agent\Toolbox\Source\Source;
use Symfony\AI\Agent\Toolbox\StreamResult as ToolboxStreamResponse;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult as GenericStreamResponse;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AgentProcessor implements InputProcessorInterface, OutputProcessorInterface, AgentAwareInterface
{
    use AgentAwareTrait;

    /**
     * Sources get collected during tool calls on class level to be able to handle consecutive tool calls.
     * They get added to the result metadata and reset when the outermost agent call is finished via nesting level.
     *
     * @var Source[]
     */
    private array $sources = [];

    /**
     * Tracks the nesting level of agent calls.
     */
    private int $nestingLevel = 0;

    public function __construct(
        private readonly ToolboxInterface $toolbox,
        private readonly ToolResultConverter $resultConverter = new ToolResultConverter(),
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly bool $keepToolMessages = false,
        private readonly bool $includeSources = false,
    ) {
    }

    public function processInput(Input $input): void
    {
        $toolMap = $this->toolbox->getTools();
        if ([] === $toolMap) {
            return;
        }

        $options = $input->getOptions();
        // only filter tool map if list of strings is provided as option
        if (isset($options['tools']) && $this->isFlatStringArray($options['tools'])) {
            $toolMap = array_values(array_filter($toolMap, fn (Tool $tool) => \in_array($tool->getName(), $options['tools'], true)));
        }

        $options['tools'] = $toolMap;
        $input->setOptions($options);
    }

    public function processOutput(Output $output): void
    {
        $result = $output->getResult();

        if ($result instanceof GenericStreamResponse) {
            $output->setResult(
                new ToolboxStreamResponse($result->getContent(), $this->handleToolCallsCallback($output))
            );

            return;
        }

        if (!$result instanceof ToolCallResult) {
            return;
        }

        $output->setResult($this->handleToolCallsCallback($output)($result));
    }

    /**
     * @param array<mixed> $tools
     */
    private function isFlatStringArray(array $tools): bool
    {
        return array_reduce($tools, fn (bool $carry, mixed $item) => $carry && \is_string($item), true);
    }

    private function handleToolCallsCallback(Output $output): \Closure
    {
        return function (ToolCallResult $result, ?AssistantMessage $streamedAssistantResponse = null) use ($output): ResultInterface {
            ++$this->nestingLevel;
            $messages = $this->keepToolMessages ? $output->getMessageBag() : clone $output->getMessageBag();

            if (null !== $streamedAssistantResponse && '' !== $streamedAssistantResponse->getContent()) {
                $messages->add($streamedAssistantResponse);
            }

            do {
                $toolCalls = $result->getContent();
                $messages->add(Message::ofAssistant(toolCalls: $toolCalls));

                $results = [];
                foreach ($toolCalls as $toolCall) {
                    $results[] = $toolResult = $this->toolbox->execute($toolCall);
                    $messages->add(Message::ofToolCall($toolCall, $this->resultConverter->convert($toolResult)));
                    array_push($this->sources, ...$toolResult->getSources());
                }

                $event = new ToolCallsExecuted(...$results);
                $this->eventDispatcher?->dispatch($event);

                $result = $event->hasResult() ? $event->getResult() : $this->agent->call($messages, $output->getOptions());
            } while ($result instanceof ToolCallResult);

            --$this->nestingLevel;
            if ($this->includeSources && 0 === $this->nestingLevel) {
                $result->getMetadata()->add('sources', $this->sources);
                $this->sources = [];
            }

            return $result;
        };
    }
}
