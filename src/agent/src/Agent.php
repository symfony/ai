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
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\MissingModelSupportException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\MessageBagInterface;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

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
     */
    public function __construct(
        private PlatformInterface $platform,
        private Model $model,
        iterable $inputProcessors = [],
        iterable $outputProcessors = [],
        private LoggerInterface $logger = new NullLogger(),
    ) {
        $this->inputProcessors = $this->initializeProcessors($inputProcessors, InputProcessorInterface::class);
        $this->outputProcessors = $this->initializeProcessors($outputProcessors, OutputProcessorInterface::class);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws MissingModelSupportException When the model doesn't support audio or image inputs present in the messages
     * @throws InvalidArgumentException     When the platform returns a client error (4xx) indicating invalid request parameters
     * @throws RuntimeException             When the platform returns a server error (5xx) or network failure occurs
     */
    public function call(MessageBagInterface $messages, array $options = []): ResultInterface
    {
        $input = new Input($this->model, $messages, $options);
        array_map(fn (InputProcessorInterface $processor) => $processor->processInput($input), $this->inputProcessors);

        $model = $input->model;
        $messages = $input->messages;
        $options = $input->getOptions();

        if ($messages->containsAudio() && !$model->supports(Capability::INPUT_AUDIO)) {
            throw MissingModelSupportException::forAudioInput($model::class);
        }

        if ($messages->containsImage() && !$model->supports(Capability::INPUT_IMAGE)) {
            throw MissingModelSupportException::forImageInput($model::class);
        }

        try {
            $result = $this->platform->invoke($model, $messages, $options)->getResult();
        } catch (ClientExceptionInterface $e) {
            $message = $e->getMessage();
            $content = $e->getResponse()->toArray(false);

            $this->logger->debug($message, $content);

            throw new InvalidArgumentException('' === $message ? 'Invalid request to model or platform' : $message, previous: $e);
        } catch (HttpExceptionInterface $e) {
            throw new RuntimeException('Failed to request model.', previous: $e);
        }

        $output = new Output($model, $result, $messages, $options);
        array_map(fn (OutputProcessorInterface $processor) => $processor->processOutput($output), $this->outputProcessors);

        return $output->result;
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
