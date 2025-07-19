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
use Symfony\AI\Agent\Exception\MissingModelSupportException;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;
use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\OutputProcessorInterface;
use Symfony\AI\Agent\Toolbox\Event\ToolCallsExecuted;
use Symfony\AI\Agent\Toolbox\StreamResult as ToolboxStreamResponse;
use Symfony\AI\Platform\Capability;
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

    public function __construct(
        private readonly ToolboxInterface $toolbox,
        private readonly ToolResultConverter $resultConverter = new ToolResultConverter(),
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly bool $keepToolMessages = false,
    ) {
    }

    public function processInput(Input $input): void
    {
        if (!$input->model->supports(Capability::TOOL_CALLING)) {
            throw MissingModelSupportException::forToolCalling($input->model::class);
        }

        $toolMap = $this->toolbox->getTools();
        if ([] === $toolMap) {
            return;
        }

        $options = $input->getOptions();
        // only filter tool map if list of strings is provided as option
        if (isset($options['tools']) && $this->isFlatStringArray($options['tools'])) {
            $toolMap = array_values(array_filter($toolMap, fn (Tool $tool) => \in_array($tool->name, $options['tools'], true)));
        }

        $options['tools'] = $toolMap;
        $input->setOptions($options);
    }

    public function processOutput(Output $output): void
    {
        if ($output->result instanceof GenericStreamResponse) {
            $output->result = new ToolboxStreamResponse(
                $output->result->getContent(),
                $this->handleToolCallsCallback($output),
            );

            return;
        }

        if (!$output->result instanceof ToolCallResult) {
            return;
        }

        $output->result = $this->handleToolCallsCallback($output)($output->result);
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
            $messages = $this->keepToolMessages ? $output->messages : clone $output->messages;

            if (null !== $streamedAssistantResponse && '' !== $streamedAssistantResponse->content) {
                $messages->add($streamedAssistantResponse);
            }

            do {
                $toolCalls = $result->getContent();
                $messages->add(Message::ofAssistant(toolCalls: $toolCalls));

                $results = [];
                foreach ($toolCalls as $toolCall) {
                    $result = $this->toolbox->execute($toolCall);
                    $results[] = new ToolResult($toolCall, $result);
                    $messages->add(Message::ofToolCall($toolCall, $this->resultConverter->convert($result)));
                }

                $event = new ToolCallsExecuted(...$results);
                $this->eventDispatcher?->dispatch($event);

                $result = $event->hasResponse() ? $event->result : $this->agent->call($messages, $output->options);
            } while ($result instanceof ToolCallResult);

            return $result;
        };
    }
}
