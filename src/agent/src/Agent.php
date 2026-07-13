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

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Agent\Context\Context;
use Symfony\AI\Agent\Context\ContextProcessorInterface;
use Symfony\AI\Agent\Context\Instruction;
use Symfony\AI\Agent\Context\Processor\AttachmentProcessor;
use Symfony\AI\Agent\Context\Processor\InstructionProcessor;
use Symfony\AI\Agent\Context\Processor\ToolProcessor;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Execution\Cancellation;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Runner;
use Symfony\AI\Agent\Toolbox\SequentialToolExecutor;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolExecutorInterface;
use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Agent implements AgentInterface
{
    private readonly Context $context;

    private readonly Runner $runner;

    /**
     * @param non-empty-string                    $model
     * @param iterable<ContextProcessorInterface> $contextProcessors
     * @param non-empty-string                    $name
     * @param bool                                $includeToolsInInstruction appends the tool definitions to the instruction
     * @param bool                                $excludeToolMessages       keeps the messages appended during tool calling out of the caller's message bag
     * @param bool                                $includeSources            exposes the sources collected during tool calling as `sources` result metadata
     */
    public function __construct(
        PlatformInterface $platform,
        private readonly string $model,
        iterable $contextProcessors = [],
        private readonly string $name = 'agent',
        string|\Stringable|TranslatableInterface|File|null $instruction = null,
        Context $context = new Context(),
        ?ToolboxInterface $toolbox = null,
        ?ToolExecutorInterface $toolExecutor = null,
        ?int $maxToolCalls = 50,
        bool $excludeToolMessages = false,
        bool $includeSources = false,
        bool $includeToolsInInstruction = false,
        ?TranslatorInterface $translator = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->context = null !== $instruction ? $context->with(new Instruction($instruction)) : $context;

        if (null === $toolExecutor && $toolbox instanceof ToolboxInterface) {
            $toolExecutor = new SequentialToolExecutor($toolbox);
        }

        $processors = [
            new InstructionProcessor($translator, $includeToolsInInstruction ? $toolbox : null, $logger ?? new NullLogger()),
            new AttachmentProcessor(),
        ];

        if ($toolbox instanceof ToolboxInterface) {
            $processors[] = new ToolProcessor($toolbox);
        }

        foreach ($contextProcessors as $processor) {
            if (!$processor instanceof ContextProcessorInterface) {
                throw new InvalidArgumentException(\sprintf('Context processor "%s" must implement "%s".', get_debug_type($processor), ContextProcessorInterface::class));
            }

            $processors[] = $processor;
        }

        $this->runner = new Runner(
            $platform,
            $processors,
            $toolExecutor,
            $toolbox,
            $maxToolCalls,
            $excludeToolMessages,
            $includeSources,
            $eventDispatcher,
        );
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Starts the agent and returns a lazy {@see Execution} that is also the result it produces.
     *
     * Read it eagerly with `->getContent()`/`->getResult()`, iterate it to observe every model request, tool call
     * and streamed delta as an update, or register callbacks via `->onProgress(...)`. With the "stream" option
     * set, `->getContent()` yields the answer's deltas.
     *
     * @param array<string, mixed> $options
     *
     * @throws InvalidArgumentException When the platform returns a client error (4xx) indicating invalid request parameters
     * @throws RuntimeException         When the platform returns a server error (5xx) or network failure occurs
     * @throws ExceptionInterface       When the platform converter throws an exception
     */
    public function call(string|MessageBag|UserMessage $input, Context $context = new Context(), array $options = []): Execution
    {
        $messages = InputNormalizer::toMessageBag($input);
        $mergedContext = $this->context->merge($context);
        $model = $this->resolveModel($options);

        $cancellation = new Cancellation();

        $factory = fn (): \Generator => yield from $this->runner->run($this, $model, $messages, $mergedContext, $options, $cancellation);

        return new Execution($factory, true === ($options['stream'] ?? false), $cancellation);
    }

    /**
     * The model configured on the agent, overridable per call through the "model" option.
     *
     * @param array<string, mixed> $options
     *
     * @return non-empty-string
     */
    private function resolveModel(array $options): string
    {
        if (!\array_key_exists('model', $options)) {
            return $this->model;
        }

        if (!\is_string($options['model']) || '' === $options['model']) {
            throw new InvalidArgumentException('Option "model" must be a non-empty string.');
        }

        return $options['model'];
    }
}
