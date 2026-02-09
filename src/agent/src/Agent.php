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

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Policy\InputPolicyInterface;
use Symfony\AI\Agent\Policy\OutputPolicyInterface;
use Symfony\AI\Agent\Policy\PolicyHandlerRegistry;
use Symfony\AI\Agent\Policy\PolicyHandlerRegistryInterface;
use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Agent implements AgentInterface
{
    /**
     * @var InputProcessorInterface[]
     */
    private readonly array $inputProcessors;

    /**
     * @var OutputProcessorInterface[]
     */
    private readonly array $outputProcessors;

    /**
     * @param InputProcessorInterface[]  $inputProcessors
     * @param OutputProcessorInterface[] $outputProcessors
     * @param non-empty-string           $model
     */
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly string $model,
        iterable $inputProcessors = [],
        iterable $outputProcessors = [],
        private readonly PolicyHandlerRegistryInterface $policyHandlerRegistry = new PolicyHandlerRegistry(),
        private readonly string $name = 'agent',
    ) {
        $this->inputProcessors = $this->initializeProcessors($inputProcessors, InputProcessorInterface::class);
        $this->outputProcessors = $this->initializeProcessors($outputProcessors, OutputProcessorInterface::class);
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
     * @param array<string, mixed>                           $options
     * @param InputPolicyInterface[]|OutputPolicyInterface[] $policies
     *
     * @throws InvalidArgumentException When the platform returns a client error (4xx) indicating invalid request parameters
     * @throws RuntimeException         When the platform returns a server error (5xx) or network failure occurs
     * @throws ExceptionInterface       When the platform converter throws an exception
     */
    public function call(MessageBag $messages, array $options = [], array $policies = []): ResultInterface
    {
        $policies = [
            'input' => array_filter($policies, static fn (object $policy): bool => $policy instanceof InputPolicyInterface),
            'output' => array_filter($policies, static fn (object $policy): bool => $policy instanceof OutputPolicyInterface),
        ];

        $input = new Input($this->getModel(), $messages, $options, $policies['input']);

        array_map(static fn (InputProcessorInterface $processor) => $processor->processInput($input), $this->inputProcessors);
        array_walk($policies['input'], fn (InputPolicyInterface $policy) => $this->policyHandlerRegistry->get($policy)->handle($messages, $options, $policy));

        $model = $input->getModel();
        $messages = $input->getMessageBag();
        $options = $input->getOptions();

        $result = $this->platform->invoke($model, $messages, $options)->getResult();

        $output = new Output($model, $result, $messages, $options, $policies['output']);

        array_map(static fn (OutputProcessorInterface $processor) => $processor->processOutput($output), $this->outputProcessors);
        array_walk($policies['output'], fn (OutputPolicyInterface $policy) => $this->policyHandlerRegistry->get($policy)->handle($messages, $options, $policy));

        return $output->getResult();
    }

    /**
     * @param InputProcessorInterface[]|OutputProcessorInterface[] $processors
     * @param class-string                                         $interface
     *
     * @return InputProcessorInterface[]|OutputProcessorInterface[]
     */
    private function initializeProcessors(iterable $processors, string $interface): array
    {
        foreach ($processors as $processor) {
            if (!$processor instanceof $interface) {
                throw new InvalidArgumentException(\sprintf('Processor "%s" must implement "%s".', $processor::class, $interface));
            }

            if ($processor instanceof AgentAwareInterface) {
                $processor->setAgent($this);
            }
        }

        return $processors instanceof \Traversable ? iterator_to_array($processors) : $processors;
    }
}
