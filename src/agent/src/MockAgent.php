<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent;

use Symfony\AI\Agent\Exception\LogicException;
use Symfony\AI\Agent\Exception\OutOfBoundsException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * A test-friendly agent implementation that doesn't make actual AI calls.
 *
 * This agent provides predictable responses without making external API calls,
 * making it ideal for unit tests and development environments.
 *
 * It tracks all calls made and provides assertion methods for verification.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class MockAgent implements AgentInterface
{
    /**
     * @var array<string, string|MockResponse|\Closure>
     */
    private array $responses = [];

    private int $callCount = 0;

    /**
     * @var array<array{messages: MessageBag, options: array<string, mixed>, input: string, response: string}>
     */
    private array $calls = [];

    /**
     * @param array<string, string|MockResponse|\Closure> $responses Predefined responses for specific inputs
     */
    public function __construct(
        array $responses = [],
        private string $name = 'mock',
    ) {
        $this->responses = $responses;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function call(MessageBag $messages, array $options = []): ResultInterface
    {
        $lastMessage = $messages->getMessages()[\count($messages->getMessages()) - 1];
        $content = '';

        if ($lastMessage instanceof UserMessage) {
            foreach ($lastMessage->getContent() as $messageContent) {
                if ($messageContent instanceof Text) {
                    $content .= $messageContent->getText();
                }
            }
        }

        if (!isset($this->responses[$content])) {
            throw new RuntimeException(\sprintf('No response configured for input "%s".', $content));
        }

        $response = $this->responses[$content];

        // Handle callable responses (similar to MockHttpClient)
        if (\is_callable($response)) {
            $response = $response($messages, $options, $content);
        }

        // Convert response to ResultInterface
        $result = $response instanceof MockResponse
            ? $response->toResult()
            : MockResponse::create($response)->toResult();

        $responseText = $response instanceof MockResponse
            ? $response->getContent()
            : $response;

        // Track the call
        ++$this->callCount;
        $this->calls[] = [
            'messages' => $messages,
            'options' => $options,
            'input' => $content,
            'response' => $responseText,
        ];

        return $result;
    }

    /**
     * Add a response for a specific input.
     */
    public function addResponse(string $input, string|MockResponse|\Closure $response): self
    {
        $this->responses[$input] = $response;

        return $this;
    }

    /**
     * Clear all configured responses.
     */
    public function clearResponses(): self
    {
        $this->responses = [];

        return $this;
    }

    /**
     * Get all configured responses.
     *
     * @return array<string, string|MockResponse|\Closure>
     */
    public function getResponses(): array
    {
        return $this->responses;
    }

    /**
     * Get the number of times call() was invoked.
     */
    public function getCallCount(): int
    {
        return $this->callCount;
    }

    /**
     * Get all recorded calls.
     *
     * @return array<array{messages: MessageBag, options: array<string, mixed>, input: string, response: string}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    /**
     * Get a specific call by index.
     *
     * @return array{messages: MessageBag, options: array<string, mixed>, input: string, response: string}
     *
     * @throws \OutOfBoundsException If the call index doesn't exist
     */
    public function getCall(int $index): array
    {
        if (!isset($this->calls[$index])) {
            throw new OutOfBoundsException(\sprintf('Call at index %d does not exist. Only %d calls have been made.', $index, \count($this->calls)));
        }

        return $this->calls[$index];
    }

    /**
     * Get the last call made (most recent).
     *
     * @return array{messages: MessageBag, options: array<string, mixed>, input: string, response: string}
     *
     * @throws \LogicException If no calls have been made
     */
    public function getLastCall(): array
    {
        if (0 === \count($this->calls)) {
            throw new LogicException('No calls have been made yet.');
        }

        return $this->calls[\count($this->calls) - 1];
    }

    /**
     * Assert that the agent was called exactly once.
     *
     * @throws \AssertionError If the call count is not exactly 1
     */
    public function assertCallCount(int $expectedCount): void
    {
        if ($this->callCount !== $expectedCount) {
            throw new \AssertionError(\sprintf('Expected %d calls, but %d calls were made.', $expectedCount, $this->callCount));
        }
    }

    /**
     * Assert that the agent was called at least once.
     *
     * @throws \AssertionError If no calls were made
     */
    public function assertCalled(): void
    {
        if (0 === $this->callCount) {
            throw new \AssertionError('Expected at least one call, but no calls were made.');
        }
    }

    /**
     * Assert that the agent was never called.
     *
     * @throws \AssertionError If any calls were made
     */
    public function assertNotCalled(): void
    {
        if ($this->callCount > 0) {
            throw new \AssertionError(\sprintf('Expected no calls, but %d calls were made.', $this->callCount));
        }
    }

    /**
     * Assert that a specific input was passed to the agent.
     *
     * @throws \AssertionError If the input was not found in any call
     */
    public function assertCalledWith(string $expectedInput): void
    {
        foreach ($this->calls as $call) {
            if ($call['input'] === $expectedInput) {
                return;
            }
        }

        throw new \AssertionError(\sprintf('Expected to be called with input "%s", but it was not found in any of the %d calls made.', $expectedInput, \count($this->calls)));
    }

    /**
     * Reset call tracking (similar to MockHttpClient::reset()).
     */
    public function reset(): self
    {
        $this->callCount = 0;
        $this->calls = [];

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
