<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\StructuredOutput;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\Event\ResultEvent;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\MissingModelSupportException;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Serializer\StructuredOutputSerializer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class PlatformSubscriber implements EventSubscriberInterface
{
    public const RESPONSE_FORMAT = 'response_format';

    private string $outputType;

    private SerializerInterface $serializer;

    public function __construct(
        private readonly ResponseFormatFactoryInterface $responseFormatFactory = new ResponseFormatFactory(),
        ?SerializerInterface $serializer = null,
    ) {
        $this->serializer = $serializer ?? new StructuredOutputSerializer();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InvocationEvent::class => 'processInput',
            ResultEvent::class => 'processResult',
        ];
    }

    /**
     * @throws MissingModelSupportException When structured output is requested but the model doesn't support it
     * @throws InvalidArgumentException     When streaming is enabled with structured output (incompatible options)
     */
    public function processInput(InvocationEvent $event): void
    {
        $options = $event->getOptions();

        if (!isset($options[self::RESPONSE_FORMAT])) {
            return;
        }

        if (true === ($options['stream'] ?? false)) {
            throw new InvalidArgumentException('Streamed responses are not supported for structured output.');
        }

        if (\is_string($options[self::RESPONSE_FORMAT])) {
            if (!$event->getModel()->supports(Capability::OUTPUT_STRUCTURED)) {
                throw MissingModelSupportException::forStructuredOutput($event->getModel()::class);
            }

            if (!class_exists($options[self::RESPONSE_FORMAT])) {
                throw new InvalidArgumentException(\sprintf('The specified response format class "%s" does not exist.', $options[self::RESPONSE_FORMAT]));
            }

            $this->outputType = $options[self::RESPONSE_FORMAT];

            $options[self::RESPONSE_FORMAT] = $this->responseFormatFactory->create($options[self::RESPONSE_FORMAT]);
        }

        $event->setOptions($options);
    }

    public function processResult(ResultEvent $event): void
    {
        $options = $event->getOptions();

        if (!isset($options[self::RESPONSE_FORMAT])) {
            return;
        }

        $deferred = $event->getDeferredResult();
        $converter = new ResultConverter($deferred->getResultConverter(), $this->serializer, $this->outputType ?? null);

        $event->setDeferredResult(new DeferredResult($converter, $deferred->getRawResult(), $options));
    }
}
