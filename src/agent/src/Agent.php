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
     * @param InputProcessorInterface[]  $inputProcessors
     * @param OutputProcessorInterface[] $outputProcessors
     * @param non-empty-string           $model
     */
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly string $model,
        private readonly iterable $inputProcessors = [],
        private readonly iterable $outputProcessors = [],
        private readonly string $name = 'agent',
    ) {
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
     * @throws InvalidArgumentException When the platform returns a client error (4xx) indicating invalid request parameters
     * @throws RuntimeException         When the platform returns a server error (5xx) or network failure occurs
     * @throws ExceptionInterface       When the platform converter throws an exception
     */
    public function call(MessageBag $messages, array $options = []): ResultInterface
    {
        $input = new Input($this->getModel(), $messages, $options);
        foreach ($this->inputProcessors as $inputProcessor) {
            if (!$inputProcessor instanceof InputProcessorInterface) {
                throw new InvalidArgumentException(\sprintf('Input processor "%s" must implement "%s".', $inputProcessor::class, InputProcessorInterface::class));
            }

            if ($inputProcessor instanceof AgentAwareInterface) {
                $inputProcessor->setAgent($this);
            }

            $inputProcessor->processInput($input);
        }

        $model = $input->getModel();
        $messages = $input->getMessageBag();
        $options = $input->getOptions();

        $result = $this->platform->invoke($model, $messages, $options)->getResult();

        $output = new Output($model, $result, $messages, $options);
        foreach ($this->outputProcessors as $outputProcessor) {
            if (!$outputProcessor instanceof OutputProcessorInterface) {
                throw new InvalidArgumentException(\sprintf('Output processor "%s" must implement "%s".', $outputProcessor::class, OutputProcessorInterface::class));
            }

            if ($outputProcessor instanceof AgentAwareInterface) {
                $outputProcessor->setAgent($this);
            }

            $outputProcessor->processOutput($output);
        }

        return $output->getResult();
    }
}
