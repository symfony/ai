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
use Symfony\AI\Agent\Exception\MissingModelSupportException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class Agent implements AgentInterface
{
    /**
     * @var InputProcessorInterface[]
     */
    private array $inputProcessors;

    /**
     * @var OutputProcessorInterface[]
     */
    private array $outputProcessors;

    /**
     * @param InputProcessorInterface[]  $inputProcessors
     * @param OutputProcessorInterface[] $outputProcessors
     * @param non-empty-string           $model
     */
    public function __construct(
        private PlatformInterface $platform,
        private string $model,
        iterable $inputProcessors = [],
        iterable $outputProcessors = [],
        private string $name = 'agent',
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
     * @param array<string, mixed> $options
     *
     * @throws MissingModelSupportException When the model doesn't support audio or image inputs present in the messages
     * @throws InvalidArgumentException     When the platform returns a client error (4xx) indicating invalid request parameters
     * @throws RuntimeException             When the platform returns a server error (5xx) or network failure occurs
     * @throws ExceptionInterface           When the platform converter throws an exception
     */
    public function call(MessageBag $messages, array $options = []): ResultInterface
    {
        $input = new Input($this->getModel(), $messages, $options);
        array_map(fn (InputProcessorInterface $processor) => $processor->processInput($input), $this->inputProcessors);

        $model = $input->getModel();
        $messages = $input->getMessageBag();
        $options = $input->getOptions();

        $result = $this->platform->invoke($model, $messages, $options)->getResult();

        $output = new Output($model, $result, $messages, $options);
        array_map(fn (OutputProcessorInterface $processor) => $processor->processOutput($output), $this->outputProcessors);

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
